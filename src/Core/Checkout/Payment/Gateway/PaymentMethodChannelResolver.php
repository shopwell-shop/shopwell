<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Gateway;

use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelCollection;
use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;

/**
 * Resolves the gateway channel a checkout payment method collects through.
 *
 * This is the only place the two halves of the platform meet: the payment method is what the shopper
 * picks, the channel is what the gateway knows how to open. The binding is data, not a naming
 * convention - a method may be renamed and a channel may be renamed without either side noticing -
 * so the channel is looked up in the table that the administration writes and nowhere else.
 *
 * @internal
 */
#[Package('checkout')]
final class PaymentMethodChannelResolver
{
    /**
     * @param EntityRepository<PaymentMethodGatewayChannelCollection> $bindingRepository
     */
    public function __construct(
        private readonly EntityRepository $bindingRepository,
        private readonly PaymentChannelRegistry $channelRegistry,
    ) {
    }

    /**
     * @throws PaymentException when the payment method is not bound to a gateway channel
     */
    public function resolve(string $paymentMethodId, Context $context): PaymentMethodGatewayChannelEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentMethodId', $paymentMethodId));
        // the app comes along: the payment is created for it, and a second read for the very row the
        // binding already points at would be a query per checkout
        $criteria->addAssociation('paymentApp');
        $criteria->setLimit(1);

        $binding = $this->bindingRepository->search($criteria, $context)->getEntities()->first();

        if (!$binding instanceof PaymentMethodGatewayChannelEntity) {
            /** @phpstan-ignore shopwell.domainException (The error belongs to the payment gateway the checkout wraps.) */
            throw PaymentException::paymentMethodChannelMissing($paymentMethodId);
        }

        return $binding;
    }

    /**
     * @throws PaymentException when the payment method is not bound or the bound channel is unknown
     */
    public function channelOf(string $paymentMethodId, Context $context): PaymentChannel
    {
        return $this->channelRegistry->get($this->resolve($paymentMethodId, $context)->channelTechnicalName);
    }
}
