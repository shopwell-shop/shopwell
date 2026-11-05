<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Account;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\RetryableWriteTransaction;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Exception\PaymentAppException;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigSanitizer;
use Shopwell\Core\System\Payment\Provider\Config\PaymentProviderConfigCollection;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;

/**
 * Owns merchant account configuration and the boundary between account values and app choices.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentAccountService
{
    /**
     * @param EntityRepository<PaymentAccountCollection> $accounts
     * @param EntityRepository<PaymentAppCollection> $apps
     * @param EntityRepository<PaymentProviderConfigCollection> $providerConfigs
     */
    public function __construct(
        private readonly EntityRepository $accounts,
        private readonly EntityRepository $apps,
        private readonly EntityRepository $providerConfigs,
        private readonly PaymentProviderRegistry $providers,
        private readonly PaymentCredentialCipher $cipher,
        private readonly Connection $connection,
        private readonly PaymentAccountLock $accountLock,
    ) {
    }

    public function get(string $id, Context $context): PaymentAccountEntity
    {
        $account = $this->accounts->search(new Criteria([$id]), $context)->getEntities()->first();
        if ($account === null) {
            throw PaymentProviderException::accountNotFound($id);
        }

        return $account;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(Context $context, bool $includeValues = true): array
    {
        $criteria = (new Criteria())->addSorting(new FieldSorting('name'), new FieldSorting('id'));
        $defaults = $this->defaultAccounts($context);
        $result = [];
        foreach ($this->accounts->search($criteria, $context)->getEntities() as $account) {
            $result[] = $this->present($account, isset($defaults[$account->id]), $includeValues);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function read(string $id, Context $context): array
    {
        return $this->present($this->get($id, $context), isset($this->defaultAccounts($context)[$id]), true);
    }

    public function save(PaymentAccountDto $dto, Context $context, ?string $id = null): PaymentAccountEntity
    {
        return RetryableWriteTransaction::retryable($this->connection, function () use ($dto, $context, $id): PaymentAccountEntity {
            $provider = $this->providers->get($dto->providerTechnicalName);
            if (trim($dto->name) === '') {
                throw PaymentException::missingParameter('name');
            }
            $current = $id === null ? null : $this->accountLock->lock($id);
            if ($current !== null && $current->providerTechnicalName !== $dto->providerTechnicalName) {
                throw PaymentProviderException::accountIdentityImmutable($current->id);
            }
            if ($dto->ownerAppId !== null && $this->apps->search(new Criteria([$dto->ownerAppId]), $context)->getEntities()->first() === null) {
                throw PaymentAppException::appNotFound($dto->ownerAppId);
            }
            foreach ($dto->enabledProducts as $productName) {
                if (!isset($provider->products[$productName])) {
                    throw PaymentProviderException::unknownChannel($productName);
                }
            }
            $this->assertDeclared($provider->technicalName, $provider->credentials, $dto->credentials);
            $storedCredentials = $this->cipher->decrypt($current?->credentials);
            $credentials = $this->mergeValues($provider->credentials, $storedCredentials, $dto->credentials, $current === null);
            if ($dto->active) {
                $this->assertComplete($provider->technicalName, $provider->credentials, $credentials);
            }
            $productParams = $current->productParams ?? [];
            $incomingProductParams = $dto->productParams;
            if ($current === null) {
                foreach (array_keys($provider->products) as $name) {
                    $incomingProductParams[$name] ??= [];
                }
            }
            foreach ($incomingProductParams as $name => $values) {
                if (!isset($provider->products[$name])) {
                    throw PaymentProviderException::unknownChannel($name);
                }
                $this->assertDeclared($name, $provider->products[$name]->params, $values);
                $storedValues = $this->cipher->decrypt($productParams[$name] ?? null);
                $values = $this->mergeValues($provider->products[$name]->params, $storedValues, $values, $current === null);
                $productParams[$name] = $current !== null && $values === $storedValues ? ($productParams[$name] ?? []) : $this->cipher->encrypt($values);
            }
            if ($dto->active) {
                foreach ($dto->enabledProducts as $name) {
                    $this->assertComplete($name, $provider->products[$name]->params, $this->cipher->decrypt($productParams[$name] ?? null));
                }
            }
            $account = $current === null ? new PaymentAccountEntity() : clone $current;
            $account->id = $id ?? Uuid::randomHex();
            $account->setUniqueIdentifier($account->id);
            $account->providerTechnicalName = $provider->technicalName;
            $account->name = trim($dto->name);
            $account->active = $dto->active;
            $account->enabledProducts = array_values(array_unique($dto->enabledProducts));
            $account->ownerAppId = $dto->ownerAppId ?? $current?->ownerAppId;
            $account->credentials = $current !== null && $credentials === $storedCredentials ? $current->credentials : $this->cipher->encrypt($credentials);
            $account->productParams = $current !== null && $dto->productParams === [] ? $current->productParams : $productParams;
            $account->settings = $current !== null && $dto->settings === [] ? $current->settings : array_merge($current->settings ?? [], $dto->settings);
            $this->accounts->upsert([[
                'id' => $account->id,
                'providerTechnicalName' => $account->providerTechnicalName,
                'name' => $account->name,
                'active' => $account->active,
                'enabledProducts' => $account->enabledProducts,
                'ownerAppId' => $account->ownerAppId,
                'credentials' => $account->credentials,
                'productParams' => $account->productParams,
                'settings' => $account->settings,
            ]], Context::createDefaultContext());

            return $account;
        });
    }

    public function delete(string $id, Context $context): void
    {
        RetryableWriteTransaction::retryable($this->connection, function () use ($id): void {
            $this->accountLock->lock($id);
            foreach (['payment_attempt', 'payment_transfer', 'payment_subscription', 'payment_mandate', 'payment_provider_config', 'payment_app_account'] as $table) {
                if ($this->connection->fetchOne('SELECT 1 FROM `' . $table . '` WHERE `account_id` = :id LIMIT 1 FOR UPDATE', ['id' => Uuid::fromHexToBytes($id)]) !== false) {
                    throw PaymentProviderException::accountInUse($id);
                }
            }
            $this->accounts->delete([['id' => $id]], Context::createDefaultContext());
        });
    }

    /**
     * @return array<string, true>
     */
    private function defaultAccounts(Context $context): array
    {
        $defaults = [];
        foreach ($this->providerConfigs->search(new Criteria(), $context)->getEntities() as $config) {
            $defaults[$config->accountId] = true;
        }

        return $defaults;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PaymentAccountEntity $account, bool $isDefault, bool $includeValues): array
    {
        $result = ['id' => $account->id, 'name' => $account->name, 'providerTechnicalName' => $account->providerTechnicalName, 'active' => $account->active, 'ownerAppId' => $account->ownerAppId, 'isDefault' => $isDefault];
        if (!$includeValues) {
            return $result;
        }
        $provider = $this->providers->has($account->providerTechnicalName) ? $this->providers->get($account->providerTechnicalName) : null;
        $result['enabledProducts'] = $account->enabledProducts;
        $result['credentials'] = $provider === null ? [] : PaymentConfigSanitizer::values($provider->credentials, array_intersect_key($this->cipher->decrypt($account->credentials), array_flip(array_column($provider->credentials, 'name'))));
        $result['productParams'] = [];
        foreach ($provider->products ?? [] as $name => $product) {
            $result['productParams'][$name] = PaymentConfigSanitizer::values($product->params, array_intersect_key($this->cipher->decrypt($account->productParams[$name] ?? null), array_flip(array_column($product->params, 'name'))));
        }
        $result['settings'] = $account->settings ?? [];

        return $result;
    }

    /**
     * @param list<PaymentConfigField> $fields
     * @param array<string, mixed> $current
     * @param array<string, mixed> $incoming
     *
     * @return array<string, mixed>
     */
    private function mergeValues(array $fields, array $current, array $incoming, bool $applyDefaults): array
    {
        $secrets = [];
        foreach ($fields as $field) {
            if ($applyDefaults && $field->default !== null && !\array_key_exists($field->name, $current)) {
                $current[$field->name] = $field->default;
            }
            if ($field->secret) {
                $secrets[$field->name] = true;
            }
        }
        foreach ($incoming as $name => $value) {
            if (isset($secrets[$name]) && ($value === null || $value === '')) {
                continue;
            }
            $current[$name] = $value;
        }

        return $current;
    }

    /**
     * @param list<PaymentConfigField> $fields
     * @param array<string, mixed> $values
     */
    private function assertDeclared(string $name, array $fields, array $values): void
    {
        $declared = array_column($fields, 'name');
        foreach (array_keys($values) as $field) {
            if (!\in_array($field, $declared, true)) {
                throw PaymentProviderException::unknownConfigurationField($name, (string) $field);
            }
        }
    }

    /**
     * @param list<PaymentConfigField> $fields
     * @param array<string, mixed> $values
     */
    private function assertComplete(string $name, array $fields, array $values): void
    {
        foreach ($fields as $field) {
            if ($field->required && (!isset($values[$field->name]) || $values[$field->name] === '')) {
                throw PaymentGatewayException::missingCredentials($name, $field->name);
            }
        }
    }
}
