<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Config;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\RetryableWriteTransaction;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Account\PaymentAccountCollection;
use Shopwell\Core\System\Payment\Account\PaymentAccountDto;
use Shopwell\Core\System\Payment\Account\PaymentAccountEntity;
use Shopwell\Core\System\Payment\Account\PaymentAccountService;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppAccount\PaymentAppAccountCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;

/**
 * Stores the merchant configuration of the payment interfaces: the credentials of an interface and
 * the parameters of its channels.
 *
 * Everything stored here is sealed with the same cipher the gateway reads with. Plain text is accepted
 * while saving, but secret values are never handed back to the administration. Values sent empty stay
 * untouched, which makes it possible to edit a single field without retyping the rest - including a
 * secret whose original value the form cannot read back.
 *
 * An interface or channel that is switched on has to be complete, otherwise the first payment of an
 * app would fail with a message only the log sees.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentProviderConfigService
{
    /**
     * @param EntityRepository<PaymentAccountCollection> $accountRepository
     * @param EntityRepository<PaymentAppCollection> $appRepository
     * @param EntityRepository<PaymentAppAccountCollection> $appAccountRepository
     * @param EntityRepository<PaymentAppChannelCollection> $appChannelRepository
     * @param EntityRepository<PaymentProviderConfigCollection> $providerConfigRepository
     * @param EntityRepository<PaymentChannelConfigCollection> $channelConfigRepository
     */
    public function __construct(
        private readonly EntityRepository $providerConfigRepository,
        private readonly EntityRepository $channelConfigRepository,
        private readonly EntityRepository $accountRepository,
        private readonly EntityRepository $appRepository,
        private readonly EntityRepository $appAccountRepository,
        private readonly EntityRepository $appChannelRepository,
        private readonly Connection $connection,
        private readonly PaymentAccountService $accounts,
    ) {
    }

    /**
     * Seals and stores the credentials of an interface. The values that are not sent keep the ones
     * that are stored.
     *
     * @throws PaymentException
     */
    public function saveProvider(PaymentProvider $definition, bool $enabled, mixed $credentials, mixed $settings): void
    {
        RetryableWriteTransaction::retryable($this->connection, function () use ($definition, $enabled, $credentials, $settings): void {
            $this->storeProvider($definition, $enabled, $credentials, $settings);
        });
    }

    public function resetProvider(string $provider): void
    {
        $config = $this->findProviderConfig($provider);

        if ($config !== null) {
            $account = $this->accounts->get($config->accountId, Context::createDefaultContext());
            $this->accounts->save(new PaymentAccountDto($account->providerTechnicalName, $account->name, false, $account->enabledProducts, ownerAppId: $account->ownerAppId), Context::createDefaultContext(), $account->id);
            $this->providerConfigRepository->update([['id' => $config->id, 'enabled' => false]], Context::createDefaultContext());
        }
    }

    /**
     * Seals and stores the parameters of one channel of an interface. The values that are not sent
     * keep the ones that are stored.
     *
     * @throws PaymentException
     */
    public function saveChannel(PaymentProduct $definition, bool $enabled, mixed $params, mixed $settings): void
    {
        RetryableWriteTransaction::retryable($this->connection, function () use ($definition, $enabled, $params, $settings): void {
            $this->storeChannel($definition, $enabled, $params, $settings);
        });
    }

    public function resetChannel(string $provider, string $channel): void
    {
        $config = $this->findChannelConfig($provider, $channel);
        $providerConfig = $this->findProviderConfig($provider);
        if ($providerConfig !== null) {
            $account = $this->accounts->get($providerConfig->accountId, Context::createDefaultContext());
            $this->accounts->save(new PaymentAccountDto($account->providerTechnicalName, $account->name, $account->active, array_values(array_diff($account->enabledProducts, [$channel])), ownerAppId: $account->ownerAppId), Context::createDefaultContext(), $account->id);
        }

        if ($config !== null) {
            $this->channelConfigRepository->delete([['id' => $config->id]], Context::createDefaultContext());
        }
    }

    public function findAccount(string $id): ?PaymentAccountEntity
    {
        return $this->accountRepository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();
    }

    public function findProviderConfig(string $provider): ?PaymentProviderConfigEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('providerTechnicalName', $provider));
        $criteria->setLimit(1);

        $config = $this->providerConfigRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $config instanceof PaymentProviderConfigEntity ? $config : null;
    }

    /**
     * @return array<string, PaymentChannelConfigEntity>
     */
    public function findChannelConfigs(string $provider): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('providerTechnicalName', $provider));

        $configs = [];
        foreach ($this->channelConfigRepository->search($criteria, Context::createDefaultContext())->getEntities() as $config) {
            if ($config instanceof PaymentChannelConfigEntity) {
                $configs[$config->channelTechnicalName] = $config;
            }
        }

        return $configs;
    }

    public function findChannelConfig(string $provider, string $channel): ?PaymentChannelConfigEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('providerTechnicalName', $provider),
            new EqualsFilter('channelTechnicalName', $channel)
        );
        $criteria->setLimit(1);

        $config = $this->channelConfigRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $config instanceof PaymentChannelConfigEntity ? $config : null;
    }

    private function storeProvider(PaymentProvider $definition, bool $enabled, mixed $credentials, mixed $settings): void
    {
        $config = $this->findProviderConfig($definition->technicalName);

        $current = $config === null ? null : $this->findAccount($config->accountId);
        $settings = array_merge($config->settings ?? [], $this->mapOf($settings));
        $account = $this->accounts->save(new PaymentAccountDto(
            providerTechnicalName: $definition->technicalName,
            name: $current->name ?? $definition->technicalName,
            active: $enabled,
            enabledProducts: $current->enabledProducts ?? [],
            credentials: $this->mapOf($credentials),
            settings: $settings,
            ownerAppId: $current->ownerAppId ?? null,
        ), Context::createDefaultContext(), $config?->accountId);
        $accountId = $account->id;

        $this->providerConfigRepository->upsert([[
            'id' => $config->id ?? Uuid::randomHex(),
            'providerTechnicalName' => $definition->technicalName,
            'enabled' => $enabled,
            'accountId' => $accountId,
            'settings' => $settings,
        ]], Context::createDefaultContext());
        $platform = $this->platformApp();
        if ($platform !== null) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('paymentAppId', $platform->id), new EqualsFilter('accountId', $accountId));
            $grant = $this->appAccountRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
            $this->appAccountRepository->upsert([[
                'id' => $grant->id ?? Uuid::randomHex(),
                'paymentAppId' => $platform->id,
                'accountId' => $accountId,
                'enabled' => $enabled,
                'position' => $grant->position ?? 0,
            ]], Context::createDefaultContext());
        }
    }

    private function storeChannel(PaymentProduct $definition, bool $enabled, mixed $params, mixed $settings): void
    {
        $config = $this->findChannelConfig($definition->providerTechnicalName, $definition->technicalName);

        $settings = array_merge($config->settings ?? [], $this->mapOf($settings));
        $providerConfig = $this->findProviderConfig($definition->providerTechnicalName);
        if ($providerConfig === null) {
            throw PaymentProviderException::providerNotConfigured($definition->providerTechnicalName);
        }
        $account = $this->accounts->get($providerConfig->accountId, Context::createDefaultContext());
        $enabledProducts = array_values(array_diff($account->enabledProducts, [$definition->technicalName]));
        if ($enabled) {
            $enabledProducts[] = $definition->technicalName;
        }
        $this->accounts->save(new PaymentAccountDto(
            providerTechnicalName: $account->providerTechnicalName,
            name: $account->name,
            active: $account->active,
            enabledProducts: $enabledProducts,
            productParams: [$definition->technicalName => $this->mapOf($params)],
            ownerAppId: $account->ownerAppId,
        ), Context::createDefaultContext(), $account->id);

        $this->channelConfigRepository->upsert([[
            'id' => $config->id ?? Uuid::randomHex(),
            'providerTechnicalName' => $definition->providerTechnicalName,
            'channelTechnicalName' => $definition->technicalName,
            'enabled' => $enabled,
            'settings' => $settings,
        ]], Context::createDefaultContext());
        $platform = $this->platformApp();
        if ($platform !== null) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('paymentAppId', $platform->id), new EqualsFilter('channelTechnicalName', $definition->technicalName));
            $grant = $this->appChannelRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
            $this->appChannelRepository->upsert([[
                'id' => $grant->id ?? Uuid::randomHex(),
                'paymentAppId' => $platform->id,
                'channelTechnicalName' => $definition->technicalName,
                'enabled' => $enabled,
            ]], Context::createDefaultContext());
        }
    }

    private function platformApp(): ?PaymentAppEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', PaymentAppEntity::PLATFORM_TECHNICAL_NAME));

        return $this->appRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
    }

    /**
     * @return array<string, string>
     */
    private function mapOf(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $name => $item) {
            if (!\is_string($name) || !\is_scalar($item)) {
                continue;
            }

            $map[$name] = (string) $item;
        }

        return $map;
    }
}
