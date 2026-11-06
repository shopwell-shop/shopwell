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
use Shopwell\Core\System\Payment\Exception\PaymentAppException;
use Shopwell\Core\System\Payment\Exception\PaymentCheckoutBindingException;
use Shopwell\Core\System\Payment\Method\PaymentMethodRegistry;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProductRegistry;

/**
 * Binds the checkout method to a global gateway method, app and routing policy.
 * Saving also selects the gateway handler; the immutable product/account choice belongs to a payment attempt.
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
        private readonly PaymentMethodRegistry $methodRegistry,
        private readonly PaymentProductRegistry $products,
    ) {
    }

    /**
     * The stored logical method, app and routing policy, or null when no binding exists.
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
     * The bound app, with the platform app as the default for an unbound checkout method.
     */
    public function appOf(?PaymentMethodGatewayChannelEntity $binding, Context $context): PaymentAppEntity
    {
        $app = $binding?->paymentApp;

        return $app instanceof PaymentAppEntity ? $app : $this->app(null, $context);
    }

    /**
     * Validates the logical method and active app, then stores the binding and gateway handler.
     */
    public function bind(
        string $paymentMethodId,
        string $methodTechnicalName,
        ?string $paymentAppId,
        Context $context,
        string $routingPolicy = 'priority',
    ): PaymentMethodGatewayChannelEntity {
        $paymentMethod = $this->paymentMethod($paymentMethodId, $context);

        $this->methodRegistry->get($methodTechnicalName);
        $app = $this->app($paymentAppId, $context);

        // A logical method of an app belongs to one checkout method.
        $takenBy = $this->bindingOfMethod($app->id, $methodTechnicalName, $paymentMethodId, $context);

        if ($takenBy instanceof PaymentMethodGatewayChannelEntity) {
            /** @phpstan-ignore shopwell.domainException (The error belongs to the payment gateway the checkout wraps.) */
            throw PaymentCheckoutBindingException::paymentMethodChannelTaken(
                $this->nameOfApp($app),
                $methodTechnicalName,
                $takenBy->paymentMethodId,
                $this->nameOf($takenBy->paymentMethodId, $context)
            );
        }

        $existing = $this->binding($paymentMethodId, $context);
        $binding = new PaymentMethodGatewayChannelEntity();
        $binding->id = $existing instanceof PaymentMethodGatewayChannelEntity ? $existing->id : Uuid::randomHex();
        $binding->setUniqueIdentifier($binding->id);
        $binding->paymentMethodId = $paymentMethodId;
        $binding->paymentAppId = $app->id;
        $binding->methodTechnicalName = $methodTechnicalName;
        $binding->routingPolicy = $routingPolicy;
        $binding->paymentApp = $app;

        $this->bindingRepository->upsert([[
            'id' => $binding->id,
            'paymentMethodId' => $paymentMethodId,
            'paymentAppId' => $app->id,
            'methodTechnicalName' => $methodTechnicalName,
            'routingPolicy' => $routingPolicy,
        ]], $context);

        // Handler selection is a system write, separate from the editable binding fields.
        if ($paymentMethod->getHandlerIdentifier() !== PaymentGatewayHandler::class) {
            $this->paymentMethodRepository->update([[
                'id' => $paymentMethodId,
                'handlerIdentifier' => PaymentGatewayHandler::class,
            ]], Context::createDefaultContext());
        }

        return $binding;
    }

    /**
     * Logical methods already bound to other checkout methods, used to disable conflicting choices.
     *
     * @return list<array{paymentAppId: string, paymentAppName: string, methodTechnicalName: string, paymentMethodId: string, paymentMethodName: string}>
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
                'methodTechnicalName' => $binding->methodTechnicalName,
                'paymentMethodId' => $binding->paymentMethodId,
                'paymentMethodName' => $this->nameOf($binding->paymentMethodId, $context, $binding->paymentMethod),
            ];
        }

        return $taken;
    }

    /**
     * Only selection labels and authorized browser methods leave this read boundary.
     *
     * @return list<array{id: string, name: string, technicalName: string, type: string, active: bool, methods: list<array{technicalName: string, translationKey: string}>}>
     */
    public function selectableApps(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addAssociation('accounts.account');
        $criteria->addAssociation('channels');
        $apps = [];
        foreach ($this->appRepository->search($criteria, $context)->getEntities() as $app) {
            $providers = [];
            foreach ($app->accounts ?? [] as $grant) {
                if ($grant->enabled && $grant->account?->active) {
                    $providers[$grant->account->providerTechnicalName] = array_merge($providers[$grant->account->providerTechnicalName] ?? [], $grant->account->enabledProducts);
                }
            }
            $methods = [];
            foreach ($app->channels ?? [] as $grant) {
                if (!$grant->enabled || !$this->products->has($grant->channelTechnicalName)) {
                    continue;
                }
                $product = $this->products->get($grant->channelTechnicalName);
                if (!\in_array($product->technicalName, $providers[$product->providerTechnicalName] ?? [], true) || array_intersect($product->interactions, [PaymentInteraction::REDIRECT, PaymentInteraction::QRCODE]) === []) {
                    continue;
                }
                $method = $this->methodRegistry->get($product->method);
                $methods[$method->technicalName] = ['technicalName' => $method->technicalName, 'translationKey' => $method->translationKey];
            }
            $apps[] = [
                'id' => $app->id,
                'name' => $this->nameOfApp($app),
                'technicalName' => $app->technicalName,
                'type' => $app->type,
                'active' => $app->active,
                'methods' => array_values($methods),
            ];
        }

        return $apps;
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
            throw PaymentAppException::appNotFound($paymentAppId ?? PaymentAppEntity::PLATFORM_TECHNICAL_NAME);
        }

        if (!$app->active) {
            /** @phpstan-ignore shopwell.domainException (The error belongs to the payment gateway the checkout wraps.) */
            throw PaymentAppException::appDisabled($app->technicalName);
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
     * Finds another checkout method that already owns this app and logical method pair.
     */
    private function bindingOfMethod(
        string $paymentAppId,
        string $methodTechnicalName,
        string $exceptPaymentMethodId,
        Context $context,
    ): ?PaymentMethodGatewayChannelEntity {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('paymentAppId', $paymentAppId),
            new EqualsFilter('methodTechnicalName', $methodTechnicalName),
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
