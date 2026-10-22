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
use Shopwell\Core\System\Payment\PaymentException as GatewayPaymentException;
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
 * A checkout payment method does not have to collect through that tenant: it collects through whichever
 * app its binding names, and {@see self::authorizeFor()} answers for that one as well - lazily for the
 * platform, read only for every other app, whose authorizations a merchant set up on purpose.
 *
 * The app itself, and its authorization for the one channel the platform switches on with it, are
 * written by the payment migration and by nothing else - a fresh installation can collect without
 * anyone configuring anything. A row that is missing is a broken installation or a broken upgrade,
 * and it is reported as such: silently writing one here would leave two writers of the same row and
 * make "why is this tenant not the platform one" an unanswerable question. Only the authorizations
 * of the other channels are written lazily, and only when such a channel is used - a channel the
 * merchant has not enabled for the platform is never handed out, because the storefront offers what
 * can really be paid, not what could theoretically be installed. An authorization that already
 * exists is respected as it is: the merchant may have switched it off or pointed it at own
 * credentials, and neither is overwritten here.
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
     * Hands out the tenant of the platform, authorized for the interface of the channel and offering
     * the channel itself.
     *
     * @throws PaymentException when the platform does not offer the channel at all
     */
    public function appForChannel(string $channelTechnicalName): PaymentAppEntity
    {
        return $this->authorizeFor($this->app(), $this->channels->get($channelTechnicalName));
    }

    /**
     * Hands out the app that collects a payment, authorized for the channel it is about to open.
     *
     * The tenant of the platform is authorized lazily, the way it always was: the first payment
     * authorizes the interface and offers the channel, so the shop's own payments need no onboarding by
     * hand, and the merchant can still switch a channel off for it - an authorization that exists is
     * respected as it is.
     *
     * Any other app is not authorized here. That app was onboarded by a merchant who decided which
     * interfaces it may charge and which of their channels it may offer, so writing those rows from the
     * checkout would be a second writer that overrules that decision without anyone noticing. An app
     * that does not offer the channel simply cannot collect it, and saying so is the only honest answer
     * left at that point.
     *
     * @throws PaymentException when the platform does not offer the channel at all
     * @throws GatewayPaymentException when the app does not offer the channel
     */
    public function authorizeFor(PaymentAppEntity $app, PaymentChannel $channel): PaymentAppEntity
    {
        if ($app->type !== PaymentAppEntity::TYPE_PLATFORM) {
            if (!$this->offersChannel($app->id, $channel->technicalName)) {
                /** @phpstan-ignore shopwell.domainException (The error belongs to the payment gateway the checkout wraps.) */
                throw GatewayPaymentException::channelNotEnabled($channel->technicalName, $app->id);
            }

            return $app;
        }

        if (!isset($this->enabledChannelConfigs()[$channel->technicalName])) {
            throw PaymentException::channelDisabledByPlatform($channel->technicalName);
        }

        $this->authorizeProvider($app->id, $channel);
        $this->offerChannel($app->id, $channel);

        return $app;
    }

    /**
     * Whether the app itself offers the channel, i.e. the merchant switched it on for that app.
     */
    private function offersChannel(string $appId, string $channelTechnicalName): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('paymentAppId', $appId),
            new EqualsFilter('channelTechnicalName', $channelTechnicalName),
            new EqualsFilter('enabled', true)
        );
        $criteria->setLimit(1);

        return $this->appChannelRepository->search($criteria, Context::createDefaultContext())->getEntities()->first() !== null;
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

    /**
     * The authorization is created for a channel the platform did not have authorized yet - the
     * interface of the seeded channel is already authorized by the migration.
     *
     * Only a missing or a switched-off authorization is written: a row that is enabled stays as it is,
     * including its account source, because the operator may have pointed it at own credentials on
     * purpose.
     */
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
            // the tenant of the platform is the root of every inheritance chain: it collects on the
            // platform level configuration and has no account to inherit
            'sourceAppId' => null,
        ]], Context::createDefaultContext());
    }

    /**
     * The channel is offered to the platform tenant the first time it is collected through - the
     * seeded channel is offered by the migration already, and a row that exists is never touched, so
     * a channel the merchant switched off stays off.
     */
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
