<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Gateway;

use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelCollection;
use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelEntity;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigCollection;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigCollection;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigEntity;

/**
 * Keeps the payment methods out of the checkout that cannot collect a payment at all.
 *
 * A payment method whose `handler_identifier` is {@see PaymentGatewayHandler::class} collects through a
 * gateway channel, and everything it needs is configuration: a binding row, a channel that an installed
 * interface still declares, the channel switched on, a merchant account for the interface, an app that is
 * switched on and may collect that channel, and a channel that can process the currency of the shop and
 * be opened on the device of the request. Any of those missing makes the payment fail at the very end of
 * the checkout - after the shopper pressed "buy", with an exception instead of a payment page - which is
 * the worst moment to learn about a configuration mistake.
 *
 * Offering such a method is therefore not an option the merchant can pick, it is simply not offerable,
 * so the filter runs unconditionally and is not tied to `onlyAvailable`: that parameter means "respect
 * the availability rules", which is a merchandising decision about a method that works. A method that
 * would throw on selection is a different thing altogether.
 *
 * The device dimension is filtered too, but only when the request says which device asks: a channel that
 * declares `wap` only (wechat_h5, for instance) cannot be opened on a desktop browser, so a desktop
 * request must not be offered it. A request that brings no `device` - the store-api leaves that to the
 * client - is not judged by device at all: guessing one would hide a working method on the very device
 * the channel was declared for, so an unknown device keeps every channel.
 *
 * The app is the other half of the binding: the credentials that get charged belong to it, so an app
 * that is switched off can no longer collect, and an app that does not offer the bound channel would be
 * refused by the checkout when it authorizes the payment. The tenant of the platform is the exception -
 * its authorizations are written lazily on the first payment, so only its own state is checked here.
 *
 * The lookups are batched on purpose - one query for the bindings, one for the channel configurations,
 * one for the interface configurations, one for the apps and one for their channels - because this runs
 * on every checkout page load and a query per payment method would grow with the number of methods the
 * shop installs.
 *
 * @internal
 */
#[Package('checkout')]
final class PaymentMethodCollectability
{
    /**
     * @param EntityRepository<PaymentMethodGatewayChannelCollection> $bindingRepository
     * @param EntityRepository<PaymentChannelConfigCollection> $channelConfigRepository
     * @param EntityRepository<PaymentProviderConfigCollection> $providerConfigRepository
     * @param EntityRepository<PaymentAppCollection> $appRepository
     * @param EntityRepository<PaymentAppChannelCollection> $appChannelRepository
     */
    public function __construct(
        private readonly EntityRepository $bindingRepository,
        private readonly EntityRepository $channelConfigRepository,
        private readonly EntityRepository $providerConfigRepository,
        private readonly EntityRepository $appRepository,
        private readonly EntityRepository $appChannelRepository,
        private readonly PaymentChannelRegistry $channelRegistry,
    ) {
    }

    /**
     * Removes every payment method that would fail on selection from the collection.
     *
     * Methods that do not collect through the gateway are never touched: their handler brings its own
     * configuration, which this class cannot and must not judge.
     *
     * @param string|null $currencyIsoCode the currency the shopper pays in; a channel that cannot
     *                                     process it cannot collect, `null` skips the check
     * @param string|null $device the device the storefront asks for, e.g. `web` or `wap`;
     *                            `null` means the request did not say and skips the check
     */
    public function filter(PaymentMethodCollection $methods, Context $context, ?string $currencyIsoCode = null, ?string $device = null): PaymentMethodCollection
    {
        $gatewayMethodIds = $this->gatewayMethodIds($methods);

        if ($gatewayMethodIds === []) {
            return $methods;
        }

        $bindings = $this->bindings($gatewayMethodIds, $context);

        // a method without a binding has nothing to collect through, and neither has a method whose
        // channel is not declared by any installed interface (its extension may have been removed)
        $candidates = [];
        foreach ($bindings as $paymentMethodId => $binding) {
            if (!$this->channelRegistry->has($binding->channelTechnicalName)) {
                continue;
            }

            $channel = $this->channelRegistry->get($binding->channelTechnicalName);

            // a channel that is opened by an sdk is refused by the checkout when the payer is handed
            // over; a binding that predates the rule of the binder is therefore just as unusable
            if ($channel->flow === PaymentFlow::SDK) {
                continue;
            }

            if ($currencyIsoCode !== null && $currencyIsoCode !== '' && !$channel->supportsCurrency($currencyIsoCode)) {
                continue;
            }

            // a channel declared for other devices only cannot hand this payer over; a request without
            // a device passes, since the channel may well be the one that device was declared for
            if (!$channel->supportsDevice($device)) {
                continue;
            }

            $candidates[$paymentMethodId] = [
                'binding' => $binding,
                'provider' => $channel->providerTechnicalName,
            ];
        }

        if ($candidates === []) {
            return $methods->filter(static fn (PaymentMethodEntity $method): bool => !\in_array($method->getId(), $gatewayMethodIds, true));
        }

        $enabledChannels = $this->enabledChannels($candidates, $context);
        $configuredProviders = $this->configuredProviders($candidates, $context);
        $apps = $this->apps($candidates, $context);
        $offeredChannels = $this->offeredChannels($candidates, $context);

        return $methods->filter(static function (PaymentMethodEntity $method) use ($gatewayMethodIds, $candidates, $enabledChannels, $configuredProviders, $apps, $offeredChannels): bool {
            $paymentMethodId = $method->getId();

            if (!\in_array($paymentMethodId, $gatewayMethodIds, true)) {
                return true;
            }

            $candidate = $candidates[$paymentMethodId] ?? null;

            if (!\is_array($candidate)) {
                return false;
            }

            $binding = $candidate['binding'];

            if (!isset($enabledChannels[$binding->channelTechnicalName])) {
                return false;
            }

            // without a merchant account the interface cannot be charged, and the channel of an
            // interface the platform runs on its own account is only usable with credentials
            if (!isset($configuredProviders[$candidate['provider']])) {
                return false;
            }

            $app = $apps[$binding->paymentAppId] ?? null;

            if (!$app instanceof PaymentAppEntity || !$app->active) {
                return false;
            }

            // the tenant of the platform authorizes the interfaces of its channels on the first
            // payment; any other app only collects what the merchant has enabled for it
            if ($app->type === PaymentAppEntity::TYPE_PLATFORM) {
                return true;
            }

            return isset($offeredChannels[$binding->paymentAppId . ':' . $binding->channelTechnicalName]);
        });
    }

    /**
     * @return list<string>
     */
    private function gatewayMethodIds(PaymentMethodCollection $methods): array
    {
        $ids = [];

        foreach ($methods as $method) {
            if ($method->getHandlerIdentifier() === PaymentGatewayHandler::class) {
                $ids[] = $method->getId();
            }
        }

        return $ids;
    }

    /**
     * @param list<string> $paymentMethodIds
     *
     * @return array<string, PaymentMethodGatewayChannelEntity>
     */
    private function bindings(array $paymentMethodIds, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('paymentMethodId', $paymentMethodIds));

        $bindings = [];

        foreach ($this->bindingRepository->search($criteria, $context)->getEntities() as $binding) {
            if (!$binding instanceof PaymentMethodGatewayChannelEntity) {
                continue;
            }

            $bindings[$binding->paymentMethodId] = $binding;
        }

        return $bindings;
    }

    /**
     * The channels the platform currently offers, indexed by their technical name.
     *
     * @param array<string, array{binding: PaymentMethodGatewayChannelEntity, provider: string}> $candidates
     *
     * @return array<string, true>
     */
    private function enabledChannels(array $candidates, Context $context): array
    {
        $channelNames = [];

        foreach ($candidates as $candidate) {
            $channelNames[$candidate['binding']->channelTechnicalName] = $candidate['binding']->channelTechnicalName;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('channelTechnicalName', array_values($channelNames)));

        $enabled = [];

        foreach ($this->channelConfigRepository->search($criteria, $context)->getEntities() as $config) {
            if ($config instanceof PaymentChannelConfigEntity && $config->enabled) {
                $enabled[$config->channelTechnicalName] = true;
            }
        }

        return $enabled;
    }

    /**
     * The interfaces that have a merchant account, indexed by their technical name.
     *
     * Only the presence of credentials is checked, not their content: they are sealed by the
     * {@see \Shopwell\Core\System\Payment\App\PaymentCredentialCipher}, and an empty array is exactly
     * what "nothing stored yet" looks like in the column.
     *
     * @param array<string, array{binding: PaymentMethodGatewayChannelEntity, provider: string}> $candidates
     *
     * @return array<string, true>
     */
    private function configuredProviders(array $candidates, Context $context): array
    {
        $providerNames = [];

        foreach ($candidates as $candidate) {
            $providerNames[$candidate['provider']] = $candidate['provider'];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('providerTechnicalName', array_values($providerNames)));

        $configured = [];

        foreach ($this->providerConfigRepository->search($criteria, $context)->getEntities() as $config) {
            if (!$config instanceof PaymentProviderConfigEntity || !$config->enabled) {
                continue;
            }

            if (($config->credentials ?? []) === []) {
                continue;
            }

            $configured[$config->providerTechnicalName] = true;
        }

        return $configured;
    }

    /**
     * The apps the bindings collect on, indexed by their id.
     *
     * @param array<string, array{binding: PaymentMethodGatewayChannelEntity, provider: string}> $candidates
     *
     * @return array<string, PaymentAppEntity>
     */
    private function apps(array $candidates, Context $context): array
    {
        $appIds = [];

        foreach ($candidates as $candidate) {
            $appIds[$candidate['binding']->paymentAppId] = $candidate['binding']->paymentAppId;
        }

        $apps = [];

        foreach ($this->appRepository->search(new Criteria(array_values($appIds)), $context)->getEntities() as $app) {
            if ($app instanceof PaymentAppEntity) {
                $apps[$app->id] = $app;
            }
        }

        return $apps;
    }

    /**
     * The channels the apps offer themselves, as `appId:channel` - one row per channel an operator has
     * switched on for that app.
     *
     * @param array<string, array{binding: PaymentMethodGatewayChannelEntity, provider: string}> $candidates
     *
     * @return array<string, true>
     */
    private function offeredChannels(array $candidates, Context $context): array
    {
        $appIds = [];

        foreach ($candidates as $candidate) {
            $appIds[$candidate['binding']->paymentAppId] = $candidate['binding']->paymentAppId;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('paymentAppId', array_values($appIds)));

        $offered = [];

        foreach ($this->appChannelRepository->search($criteria, $context)->getEntities() as $appChannel) {
            if (!$appChannel instanceof PaymentAppChannelEntity || !$appChannel->enabled) {
                continue;
            }

            $offered[$appChannel->paymentAppId . ':' . $appChannel->channelTechnicalName] = true;
        }

        return $offered;
    }
}
