<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Service;

use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Loyalty\Cart\LoyaltyPointCartProcessor;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointTransaction\LoyaltyPointTransactionCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointTransaction\LoyaltyPointTransactionEntity;
use Shopwell\Core\Checkout\Loyalty\LoyaltyPointTransactionTypes;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyAmountConverter;

/**
 * What a return does to the points of an order - in both directions.
 *
 * An order touches the loyalty account twice, and a return has to undo both, each in its own way:
 *
 * - **The order spent points.** They came back as a discount on the goods. When the goods go back,
 *   the discount has to go back too - as points, in the batches it was taken from, keeping the date
 *   those points were given. {@see refundableValue()} and {@see giveBack()} answer that half for the
 *   refund, which is why they speak in money: the return is settled in money, and this class says how
 *   much of it the points program covers.
 * - **The order earned points.** They were granted for buying the goods. When part of the goods comes
 *   back, part of the grant has to go with it, otherwise a customer can buy, collect and return for
 *   free. {@see revokeEarned()} does that per return, in proportion to the goods that came back and
 *   capped by what the order's batches still hold.
 *
 * The two are not the same operation and must not be merged: giving points back raises the balance,
 * taking them back lowers it, and a customer statement that showed one as the other would be wrong.
 *
 * What a point is worth is not decided here either - it is read back from the order itself: the
 * discount of the `loyalty-points` credit line item and the points the redemption booking charged are
 * the record of what one point bought on that day, whatever the ladder says today.
 *
 * @final
 */
#[Package('checkout')]
class LoyaltyReturnPoints
{
    /**
     * @internal
     *
     * @param EntityRepository<LoyaltyPointTransactionCollection> $pointTransactionRepository
     */
    public function __construct(
        private readonly EntityRepository $pointTransactionRepository,
        private readonly LoyaltyPointLots $lots,
        private readonly LoyaltyLedger $ledger,
    ) {
    }

    /**
     * How much of the return the points program settles, in the minor unit of the currency.
     *
     * It is the value of the points the order spent and has not been given back yet, so a second
     * return of the same order only gets what the first one left. Answering zero is the normal case
     * for an order that was never paid with points.
     *
     * The value of a single point is derived from the order and not from the ladder: the ladder may
     * have changed since, and the customer is owed what their points were worth when they spent them.
     */
    public function refundableValue(OrderReturnEntity $orderReturn, OrderEntity $order, Context $context): int
    {
        $redeemedValue = $this->redeemedValue($order, $orderReturn->currency);

        if ($redeemedValue <= 0) {
            return 0;
        }

        $usage = $this->lots->usageOf($order->getId(), LoyaltyPointTransactionTypes::REDEEM);

        if ($usage['taken'] <= 0 || $usage['outstanding'] <= 0) {
            return 0;
        }

        return (int) round($usage['outstanding'] * $redeemedValue / $usage['taken']);
    }

    /**
     * Gives back the points that $value of the return is worth.
     *
     * The amount is converted with the rate the order itself recorded, and the ledger caps the result
     * at what is still out, so a rounding difference can never give back more than was taken.
     */
    public function giveBack(OrderReturnEntity $orderReturn, OrderEntity $order, int $value, Context $context): void
    {
        $customerId = $orderReturn->customerId;

        if ($customerId === null || $value <= 0) {
            // a guest return is not tied to an account and therefore never spent points
            return;
        }

        $redeemedValue = $this->redeemedValue($order, $orderReturn->currency);

        if ($redeemedValue <= 0) {
            return;
        }

        $charged = $this->lots->usageOf($order->getId(), LoyaltyPointTransactionTypes::REDEEM)['taken'];

        if ($charged <= 0) {
            return;
        }

        $this->ledger->giveBackPoints(
            $customerId,
            LoyaltyPointTransactionTypes::RETURN,
            $order->getId(),
            (int) round($value * $charged / $redeemedValue),
            $context,
            $orderReturn->returnNumber,
        );
    }

    /**
     * Takes back the points the order earned for the goods that came back.
     *
     * The share is the value of the returned goods over the value of the whole order, so several
     * partial returns add up to the full grant without anybody having to count them. Only what the
     * batches of that order still hold is taken - points already spent are not turned into a debt,
     * and the return is booked either way.
     */
    public function revokeEarned(OrderReturnEntity $orderReturn, OrderEntity $order, Context $context): void
    {
        $customerId = $orderReturn->customerId;

        if ($customerId === null) {
            return;
        }

        $earned = $this->bookedPoints($order->getId(), LoyaltyPointTransactionTypes::AWARD, $context);

        if ($earned <= 0) {
            return;
        }

        $goodsValue = $this->goodsValue($order, $orderReturn->currency);

        if ($goodsValue <= 0) {
            return;
        }

        $share = min(1.0, $orderReturn->refundAmount / $goodsValue);
        $points = (int) round($earned * $share);

        if ($points <= 0) {
            return;
        }

        $this->ledger->revokeOrderPoints(
            $customerId,
            LoyaltyPointTransactionTypes::REVOKE,
            $order->getId(),
            $points,
            $context,
            $orderReturn->returnNumber,
        );
    }

    /**
     * The discount the order really gave for points, as an amount in the minor unit of the currency.
     *
     * Read from the credit line item and not from the ladder: the line item is what the customer was
     * charged, and it also covers the case that the redemption was clamped to a lower balance.
     */
    private function redeemedValue(OrderEntity $order, string $currency): int
    {
        $total = 0.0;

        foreach ($order->getLineItems() ?? [] as $lineItem) {
            if ($lineItem->getType() !== LineItem::CREDIT_LINE_ITEM_TYPE || $lineItem->getIdentifier() !== LoyaltyPointCartProcessor::LINE_ITEM_ID) {
                continue;
            }

            $total += abs($lineItem->getPrice()?->getTotalPrice() ?? 0.0);
        }

        return CurrencyAmountConverter::fromFloat($total, $currency);
    }

    /**
     * What the goods of the order were worth before any discount - the same measure the return itself
     * adds up when it is opened, so the share of a return is comparable to the share of the order.
     */
    private function goodsValue(OrderEntity $order, string $currency): int
    {
        $total = 0.0;

        foreach ($order->getLineItems() ?? [] as $lineItem) {
            if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            $total += $lineItem->getUnitPrice() * $lineItem->getQuantity();
        }

        return CurrencyAmountConverter::fromFloat($total, $currency);
    }

    /**
     * The sum of the bookings of one type for one order - positive for an award, negative for a
     * redemption. An order has a handful of bookings at most, so they are summed here instead of in SQL.
     */
    private function bookedPoints(string $orderId, string $type, Context $context): int
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('orderId', $orderId),
            new EqualsFilter('type', $type)
        );

        $total = 0;

        foreach ($this->pointTransactionRepository->search($criteria, $context)->getEntities() as $transaction) {
            if ($transaction instanceof LoyaltyPointTransactionEntity) {
                $total += $transaction->getPoints();
            }
        }

        return $total;
    }
}
