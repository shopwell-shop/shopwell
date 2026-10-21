<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\Service;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Order\OrderStates;
use Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnLineItem\OrderReturnLineItemCollection;
use Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnMessage\OrderReturnMessageCollection;
use Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnReason\OrderReturnReasonCollection;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnCollection;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnEvents;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnException;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnStates;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnTypes;
use Shopwell\Core\Checkout\OrderReturn\Event\OrderReturnCreatedEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The business of a return: opening it, deciding on it, moving the goods and paying them out.
 *
 * The service owns the whole flow and is the only place that knows which state may do what. The
 * controllers - administration and storefront alike - only hand in what a person asked for; every
 * rule that protects the shop (does the customer own the order, is the window open, is the quantity
 * still returnable) lives here, so a new entry point can never bypass it.
 *
 * The money is not touched here except for the hand over to {@see OrderReturnRefundService}: the
 * gateway books refunds, this service books what happened around them.
 *
 * @final
 */
#[Package('after-sales')]
class OrderReturnService
{
    private const DEFAULT_ASSOCIATIONS = [
        'lineItems.orderLineItem',
        'stateMachineState',
        'reason',
        'order.orderCustomer',
        'order.currency',
    ];

    /**
     * @internal
     *
     * @param EntityRepository<OrderReturnCollection> $orderReturnRepository
     * @param EntityRepository<OrderReturnLineItemCollection> $lineItemRepository
     * @param EntityRepository<OrderReturnMessageCollection> $messageRepository
     * @param EntityRepository<OrderReturnReasonCollection> $reasonRepository
     */
    public function __construct(
        private readonly EntityRepository $orderReturnRepository,
        private readonly EntityRepository $lineItemRepository,
        private readonly EntityRepository $messageRepository,
        private readonly EntityRepository $reasonRepository,
        private readonly EntityRepository $orderRepository,
        private readonly OrderReturnSettings $settings,
        private readonly OrderReturnNumberGenerator $numberGenerator,
        private readonly OrderReturnStateHandler $stateHandler,
        private readonly OrderReturnQuantityGuard $quantityGuard,
        private readonly OrderReturnStockRestorer $stockRestorer,
        private readonly OrderReturnRefundService $refundService,
        private readonly InitialStateIdLoader $initialStateIdLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Opens a return.
     *
     * `$positions` is a map of order line item id to the quantity that goes back. The map is
     * validated against the order and against everything that is already claimed by a running
     * return - the amount is calculated here and never taken from the request, so a customer can not
     * name a price.
     *
     * @param array{
     *     orderId: string,
     *     type: string,
     *     positions: array<string, int>,
     *     reasonId?: string|null,
     *     reasonText?: string|null,
     *     customerNote?: string|null,
     *     evidence?: list<string>|null,
     *     guestEmail?: string|null,
     *     guestTokenHash?: string|null
     * } $data
     *
     * @throws OrderReturnException
     */
    public function create(array $data, Context $context, ?CustomerEntity $customer): OrderReturnEntity
    {
        $type = $data['type'];

        if (!\in_array($type, OrderReturnTypes::all(), true)) {
            throw OrderReturnException::invalidType($type);
        }

        $order = $this->loadOrder($data['orderId'], $context);

        $salesChannelId = $order->getSalesChannelId();

        if (!$this->settings->isEnabled($salesChannelId)) {
            throw OrderReturnException::disabled();
        }

        $guestEmail = $data['guestEmail'] ?? null;

        if (!$this->ownsOrder($order, $customer, $guestEmail)) {
            // the message must not tell an anonymous visitor whether the order exists
            throw OrderReturnException::orderNotFound($order->getOrderNumber());
        }

        if ($order->getStateMachineState()?->getTechnicalName() === OrderStates::STATE_CANCELLED) {
            throw OrderReturnException::orderNotFinished($order->getOrderNumber());
        }

        if ($customer === null && !$this->settings->allowGuest($salesChannelId)) {
            throw OrderReturnException::disabled();
        }

        $this->assertWindowIsOpen($order, $salesChannelId);
        $this->assertReason($data['reasonId'] ?? null, $context);

        $positions = $data['positions'];

        if ($positions === []) {
            throw OrderReturnException::emptyPositions();
        }

        $this->quantityGuard->assertReturnable($order, $positions, $context);

        $factor = 10 ** ($order->getCurrency()?->getItemRounding()->getDecimals() ?? 2);

        $id = Uuid::randomHex();
        $lineItems = [];
        $total = 0;

        foreach ($positions as $lineItemId => $quantity) {
            $lineItem = $order->getLineItems()?->get($lineItemId);

            if (!$lineItem instanceof OrderLineItemEntity) {
                throw OrderReturnException::lineItemUnknown($lineItemId);
            }

            $amount = (int) round($lineItem->getUnitPrice() * $quantity * $factor);
            $total += $amount;

            $lineItems[] = [
                'id' => Uuid::randomHex(),
                'orderLineItemId' => $lineItem->getId(),
                'orderLineItemVersionId' => $lineItem->getVersionId(),
                'quantity' => $quantity,
                'refundAmount' => $amount,
                // the dal turns every non nullable property into a required field and writes a
                // missing one as null instead of using the ddl default, so both flags have to be
                // spelled out here (see OrderReturnLineItemEntity)
                'restocked' => false,
                'accepted' => true,
            ];
        }

        $deadline = $this->clock->now()->modify('+' . $this->settings->approvalDeadlineHours($salesChannelId) . ' hours');

        $this->orderReturnRepository->create([[
            'id' => $id,
            'returnNumber' => $this->numberGenerator->generate(),
            'orderId' => $order->getId(),
            'orderVersionId' => $order->getVersionId(),
            'customerId' => $customer?->getId(),
            'guestEmail' => $customer === null ? $guestEmail : null,
            'guestTokenHash' => $customer === null ? ($data['guestTokenHash'] ?? null) : null,
            'salesChannelId' => $salesChannelId,
            'type' => $type,
            'reasonId' => $this->reasonIdOrNull($data['reasonId'] ?? null),
            'reasonText' => $this->stringOrNull($data['reasonText'] ?? null),
            'customerNote' => $this->stringOrNull($data['customerNote'] ?? null),
            'evidence' => $data['evidence'] ?? null,
            'refundAmount' => $total,
            'currency' => $order->getCurrency()?->getIsoCode() ?? 'CNY',
            'stateId' => $this->initialStateIdLoader->get(OrderReturnStates::STATE_MACHINE),
            'deadlineAt' => $deadline,
            'lineItems' => $lineItems,
        ]], $context);

        $orderReturn = $this->get($id, $context);

        $this->eventDispatcher->dispatch(new OrderReturnCreatedEvent($orderReturn, $context), OrderReturnEvents::RETURN_CREATED_EVENT);

        return $orderReturn;
    }

    /**
     * Agrees to the request. A refund without goods is paid out right away, a return waits for the
     * parcel and gets the deadline by which it has to arrive.
     */
    public function approve(string $returnId, Context $context): OrderReturnEntity
    {
        $orderReturn = $this->get($returnId, $context);
        $this->assertState($orderReturn, [OrderReturnStates::STATE_REQUESTED], OrderReturnStates::ACTION_APPROVE);

        $this->stateHandler->approve($returnId, $context);
        $orderReturn = $this->get($returnId, $context);

        if (OrderReturnTypes::requiresReturn($orderReturn->type)) {
            $this->deadline($orderReturn, $this->returnDeadline($orderReturn), $context);

            return $this->get($returnId, $context);
        }

        $this->refundService->refund($orderReturn, $this->loadOrder($orderReturn->orderId, $context), $context);

        return $this->get($returnId, $context);
    }

    /**
     * Refuses the request. Declining is final: the goods stay with the customer and no refund can be
     * booked on this return anymore.
     */
    public function decline(string $returnId, string $reason, Context $context, ?string $administratorId = null): OrderReturnEntity
    {
        $orderReturn = $this->get($returnId, $context);
        $this->assertState($orderReturn, [OrderReturnStates::STATE_REQUESTED], OrderReturnStates::ACTION_DECLINE);

        $this->stateHandler->decline($returnId, $context);
        $this->recordDecision($returnId, $reason, $administratorId, $context);

        return $this->get($returnId, $context);
    }

    /**
     * Withdraws the request. A customer may withdraw as long as the parcel was not announced, a shop
     * may always close it - the goods simply stay where they are.
     */
    public function cancel(string $returnId, Context $context, ?string $note = null): OrderReturnEntity
    {
        $orderReturn = $this->get($returnId, $context);
        $this->assertState(
            $orderReturn,
            [OrderReturnStates::STATE_REQUESTED, OrderReturnStates::STATE_OPEN],
            OrderReturnStates::ACTION_CANCEL
        );

        $this->stateHandler->cancel($returnId, $context);

        if ($note !== null && trim($note) !== '') {
            $this->addMessage($returnId, trim($note), OrderReturnEntity::AUTHOR_AGENT, null, null, $context);
        }

        return $this->get($returnId, $context);
    }

    /**
     * Announces the parcel of the customer.
     */
    public function ship(string $returnId, ?string $carrier, string $trackingCode, Context $context): OrderReturnEntity
    {
        $orderReturn = $this->get($returnId, $context);
        $this->assertState($orderReturn, [OrderReturnStates::STATE_OPEN], OrderReturnStates::ACTION_SHIP);

        $this->orderReturnRepository->update([[
            'id' => $returnId,
            'trackingCarrier' => $this->stringOrNull($carrier),
            'trackingCode' => $trackingCode,
            'returnedAt' => $this->clock->now(),
            'deadlineAt' => null,
        ]], $context);

        $this->stateHandler->ship($returnId, $context);

        return $this->get($returnId, $context);
    }

    /**
     * Books the parcel in, puts the accepted goods back into the stock and pays the customer out.
     */
    public function receive(string $returnId, Context $context): OrderReturnEntity
    {
        $orderReturn = $this->get($returnId, $context);
        $this->assertState($orderReturn, [OrderReturnStates::STATE_RETURNING], OrderReturnStates::ACTION_RECEIVE);

        $this->orderReturnRepository->update([[
            'id' => $returnId,
            'receivedAt' => $this->clock->now(),
            'deadlineAt' => null,
        ]], $context);

        $this->stateHandler->receive($returnId, $context);

        $orderReturn = $this->get($returnId, $context);
        $order = $this->loadOrder($orderReturn->orderId, $context);

        if ($this->settings->restockOnReceive($orderReturn->salesChannelId)) {
            $this->stockRestorer->restock($orderReturn, $order, $context);
        }

        $this->refundService->refund($orderReturn, $order, $context);

        return $this->get($returnId, $context);
    }

    /**
     * Hands the return to the gateway again.
     *
     * It exists for the two cases the automatic payout can not cover: a channel that failed and was
     * fixed afterwards, and a return whose goods were accepted by hand although the parcel was never
     * announced. A payout that already started is answered with the refund it already has, so a
     * merchant can not refund twice by clicking twice.
     */
    public function refund(string $returnId, Context $context): OrderReturnEntity
    {
        $orderReturn = $this->get($returnId, $context);
        $this->assertState(
            $orderReturn,
            [OrderReturnStates::STATE_OPEN, OrderReturnStates::STATE_RECEIVED],
            OrderReturnStates::ACTION_REFUND
        );

        $this->refundService->refund($orderReturn, $this->loadOrder($orderReturn->orderId, $context), $context);

        return $this->get($returnId, $context);
    }

    /**
     * @param list<string>|null $attachments
     */
    public function addMessage(
        string $returnId,
        string $content,
        string $authorRole,
        ?string $authorId,
        ?array $attachments,
        Context $context
    ): void {
        $this->messageRepository->create([[
            'id' => Uuid::randomHex(),
            'orderReturnId' => $returnId,
            'authorRole' => $authorRole,
            'authorId' => $authorId,
            'content' => $content,
            'attachments' => $attachments,
        ]], $context);
    }

    /**
     * The positions of an order that may still be returned, with the quantity that is left.
     *
     * The storefront renders the form from this, so a customer never sees a position that would be
     * rejected afterwards.
     *
     * @return list<array{lineItemId: string, label: string, quantity: int, returnable: int, unitPrice: float}>
     */
    public function returnablePositions(OrderEntity $order, Context $context): array
    {
        $claimed = $this->quantityGuard->claimedQuantities($order->getId(), $context);
        $positions = [];

        foreach ($order->getLineItems() ?? [] as $lineItem) {
            if (!$lineItem instanceof OrderLineItemEntity || $lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            $returnable = $lineItem->getQuantity() - ($claimed[$lineItem->getId()] ?? 0);

            if ($returnable <= 0) {
                continue;
            }

            $positions[] = [
                'lineItemId' => $lineItem->getId(),
                'label' => (string) $lineItem->getLabel(),
                'quantity' => $lineItem->getQuantity(),
                'returnable' => $returnable,
                'unitPrice' => $lineItem->getUnitPrice(),
            ];
        }

        return $positions;
    }

    /**
     * @throws OrderReturnException
     */
    public function get(string $returnId, Context $context, ?Criteria $criteria = null): OrderReturnEntity
    {
        $criteria ??= new Criteria([$returnId]);

        foreach (self::DEFAULT_ASSOCIATIONS as $association) {
            $criteria->addAssociation($association);
        }

        $orderReturn = $this->orderReturnRepository->search($criteria, $context)->getEntities()->first();

        if (!$orderReturn instanceof OrderReturnEntity) {
            throw OrderReturnException::notFound($returnId);
        }

        return $orderReturn;
    }

    /**
     * The reasons a customer may pick, ordered the way the merchant arranged them.
     *
     * @return list<array{id: string, label: string}>
     */
    public function activeReasons(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));

        $reasons = [];

        foreach ($this->reasonRepository->search($criteria, $context)->getEntities() as $reason) {
            $reasons[] = [
                'id' => (string) $reason->id,
                'label' => (string) $reason->get('label'),
            ];
        }

        return $reasons;
    }

    /**
     * @throws OrderReturnException
     */
    private function assertState(OrderReturnEntity $orderReturn, array $allowed, string $action): void
    {
        $current = $orderReturn->stateMachineState?->getTechnicalName();

        if ($current === null || !\in_array($current, $allowed, true)) {
            throw OrderReturnException::stateNotAllowed($current ?? 'unknown', $action);
        }
    }

    /**
     * @throws OrderReturnException
     */
    private function assertWindowIsOpen(OrderEntity $order, string $salesChannelId): void
    {
        $days = $this->settings->windowDays($salesChannelId);

        if ($days <= 0) {
            return;
        }

        $start = $this->settings->windowStartsAtDelivery($salesChannelId)
            ? $this->lastDeliveryDate($order)
            : null;

        $start ??= $order->getOrderDate();

        if ($start === null) {
            return;
        }

        if ($start->modify('+' . $days . ' days') < $this->clock->now()) {
            throw OrderReturnException::windowExpired((string) $order->getOrderNumber(), $days);
        }
    }

    /**
     * The moment the parcel of the order left the shop last, which is when a return window that
     * counts from the delivery starts. An order that was never delivered falls back to its order
     * date, so a window that counts from the delivery never blocks a customer whose order is still
     * on its way.
     */
    private function lastDeliveryDate(OrderEntity $order): ?\DateTimeInterface
    {
        $latest = null;

        foreach ($order->getDeliveries() ?? [] as $delivery) {
            $state = $delivery->getStateMachineState()?->getTechnicalName();

            if ($state !== 'shipped' && $state !== 'partially_shipped') {
                continue;
            }

            $date = $delivery->getUpdatedAt() ?? $delivery->getCreatedAt();

            if ($date !== null && ($latest === null || $date > $latest)) {
                $latest = $date;
            }
        }

        return $latest;
    }

    private function ownsOrder(OrderEntity $order, ?CustomerEntity $customer, ?string $guestEmail): bool
    {
        $orderCustomer = $order->getOrderCustomer();

        if ($orderCustomer === null) {
            return false;
        }

        if ($customer !== null) {
            return $orderCustomer->getCustomerId() === $customer->getId();
        }

        if ($guestEmail === null || trim($guestEmail) === '') {
            return false;
        }

        // a guest proves the order by the mail address it was placed with; the comparison is case
        // insensitive because no mail address is, in practice, case sensitive
        return mb_strtolower(trim($guestEmail)) === mb_strtolower((string) $orderCustomer->getEmail());
    }

    /**
     * @throws OrderReturnException
     */
    private function loadOrder(string $orderId, Context $context): OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('stateMachineState');
        $criteria->addAssociation('deliveries');
        $criteria->addAssociation('deliveries.stateMachineState');
        $criteria->addAssociation('orderCustomer');

        $order = $this->orderRepository->search($criteria, $context)->getEntities()->first();

        if (!$order instanceof OrderEntity) {
            throw OrderReturnException::orderNotFound($orderId);
        }

        return $order;
    }

    private function assertReason(?string $reasonId, Context $context): void
    {
        if ($reasonId === null || $reasonId === '') {
            return;
        }

        $criteria = new Criteria([$reasonId]);
        $criteria->addFilter(new EqualsFilter('active', true));

        if ($this->reasonRepository->searchIds($criteria, $context)->firstId() === null) {
            throw OrderReturnException::reasonUnknown($reasonId);
        }
    }

    private function recordDecision(string $returnId, string $reason, ?string $administratorId, Context $context): void
    {
        if (trim($reason) !== '') {
            $this->addMessage($returnId, trim($reason), OrderReturnEntity::AUTHOR_AGENT, $administratorId, null, $context);
        }
    }

    private function deadline(OrderReturnEntity $orderReturn, ?\DateTimeInterface $deadline, Context $context): void
    {
        $this->orderReturnRepository->update([[
            'id' => $orderReturn->id,
            'deadlineAt' => $deadline,
        ]], $context);
    }

    private function returnDeadline(OrderReturnEntity $orderReturn): \DateTimeInterface
    {
        $days = $this->settings->returnDeadlineDays($orderReturn->salesChannelId);

        return $this->clock->now()->modify('+' . max($days, 1) . ' days');
    }

    private function reasonIdOrNull(?string $reasonId): ?string
    {
        return $reasonId === null || $reasonId === '' ? null : $reasonId;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
