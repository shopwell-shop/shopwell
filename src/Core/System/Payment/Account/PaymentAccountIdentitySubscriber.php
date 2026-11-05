<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Account;

use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A bound account is a stable merchant identity, while its signing and verification keys can rotate.
 * Applies to domain services as well as direct DAL writes; revoking an app grant does not erase history.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentAccountIdentitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PaymentAccountLock $accountLock,
        private readonly PaymentProviderRegistry $providers,
        private readonly PaymentCredentialCipher $cipher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'validate'];
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        // Bindings and account edits acquire the same lock before their SQL writes. Sort account
        // ids so a batched DAL write cannot acquire several accounts in a different order.
        $createdAccounts = [];
        foreach ($event->getCommandsForEntity('payment_account') as $command) {
            if ($command instanceof InsertCommand) {
                $createdAccounts[] = Uuid::fromBytesToHex($command->getPrimaryKey()['id']);
            }
        }
        $ids = [];
        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() === 'payment_account' && $command instanceof UpdateCommand) {
                $ids[] = Uuid::fromBytesToHex($command->getPrimaryKey()['id']);
            }
            if (\in_array($command->getEntityName(), ['payment_attempt', 'payment_transfer', 'payment_subscription', 'payment_mandate'], true)
                && ($command instanceof InsertCommand || $command instanceof UpdateCommand)
                && isset($command->getPayload()['account_id'])) {
                $ids[] = Uuid::fromBytesToHex($command->getPayload()['account_id']);
            }
        }
        sort($ids);
        $accounts = [];
        foreach (array_unique($ids) as $id) {
            // A nested account insert itself takes the exclusive lock; its row does not exist yet.
            if (\in_array($id, $createdAccounts, true)) {
                continue;
            }
            $accounts[$id] = $this->accountLock->lock($id);
        }
        foreach ($event->getCommandsForEntity('payment_account') as $command) {
            if (!$command instanceof UpdateCommand) {
                continue;
            }
            $id = Uuid::fromBytesToHex($command->getPrimaryKey()['id']);
            $account = $accounts[$id];
            $next = clone $account;
            $payload = $command->getPayload();
            foreach (['credentials' => 'credentials', 'product_params' => 'productParams', 'settings' => 'settings'] as $storageName => $property) {
                if (\array_key_exists($storageName, $payload)) {
                    $next->$property = $payload[$storageName] === null ? null : json_decode($payload[$storageName], true, flags: \JSON_THROW_ON_ERROR);
                }
            }
            if (isset($payload['provider_technical_name'])) {
                $next->providerTechnicalName = $payload['provider_technical_name'];
            }
            if (\array_key_exists('owner_app_id', $payload)) {
                $next->ownerAppId = $payload['owner_app_id'] === null ? null : Uuid::fromBytesToHex($payload['owner_app_id']);
            }
            $this->validateIdentity($account, $next);
        }
    }

    public function validateIdentity(PaymentAccountEntity $current, PaymentAccountEntity $next): void
    {
        if ($this->merchantIdentity($current) !== $this->merchantIdentity($next)) {
            foreach (['payment_attempt', 'payment_transfer', 'payment_subscription', 'payment_mandate'] as $table) {
                if ($this->accountLock->hasBinding($table, $current->id)) {
                    throw PaymentProviderException::accountIdentityImmutable($current->id);
                }
            }
        }

        // Collection attempts already freeze their product parameters. Other historical operations
        // resolve their bound product on the account, so only products used by those operations lock.
        $provider = $this->providers->get($current->providerTechnicalName);
        foreach (array_unique([...array_keys($current->productParams ?? []), ...array_keys($next->productParams ?? [])]) as $name) {
            $fields = $provider->products[$name]->params ?? [];
            $before = $this->stableValues($this->cipher->decrypt($current->productParams[$name] ?? null), $fields);
            $after = $this->stableValues($this->cipher->decrypt($next->productParams[$name] ?? null), $fields);
            if ($before === $after) {
                continue;
            }
            foreach (['payment_transfer', 'payment_subscription', 'payment_mandate'] as $table) {
                if ($this->accountLock->hasBinding($table, $current->id, $name)) {
                    throw PaymentProviderException::accountIdentityImmutable($current->id);
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function merchantIdentity(PaymentAccountEntity $account): array
    {
        $provider = $this->providers->get($account->providerTechnicalName);

        return [
            'provider' => $account->providerTechnicalName,
            'owner' => $account->ownerAppId,
            'credentials' => $this->stableValues($this->cipher->decrypt($account->credentials), $provider->credentials),
            'settings' => $this->stableValues($account->settings ?? [], []),
        ];
    }

    /**
     * @param array<string, mixed> $values
     * @param list<PaymentConfigField> $fields
     *
     * @return array<string, mixed>
     */
    private function stableValues(array $values, array $fields): array
    {
        foreach ($fields as $field) {
            if ($field->secret) {
                unset($values[$field->name]);
            }
        }
        ksort($values);

        return $values;
    }
}
