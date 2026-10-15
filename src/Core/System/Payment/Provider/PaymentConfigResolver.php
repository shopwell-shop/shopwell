<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;

/**
 * Resolves which interface, which merchant account and which channel parameters one payment runs with.
 *
 * There are two independent authorizations, and both are checked:
 *
 *  1. the app is authorized for the payment interface - that is the merchant account, and `mode` says
 *     whose it is: the platform account or the app's own one,
 *  2. the app has the channel enabled - being allowed to collect through alipay does not mean the app
 *     offers every alipay product to its users.
 *
 * The channel parameters follow the mode on purpose: a platform appid next to an app merchant id would
 * be rejected by the interface anyway, so the two must never be mixed.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentConfigResolver
{
    /**
     * @param EntityRepository<PaymentAppProviderCollection> $appProviderRepository
     * @param EntityRepository<PaymentProviderConfigCollection> $providerConfigRepository
     * @param EntityRepository<PaymentAppChannelCollection> $appChannelRepository
     * @param EntityRepository<PaymentChannelConfigCollection> $channelConfigRepository
     */
    public function __construct(
        private readonly PaymentProviderRegistry $providerRegistry,
        private readonly PaymentChannelRegistry $channelRegistry,
        private readonly EntityRepository $appProviderRepository,
        private readonly EntityRepository $providerConfigRepository,
        private readonly EntityRepository $appChannelRepository,
        private readonly EntityRepository $channelConfigRepository,
        private readonly PaymentCredentialCipher $cipher,
    ) {
    }

    /**
     * @param array<string, mixed> $runtimeParams the parameters the app handed in for this very payment
     *
     * @throws PaymentException
     */
    public function resolve(
        PaymentAppEntity $app,
        string $channelTechnicalName,
        string $currency,
        ?string $device = null,
        array $runtimeParams = []
    ): ResolvedPaymentConfig {
        $channel = $this->channelRegistry->get($channelTechnicalName);

        if (!$channel->supportsCurrency($currency)) {
            throw PaymentException::currencyNotSupported($channelTechnicalName, $currency);
        }

        if (!$channel->supportsDevice($device)) {
            throw PaymentException::deviceNotSupported($channelTechnicalName, (string) $device);
        }

        $channel->validateRuntimeParams($runtimeParams);

        $provider = $this->providerRegistry->get($channel->providerTechnicalName);

        $appProvider = $this->loadAppProvider($app->id, $provider->technicalName);

        if (!$appProvider instanceof PaymentAppProviderEntity || !$appProvider->enabled) {
            throw PaymentException::providerNotEnabled($provider->technicalName, $app->technicalName);
        }

        $appChannel = $this->loadAppChannel($app->id, $channelTechnicalName);

        if (!$appChannel instanceof PaymentAppChannelEntity || !$appChannel->enabled) {
            throw PaymentException::channelNotEnabled($channelTechnicalName, $app->technicalName);
        }

        $ownMode = $appProvider->mode === PaymentAppProviderEntity::MODE_OWN;
        $platformProvider = null;

        // A channel the platform does not offer cannot be collected through at all, no matter whose
        // merchant account is charged - which appid, for instance, a wechat channel runs on is decided
        // by the platform and not by the app.
        $platformChannel = $this->loadChannelConfig($provider->technicalName, $channelTechnicalName);

        if (!$platformChannel instanceof PaymentChannelConfigEntity || !$platformChannel->enabled) {
            throw PaymentException::channelDisabled($channelTechnicalName);
        }

        if ($ownMode) {
            $credentials = $this->cipher->decrypt($appProvider->credentials);
            $params = $this->cipher->decrypt($appChannel->params);
        } else {
            $platformProvider = $this->loadProviderConfig($provider->technicalName);

            if (!$platformProvider instanceof PaymentProviderConfigEntity || !$platformProvider->enabled) {
                throw PaymentException::providerNotConfigured($provider->technicalName);
            }

            $credentials = $this->cipher->decrypt($platformProvider->credentials);
            $params = $this->cipher->decrypt($platformChannel->params);
        }

        if ($credentials === []) {
            throw PaymentException::providerNotConfigured($provider->technicalName);
        }

        return new ResolvedPaymentConfig(
            provider: $provider,
            channel: $channel,
            credentials: $credentials,
            params: $params,
            runtimeParams: $runtimeParams,
            settings: $this->mergeSettings($platformProvider, $platformChannel, $appProvider, $appChannel),
            mode: $ownMode ? PaymentAppProviderEntity::MODE_OWN : PaymentAppProviderEntity::MODE_PLATFORM,
        );
    }

    /**
     * The channels an app may actually use, so the app can build its own payment selection without
     * guessing which of the channels of the platform are enabled for it.
     *
     * A channel that is enabled but whose interface is not configured is deliberately still listed: the
     * app learns that it is not usable yet when it creates the payment order, which is the only place
     * the credentials are needed.
     *
     * @return list<array<string, mixed>>
     */
    public function enabledFor(PaymentAppEntity $app): array
    {
        $context = Context::createDefaultContext();

        $providerCriteria = new Criteria();
        $providerCriteria->addFilter(new EqualsFilter('paymentAppId', $app->id), new EqualsFilter('enabled', true));

        $modes = [];
        foreach ($this->appProviderRepository->search($providerCriteria, $context)->getEntities() as $authorization) {
            if ($authorization instanceof PaymentAppProviderEntity) {
                $modes[$authorization->providerTechnicalName] = $authorization->mode;
            }
        }

        if ($modes === []) {
            return [];
        }

        $channelCriteria = new Criteria();
        $channelCriteria->addFilter(new EqualsFilter('paymentAppId', $app->id), new EqualsFilter('enabled', true));

        $channels = [];
        foreach ($this->appChannelRepository->search($channelCriteria, $context)->getEntities() as $assignment) {
            if (!$assignment instanceof PaymentAppChannelEntity) {
                continue;
            }

            // the channel of an extension may still be stored here while the extension is not installed
            // (anymore), which is not an error for the app
            if (!$this->channelRegistry->has($assignment->channelTechnicalName)) {
                continue;
            }

            $channel = $this->channelRegistry->get($assignment->channelTechnicalName);
            $mode = $modes[$channel->providerTechnicalName] ?? null;

            if ($mode === null) {
                // the interface itself is not authorized, so the channel is not usable either
                continue;
            }

            $channels[] = $this->presentChannel($channel, $mode);
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentChannel(PaymentChannel $channel, string $mode): array
    {
        $provider = $this->providerRegistry->get($channel->providerTechnicalName);

        return [
            'channel' => $channel->technicalName,
            'channel_label' => $channel->label,
            'provider' => $provider->technicalName,
            'provider_label' => $provider->label,
            'flow' => $channel->flow->value,
            'currencies' => $channel->currencies,
            'devices' => $channel->devices,
            'runtime_params' => $channel->runtimeParams,
            'mode' => $mode,
        ];
    }

    /**
     * The platform settings are the defaults, the app may override them.
     *
     * @return array<string, mixed>
     */
    private function mergeSettings(
        ?PaymentProviderConfigEntity $platformProvider,
        ?PaymentChannelConfigEntity $platformChannel,
        PaymentAppProviderEntity $appProvider,
        PaymentAppChannelEntity $appChannel
    ): array {
        $platform = array_merge(
            $platformProvider->settings ?? [],
            $platformChannel->settings ?? []
        );

        $app = array_merge(
            $appProvider->settings ?? [],
            $appChannel->settings ?? []
        );

        return array_merge($platform, $app);
    }

    private function loadProviderConfig(string $providerTechnicalName): ?PaymentProviderConfigEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('providerTechnicalName', $providerTechnicalName));
        $criteria->setLimit(1);

        $config = $this->providerConfigRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $config instanceof PaymentProviderConfigEntity ? $config : null;
    }

    private function loadChannelConfig(string $providerTechnicalName, string $channelTechnicalName): ?PaymentChannelConfigEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('providerTechnicalName', $providerTechnicalName),
            new EqualsFilter('channelTechnicalName', $channelTechnicalName)
        );
        $criteria->setLimit(1);

        $config = $this->channelConfigRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $config instanceof PaymentChannelConfigEntity ? $config : null;
    }

    private function loadAppProvider(string $appId, string $providerTechnicalName): ?PaymentAppProviderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('paymentAppId', $appId),
            new EqualsFilter('providerTechnicalName', $providerTechnicalName)
        );
        $criteria->setLimit(1);

        $provider = $this->appProviderRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $provider instanceof PaymentAppProviderEntity ? $provider : null;
    }

    private function loadAppChannel(string $appId, string $channelTechnicalName): ?PaymentAppChannelEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('paymentAppId', $appId),
            new EqualsFilter('channelTechnicalName', $channelTechnicalName)
        );
        $criteria->setLimit(1);

        $channel = $this->appChannelRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $channel instanceof PaymentAppChannelEntity ? $channel : null;
    }
}
