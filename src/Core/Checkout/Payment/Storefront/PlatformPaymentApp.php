<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Storefront;

use Shopwell\Core\Checkout\Payment\PaymentException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigCollection;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigCollection;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;

/**
 * The payment app the shop uses for its own payments.
 *
 * A storefront recharge is a payment like any other: it runs through the same gateway, with the same
 * credentials and the same notification, so it is collected by an app as well. That app is not
 * onboarded by hand - it belongs to the platform, carries the technical name
 * {@see self::TECHNICAL_NAME} and is authorized for the interface of the channel that is actually
 * used, the same way an operator would have done it in the administration.
 *
 * The app itself is written by the payment migration and by nothing else. A row that is missing is a
 * broken installation or a broken upgrade, and it is reported as such: silently writing one here
 * would leave two writers of the same row and make "why is this tenant not the platform one" an
 * unanswerable question. Only the authorizations around it are written lazily, and only when a channel
 * is used - a channel the merchant has not enabled for the platform is never handed out, because the
 * storefront offers what can really be paid, not what could theoretically be installed.
 *
 * @internal
 */
#[Package('checkout')]
final class PlatformPaymentApp
{
    final public const TECHNICAL_NAME = PaymentAppEntity::PLATFORM_TECHNICAL_NAME;

    /**
     * @var array<string, true>|null
     */
    private ?array $enabledChannelConfigs = null;

    /**
     * @var array<string, true>|null
     */
    private ?array $configuredProviders = null;

    /**
     * @param EntityRepository<PaymentAppCollection> $appRepository
     * @param EntityRepository<PaymentAppProviderCollection> $appProviderRepository
     * @param EntityRepository<PaymentAppChannelCollection> $appChannelRepository
     * @param EntityRepository<PaymentProviderConfigCollection> $providerConfigRepository
     * @param EntityRepository<PaymentChannelConfigCollection> $channelConfigRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly EntityRepository $appProviderRepository,
        private readonly EntityRepository $appChannelRepository,
        private readonly EntityRepository $providerConfigRepository,
        private readonly EntityRepository $channelConfigRepository,
        private readonly PaymentChannelRegistry $channels,
        private readonly PaymentProviderRegistry $providers,
    ) {
    }

    /**
     * The channels the storefront may offer right now: the channel is enabled for the platform, its
     * interface has platform credentials, and the channel can process the currency on this device.
     *
     * @return list<array{channel: string, label: string, provider: string, provider_label: string, flow: string}>
     */
    public function availableChannels(string $currency, ?string $device): array
    {
        $enabledChannels = $this->enabledChannelConfigs();
        $configuredProviders = $this->configuredProviders();

        $available = [];

        foreach ($this->channels->all() as $channel) {
            if (!isset($enabledChannels[$channel->technicalName]) || !isset($configuredProviders[$channel->providerTechnicalName])) {
                continue;
            }

            if (!$channel->supportsCurrency($currency) || !$channel->supportsDevice($device)) {
                continue;
            }

            $available[] = [
                'channel' => $channel->technicalName,
                'label' => $channel->label,
                'provider' => $channel->providerTechnicalName,
                'provider_label' => $this->providers->get($channel->providerTechnicalName)->label,
                'flow' => $channel->flow->value,
            ];
        }

        return $available;
    }

    /**
     * Hands out the app, authorized for the interface of the channel and offering the channel itself.
     *
     * The rows are only written when they are missing: an operator may have authorized the same
     * interface by hand - an existing authorization is then respected as it is and never silently
     * rewritten.
     *
     * @throws PaymentException when the platform does not offer the channel at all
     */
    public function appForChannel(string $channelTechnicalName): PaymentAppEntity
    {
        $channel = $this->channels->get($channelTechnicalName);

        if (!isset($this->enabledChannelConfigs()[$channelTechnicalName])) {
            throw PaymentException::channelDisabledByPlatform($channelTechnicalName);
        }

        $app = $this->app();

        $this->authorizeProvider($app->id, $channel);
        $this->offerChannel($app->id, $channel);

        return $app;
    }

    /**
     * @throws PaymentException when the payment migration did not seed the tenant
     */
    private function app(): PaymentAppEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', self::TECHNICAL_NAME));
        $criteria->setLimit(1);

        $app = $this->appRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        if (!$app instanceof PaymentAppEntity) {
            throw PaymentException::platformTenantMissing(self::TECHNICAL_NAME);
        }

        return $app;
    }

    private function authorizeProvider(string $appId, PaymentChannel $channel): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('paymentAppId', $appId),
            new EqualsFilter('providerTechnicalName', $channel->providerTechnicalName)
        );
        $criteria->setLimit(1);

        $existing = $this->appProviderRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        if ($existing instanceof PaymentAppProviderEntity && $existing->enabled) {
            return;
        }

        $this->appProviderRepository->upsert([[
            'id' => $existing instanceof PaymentAppProviderEntity ? $existing->id : Uuid::randomHex(),
            'paymentAppId' => $appId,
            'providerTechnicalName' => $channel->providerTechnicalName,
            'enabled' => true,
            // the shop always collects on the merchant account of the platform, never on an own one
            'mode' => PaymentAppProviderEntity::MODE_PLATFORM,
        ]], Context::createDefaultContext());
    }

    private function offerChannel(string $appId, PaymentChannel $channel): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('paymentAppId', $appId),
            new EqualsFilter('channelTechnicalName', $channel->technicalName)
        );
        $criteria->setLimit(1);

        if ($this->appChannelRepository->search($criteria, Context::createDefaultContext())->getEntities()->first() !== null) {
            return;
        }

        $this->appChannelRepository->create([[
            'id' => Uuid::randomHex(),
            'paymentAppId' => $appId,
            'channelTechnicalName' => $channel->technicalName,
            'enabled' => true,
        ]], Context::createDefaultContext());
    }

    /**
     * @return array<string, true>
     */
    private function enabledChannelConfigs(): array
    {
        if ($this->enabledChannelConfigs !== null) {
            return $this->enabledChannelConfigs;
        }

        $enabled = [];

        foreach ($this->channelConfigRepository->search(new Criteria(), Context::createDefaultContext())->getEntities() as $config) {
            if ($config instanceof PaymentChannelConfigEntity && $config->enabled) {
                $enabled[$config->channelTechnicalName] = true;
            }
        }

        return $this->enabledChannelConfigs = $enabled;
    }

    /**
     * An interface counts as configured once the merchant enabled it and stored credentials for it.
     *
     * @return array<string, true>
     */
    private function configuredProviders(): array
    {
        if ($this->configuredProviders !== null) {
            return $this->configuredProviders;
        }

        $configured = [];

        foreach ($this->providerConfigRepository->search(new Criteria(), Context::createDefaultContext())->getEntities() as $config) {
            if (!$config instanceof PaymentProviderConfigEntity || !$config->enabled) {
                continue;
            }

            if (($config->credentials ?? []) === []) {
                continue;
            }

            $configured[$config->providerTechnicalName] = true;
        }

        return $this->configuredProviders = $configured;
    }
}
