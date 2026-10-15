<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Subscriber;

use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Loyalty\Cart\LoyaltyPointCartProcessor;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyEntity;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointService;
use Shopwell\Core\Checkout\Loyalty\Service\TierResolver;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Order\OrderStates;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
class OrderStateChangeLoyaltySubscriber implements EventSubscriberInterface
{
    final public const CONFIG_KEY_POINTS_PER_CURRENCY_UNIT = 'core.loyalty.pointsPerCurrencyUnit';

    final public const CONFIG_KEY_GROWTH_PER_CURRENCY_UNIT = 'core.loyalty.growthPerCurrencyUnit';

    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     * @param EntityRepository<CustomerLoyaltyCollection> $customerLoyaltyRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $customerLoyaltyRepository,
        private readonly LoyaltyPointService $loyaltyPointService,
        private readonly TierResolver $tierResolver,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineTransitionEvent::class => 'onOrderStateChange',
        ];
    }

    public function onOrderStateChange(StateMachineTransitionEvent $event): void
    {
        $context = $event->getContext();

        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        if ($event->getEntityName() !== 'order') {
            return;
        }

        if ($event->getToPlace()->getTechnicalName() !== OrderStates::STATE_COMPLETED) {
            return;
        }

        $orderId = $event->getEntityId();

        $order = $this->loadOrder($orderId, $context);
        if (!$order instanceof OrderEntity) {
            return;
        }

        $customerId = $order->getOrderCustomer()?->getCustomerId();
        if ($customerId === null) {
            return;
        }

        $this->redeemPoints($customerId, $order, $context);
        $this->awardPoints($customerId, $order, $context);
    }

    /**
     * Consumes the points the customer redeemed while placing the order. The redeemed amount is
     * carried in the payload of the `loyalty-points` credit line item.
     */
    private function redeemPoints(string $customerId, OrderEntity $order, Context $context): void
    {
        $redeemed = (int) round($this->sumCreditPayload($order, LoyaltyPointCartProcessor::LINE_ITEM_ID, 'points'));

        if ($redeemed <= 0) {
            return;
        }

        // the balance may have changed while the order was open, so it is capped to what is still available
        $balance = $this->loyaltyPointService->getBalance($customerId, $context);
        $points = min($redeemed, $balance);

        if ($points <= 0) {
            return;
        }

        $this->loyaltyPointService->redeem($customerId, $points, $order->getId(), $order->getId() . '-redeemed', $context);
    }

    private function awardPoints(string $customerId, OrderEntity $order, Context $context): void
    {
        $amountTotal = $order->getAmountTotal();

        $pointsPerUnit = $this->systemConfigService->getInt(self::CONFIG_KEY_POINTS_PER_CURRENCY_UNIT);
        $growthPerUnit = $this->systemConfigService->getInt(self::CONFIG_KEY_GROWTH_PER_CURRENCY_UNIT);
        $multiplier = $this->tierResolver->getPointsMultiplier($customerId, $context);

        $points = (int) floor($amountTotal * $pointsPerUnit * $multiplier);
        $growth = (int) floor($amountTotal * $growthPerUnit);

        if ($points > 0) {
            $this->loyaltyPointService->award($customerId, $points, $order->getId(), $order->getId() . '-earned', $context);
        }

        if ($growth <= 0) {
            return;
        }

        $loyalty = $this->findLoyalty($customerId, $context);
        if ($loyalty === null) {
            return;
        }

        $growthValue = $loyalty->getGrowthValue() + $growth;

        $this->customerLoyaltyRepository->update([[
            'id' => $loyalty->getId(),
            'growthValue' => $growthValue,
        ]], $context);

        $this->tierResolver->resolve($customerId, $growthValue, $context);
    }

    /**
     * Sums the numeric payload value of all credit line items matching the given identifier.
     */
    private function sumCreditPayload(OrderEntity $order, string $identifier, string $payloadKey): float
    {
        $lineItems = $order->getLineItems();

        if ($lineItems === null) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($lineItems as $lineItem) {
            if ($lineItem->getType() !== LineItem::CREDIT_LINE_ITEM_TYPE || $lineItem->getIdentifier() !== $identifier) {
                continue;
            }

            $value = $lineItem->getPayloadValue($payloadKey);

            if (is_numeric($value)) {
                $total += (float) $value;
            }
        }

        return $total;
    }

    private function loadOrder(string $orderId, Context $context): ?OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('lineItems');

        $order = $this->orderRepository->search($criteria, $context)->getEntities()->first();

        return $order instanceof OrderEntity ? $order : null;
    }

    private function findLoyalty(string $customerId, Context $context): ?CustomerLoyaltyEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->setLimit(1);

        $loyalty = $this->customerLoyaltyRepository->search($criteria, $context)->getEntities()->first();

        return $loyalty instanceof CustomerLoyaltyEntity ? $loyalty : null;
    }
}
