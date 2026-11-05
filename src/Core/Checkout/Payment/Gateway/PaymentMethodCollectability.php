<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Gateway;

use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelCollection;
use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelEntity;
use Shopwell\Core\Checkout\Payment\PaymentException;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Routing\PaymentContext;
use Shopwell\Core\System\Payment\Routing\PaymentRoute;
use Shopwell\Core\System\Payment\Routing\PaymentRouteResolver;

/**
 * The common router decides whether the selected method has an authorized route for this browser.
 *
 * @internal
 */
#[Package('checkout')]
class PaymentMethodCollectability
{
    /**
     * @param EntityRepository<PaymentMethodGatewayChannelCollection> $bindingRepository
     */
    public function __construct(
        private readonly EntityRepository $bindingRepository,
        private readonly PaymentRouteResolver $routes,
    ) {
    }

    public function filter(PaymentMethodCollection $methods, Context $context, PaymentContext $paymentContext): PaymentMethodCollection
    {
        $ids = [];
        foreach ($methods as $method) {
            if ($method->getHandlerIdentifier() === PaymentGatewayHandler::class) {
                $ids[] = $method->getId();
            }
        }
        if ($ids === []) {
            return $methods;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('paymentMethodId', $ids));
        $criteria->addAssociation('paymentApp');
        $available = [];
        foreach ($this->bindingRepository->search($criteria, $context)->getEntities() as $binding) {
            if (!$binding instanceof PaymentMethodGatewayChannelEntity || !$binding->paymentApp instanceof PaymentAppEntity) {
                continue;
            }
            if ($this->routes->options($binding->paymentApp, $paymentContext, allowedMethods: [$binding->methodTechnicalName], policyName: $binding->routingPolicy) !== []) {
                $available[$binding->paymentMethodId] = true;
            }
        }

        return $methods->filter(static fn (PaymentMethodEntity $method): bool => !\in_array($method->getId(), $ids, true) || isset($available[$method->getId()]));
    }

    public function resolve(PaymentMethodGatewayChannelEntity $binding, Context $context, PaymentContext $selection): PaymentRoute
    {
        if (!$binding->paymentApp instanceof PaymentAppEntity) {
            throw PaymentException::channelNotAvailableInStorefront($binding->methodTechnicalName);
        }

        return $this->routes->resolveMethod($binding->paymentApp, $binding->methodTechnicalName, $selection, policyName: $binding->routingPolicy);
    }
}
