<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Gateway;

use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelCollection;
use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelEntity;
use Shopwell\Core\Checkout\Payment\PaymentException as CheckoutPaymentException;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigService;

/**
 * Writes the binding between a checkout payment method and the pair it collects through.
 *
 * Binding is the whole configuration of a payment method that collects through the gateway, and it is
 * two writes that only make sense together:
 *
 *  1. the binding row names the app that is charged and the channel of it, i.e. which merchant account
 *     the money goes to and which way the payer is handed over, and
 *  2. `payment_method.handler_identifier` has to be the gateway handler, because the checkout dispatches
 *     on it - a binding on a method that still collects through, say, the invoice handler would never
 *     be read, so the binding would look configured and silently do nothing.
 *
 * The second write is the reason this class exists at all instead of the administration writing the
 * binding table through its generic endpoints: `handler_identifier` carries no `ApiAware` flag, so no
 * admin endpoint can write it. The write happens in the system context on purpose - it is the outcome
 * of the operator's decision "this method collects through that channel", not a field of its own.
 *
 * The channel is verified against the code that has to open the payment afterwards, and the app against
 * the tenants of the shop: a channel that no installed interface declares, an app that does not exist or
 * is switched off, an interface without a merchant account, or a channel the platform does not offer
 * would produce a binding that fails on the first payment. Only channels that hand the payer over through
 * a url or a qr code are bindable at all - a channel that is pulled up by an sdk is rendered by an app,
 * and the storefront refuses it when the payer is handed over, which is after the order was placed.
 *
 * Without an app the tenant of the platform is bound. That is the account the shop's checkout collects
 * on, and it keeps the single-tenant case - which is every installation that never onboarded an app -
 * free of a choice that has exactly one answer.
 *
 * There is deliberately no `unbind()`. The only way to stop collecting through a channel is to
 * deactivate the payment method, and that is where a shop expects it: deactivating is a checkout
 * decision that is visible on every other page, while an unbind would leave a method with the gateway
 * handler and without a channel - the state the checkout refuses with
 * {@see PaymentException::paymentMethodChannelMissing()}. Configuring a binding and taking it away
 * again would therefore not be symmetric, and offering the second half of an operation whose first
 * half cannot fail gracefully is worse than not offering it.
 *
 * @internal
 */
#[Package('checkout')]
final class PaymentMethodChannelBinder
{
    /**
     * @param EntityRepository<PaymentMethodGatewayChannelCollection> $bindingRepository
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     * @param EntityRepository<PaymentAppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $bindingRepository,
        private readonly EntityRepository $paymentMethodRepository,
        private readonly EntityRepository $appRepository,
        private readonly PaymentChannelRegistry $channelRegistry,
        private readonly PaymentProviderConfigService $configService,
    ) {
    }

    /**
     * The channel this payment method currently collects through, `null` when it is not bound.
     *
     * A method the administration has not bound yet is a normal state - every payment method of the
     * shop starts as an unbound one - so the absence is answered with `null` and not with an error.
     * The app comes along, because a caller that shows the binding also shows the account.
     */
    public function binding(string $paymentMethodId, Context $context): ?PaymentMethodGatewayChannelEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentMethodId', $paymentMethodId));
        $criteria->addAssociation('paymentApp');
        $criteria->setLimit(1);

        $binding = $this->bindingRepository->search($criteria, $context)->getEntities()->first();

        return $binding instanceof PaymentMethodGatewayChannelEntity ? $binding : null;
    }

    /**
     * The account a binding collects through, or the tenant of the platform when the method is not
     * bound yet: that is the default the checkout would use, so it is also what the operator is shown.
     *
     * @throws PaymentException when the payment migration did not seed the tenant of the platform
     */
    public function appOf(?PaymentMethodGatewayChannelEntity $binding, Context $context): PaymentAppEntity
    {
        $app = $binding?->paymentApp;

        return $app instanceof PaymentAppEntity ? $app : $this->app(null, $context);
    }

    /**
     * Binds the payment method to the channel, replacing an existing binding of the same method.
     *
     * Every check happens before the first write, so a rejected call leaves the configuration exactly
     * as it was - a half written binding would be a method that collects through a channel nobody
     * verified.
     *
     * @param string|null $paymentAppId the app that collects, `null` for the tenant of the platform
     *
     * @throws CheckoutPaymentException when the payment method does not exist
     * @throws PaymentException when the app, the interface or the channel is unknown, not enabled,
     *                          not configured or taken
     */
    public function bind(
        string $paymentMethodId,
        string $channelTechnicalName,
        ?string $paymentAppId,
        Context $context,
    ): PaymentMethodGatewayChannelEntity {
        $paymentMethod = $this->paymentMethod($paymentMethodId, $context);

        // 1. the channel is the thing the gateway opens, so it is verified first
        $channel = $this->channelRegistry->get($channelTechnicalName);

        // a channel that is pulled up by an sdk belongs to an app that renders the payment itself, so
        // the storefront can never hand a payer over to it - the checkout refuses it in
        // OrderPaymentStarter::handOver(), i.e. after the order was placed. Refusing the binding here
        // is the only moment the mistake is still correctable without a failed checkout.
        if ($channel->flow === PaymentFlow::SDK) {
            /** @phpstan-ignore shopwell.domainException (The error belongs to the payment gateway the checkout wraps.) */
            throw PaymentException::channelNotAvailableInStorefront($channelTechnicalName);
        }

        // 2. the app has to exist and has to be switched on: a disabled tenant cannot collect
        $app = $this->app($paymentAppId, $context);

        // 3. the interface of the channel needs a merchant account: a channel without one cannot be
        //    charged, and charging nothing is not what the operator asked for
        $providerConfig = $this->configService->findProviderConfig($channel->providerTechnicalName);

        if ($providerConfig === null || !$providerConfig->enabled || ($providerConfig->credentials ?? []) === []) {
            /** @phpstan-ignore shopwell.domainException (The error belongs to the payment gateway the checkout wraps.) */
            throw PaymentException::providerNotConfigured($channel->providerTechnicalName);
        }

        // 4. the platform has to offer the channel itself. Being declared by the interface is not
        //    enough - a channel the platform has switched off must not be offered to any payer.
        $channelConfig = $this->configService->findChannelConfig($channel->providerTechnicalName, $channelTechnicalName);

        if ($channelConfig === null || !$channelConfig->enabled) {
            /** @phpstan-ignore shopwell.domainException (The error belongs to the payment gateway the checkout wraps.) */
            throw PaymentException::channelDisabled($channelTechnicalName);
        }

        // 5. an app collects a channel on exactly one merchant account, so binding it to a second
        //    payment method would move the money of one method into the account of the other
        $takenBy = $this->bindingOfChannel($app->id, $channelTechnicalName, $paymentMethodId, $context);

        if ($takenBy instanceof PaymentMethodGatewayChannelEntity) {
            /** @phpstan-ignore shopwell.domainException (The error belongs to the payment gateway the checkout wraps.) */
            throw PaymentException::paymentMethodChannelTaken(
                $this->nameOfApp($app),
                $channelTechnicalName,
                $takenBy->paymentMethodId,
                $this->nameOf($takenBy->paymentMethodId, $context)
            );
        }

        // 6. the binding itself, upserted on the id of the existing row so a re-bind replaces the
        //    channel instead of running into the unique key over (app, channel)
        $existing = $this->binding($paymentMethodId, $context);
        $binding = new PaymentMethodGatewayChannelEntity();
        $binding->id = $existing instanceof PaymentMethodGatewayChannelEntity ? $existing->id : Uuid::randomHex();
        $binding->setUniqueIdentifier($binding->id);
        $binding->paymentMethodId = $paymentMethodId;
        $binding->paymentAppId = $app->id;
        $binding->channelTechnicalName = $channelTechnicalName;
        $binding->paymentApp = $app;

        $this->bindingRepository->upsert([[
            'id' => $binding->id,
            'paymentMethodId' => $paymentMethodId,
            'paymentAppId' => $app->id,
            'channelTechnicalName' => $channelTechnicalName,
        ]], $context);

        // 7. the dispatch the checkout really uses. It is written in the system context because the
        //    field is not writable through the admin api - which is exactly why this write lives here
        if ($paymentMethod->getHandlerIdentifier() !== PaymentGatewayHandler::class) {
            $this->paymentMethodRepository->update([[
                'id' => $paymentMethodId,
                'handlerIdentifier' => PaymentGatewayHandler::class,
            ]], Context::createDefaultContext());
        }

        return $binding;
    }

    /**
     * The app/channel pairs other payment methods already collect through.
     *
     * The administration greys those out instead of letting the operator pick them and learn from a
     * 409 that the pair belongs to another method - the message names the method, but the choice
     * should never have been offered.
     *
     * @return list<array{paymentAppId: string, paymentAppName: string, channelTechnicalName: string, paymentMethodId: string, paymentMethodName: string}>
     */
    public function taken(string $exceptPaymentMethodId, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new NotEqualsFilter('paymentMethodId', $exceptPaymentMethodId));
        $criteria->addAssociation('paymentMethod');
        $criteria->addAssociation('paymentApp');

        $taken = [];

        foreach ($this->bindingRepository->search($criteria, $context)->getEntities() as $binding) {
            if (!$binding instanceof PaymentMethodGatewayChannelEntity) {
                continue;
            }

            $taken[] = [
                'paymentAppId' => $binding->paymentAppId,
                'paymentAppName' => $binding->paymentApp instanceof PaymentAppEntity
                    ? $this->nameOfApp($binding->paymentApp)
                    : $binding->paymentAppId,
                'channelTechnicalName' => $binding->channelTechnicalName,
                'paymentMethodId' => $binding->paymentMethodId,
                'paymentMethodName' => $this->nameOf($binding->paymentMethodId, $context, $binding->paymentMethod),
            ];
        }

        return $taken;
    }

    /**
     * The app that collects, read by id - or the tenant of the platform when no id was sent, which is
     * the account the shop's own checkout collects on.
     *
     * @throws PaymentException when the app does not exist or is switched off
     */
    private function app(?string $paymentAppId, Context $context): PaymentAppEntity
    {
        $criteria = new Criteria();

        if ($paymentAppId === null || $paymentAppId === '') {
            $criteria->addFilter(new EqualsFilter('technicalName', PaymentAppEntity::PLATFORM_TECHNICAL_NAME));
        } else {
            $criteria->setIds([$paymentAppId]);
        }

        $criteria->setLimit(1);

        $app = $this->appRepository->search($criteria, $context)->getEntities()->first();

        if (!$app instanceof PaymentAppEntity) {
            /** @phpstan-ignore shopwell.domainException (The error belongs to the payment gateway the checkout wraps.) */
            throw PaymentException::appNotFound($paymentAppId ?? PaymentAppEntity::PLATFORM_TECHNICAL_NAME);
        }

        if (!$app->active) {
            /** @phpstan-ignore shopwell.domainException (The error belongs to the payment gateway the checkout wraps.) */
            throw PaymentException::appDisabled($app->technicalName);
        }

        return $app;
    }

    /**
     * @throws CheckoutPaymentException
     */
    private function paymentMethod(string $paymentMethodId, Context $context): PaymentMethodEntity
    {
        $paymentMethod = $this->paymentMethodRepository
            ->search(new Criteria([$paymentMethodId]), $context)
            ->getEntities()
            ->first();

        if (!$paymentMethod instanceof PaymentMethodEntity) {
            throw CheckoutPaymentException::unknownPaymentMethodById($paymentMethodId);
        }

        return $paymentMethod;
    }

    /**
     * The binding of the very app/channel pair, if it belongs to a payment method other than the given
     * one.
     */
    private function bindingOfChannel(
        string $paymentAppId,
        string $channelTechnicalName,
        string $exceptPaymentMethodId,
        Context $context,
    ): ?PaymentMethodGatewayChannelEntity {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('paymentAppId', $paymentAppId),
            new EqualsFilter('channelTechnicalName', $channelTechnicalName),
            new NotEqualsFilter('paymentMethodId', $exceptPaymentMethodId),
        );
        $criteria->setLimit(1);

        $binding = $this->bindingRepository->search($criteria, $context)->getEntities()->first();

        return $binding instanceof PaymentMethodGatewayChannelEntity ? $binding : null;
    }

    /**
     * The displayed name of a payment method. An association that is already loaded is used, otherwise
     * the method is read - the operator has to be told which method is in the way, and an id alone is
     * not an answer they can act on.
     */
    private function nameOf(string $paymentMethodId, Context $context, ?PaymentMethodEntity $paymentMethod = null): string
    {
        $paymentMethod ??= $this->paymentMethodRepository
            ->search(new Criteria([$paymentMethodId]), $context)
            ->getEntities()
            ->first();

        if (!$paymentMethod instanceof PaymentMethodEntity) {
            return $paymentMethodId;
        }

        $name = $paymentMethod->getName();

        return \is_string($name) && $name !== '' ? $name : $paymentMethodId;
    }

    /**
     * The displayed name of an app, with the technical name as the fallback: an app may be unnamed, and
     * the conflict message is useless without something the operator can recognize.
     */
    private function nameOfApp(PaymentAppEntity $app): string
    {
        $name = isset($app->name) ? $app->name : '';

        return $name !== '' ? $name : $app->technicalName;
    }
}
