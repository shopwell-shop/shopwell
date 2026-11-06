<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Account;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\RetryableWriteTransaction;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;

/**
 * Serializes account configuration changes and durable bindings on the DAL connection.
 * Locking reads deliberately bypass repeatable-read snapshots established before a lock wait.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\System\Payment\Account\PaymentAccountLockTest
 */
#[Package('framework')]
class PaymentAccountLock
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @template TReturn
     *
     * @param \Closure(): TReturn $write
     *
     * @return TReturn
     */
    public function transaction(\Closure $write)
    {
        return RetryableWriteTransaction::retryable($this->connection, static fn () => $write());
    }

    public function lock(string $id): PaymentAccountEntity
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM payment_account WHERE id = :id FOR UPDATE', ['id' => Uuid::fromHexToBytes($id)], ['id' => ParameterType::BINARY]);
        if ($row === false) {
            throw PaymentProviderException::accountNotFound($id);
        }
        $account = new PaymentAccountEntity();
        $account->id = $id;
        $account->setUniqueIdentifier($id);
        $account->providerTechnicalName = $row['provider_technical_name'];
        $account->ownerAppId = $row['owner_app_id'] === null ? null : Uuid::fromBytesToHex($row['owner_app_id']);
        $account->name = $row['name'];
        $account->active = (bool) $row['active'];
        $account->enabledProducts = json_decode($row['enabled_products'], true, flags: \JSON_THROW_ON_ERROR);
        foreach (['credentials' => 'credentials', 'product_params' => 'productParams', 'settings' => 'settings'] as $column => $property) {
            $account->$property = $row[$column] === null ? null : json_decode($row[$column], true, flags: \JSON_THROW_ON_ERROR);
        }

        $account->setCustomFields($row['custom_fields'] === null ? null : json_decode($row['custom_fields'], true, flags: \JSON_THROW_ON_ERROR));

        return $account;
    }

    /**
     * @param 'payment_attempt'|'payment_transfer'|'payment_subscription'|'payment_mandate' $table
     */
    public function hasBinding(string $table, string $accountId, ?string $product = null): bool
    {
        $params = ['id' => Uuid::fromHexToBytes($accountId)];
        $where = '';
        if ($product !== null) {
            $where = ' AND channel = :product';
            $params['product'] = $product;
        }

        return $this->connection->fetchOne('SELECT id FROM `' . $table . '` WHERE account_id = :id' . $where . ' LIMIT 1 FOR UPDATE', $params, ['id' => ParameterType::BINARY]) !== false;
    }

    public function validateConfiguration(ResolvedPaymentConfig $config): void
    {
        $account = $this->lock($config->accountId);
        if ($config->accountFingerprint !== self::fingerprint($account)) {
            throw PaymentProviderException::accountConfigurationChanged($account->id);
        }
    }

    public static function fingerprint(PaymentAccountEntity $account): string
    {
        return Hasher::hash([
            $account->providerTechnicalName,
            $account->ownerAppId,
            $account->active,
            $account->enabledProducts,
            $account->credentials,
            $account->productParams,
            $account->settings,
        ], 'sha256');
    }
}
