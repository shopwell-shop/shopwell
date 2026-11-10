<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Service;

use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnRefundDestination;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction\CustomerWalletTransactionCollection;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction\CustomerWalletTransactionEntity;
use Shopwell\Core\Checkout\Wallet\WalletException;
use Shopwell\Core\Checkout\Wallet\WalletTransactionTypes;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyAmountConverter;
use Shopwell\Core\System\Payment\Order\PaymentOrderRowLock;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;

/**
 * Gives back the part of an order the customer paid with the wallet.
 *
 * The ledger is the truth here, not the credit line item of the order: the debit is capped to what the
 * wallet really held when the order completed, so only the booked amount can be given back and a
 * second return of the same order only gets what the first one left.
 *
 * @final
 */
#[Package('checkout')]
class WalletRefundSource implements OrderReturnRefundDestination
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerWalletTransactionCollection> $transactionRepository
     */
    public function __construct(
        private readonly EntityRepository $transactionRepository,
        private readonly WalletPaymentService $payments,
        private readonly ?PaymentOrderService $paymentOrders = null,
        private readonly ?PaymentOrderRowLock $paymentOrderLock = null,
    ) {
    }

    public function getDestination(): string
    {
        return OrderReturnEntity::REFUND_WALLET;
    }

    public function refundableAmount(OrderReturnEntity $orderReturn, OrderEntity $order, Context $context): int
    {
        if ($orderReturn->customerId === null) {
            return 0;
        }

        return max(0, CurrencyAmountConverter::fromFloat(
            -$this->sum($order->getId(), WalletTransactionTypes::CONSUME, $context)
            - $this->sum($order->getId(), WalletTransactionTypes::REFUND, $context, $orderReturn->returnNumber),
            $orderReturn->currency
        ));
    }

    public function refundableDestinationAmount(OrderReturnEntity $orderReturn, OrderEntity $order, Context $context): int
    {
        if ($orderReturn->customerId === null) {
            return 0;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $order->getId()), new EqualsFilter('type', WalletTransactionTypes::REFUND), new EqualsFilter('externalReference', $orderReturn->returnNumber));
        $booked = $this->transactionRepository->search($criteria, $context)->getEntities()->first();
        if ($booked instanceof CustomerWalletTransactionEntity) {
            return CurrencyAmountConverter::fromFloat($booked->getAmount(), $orderReturn->currency);
        }

        $remaining = $this->refundableAmount($orderReturn, $order, $context);
        $transactionId = $order->getPrimaryOrderTransactionId();
        $payment = $transactionId === null ? null : $this->paymentOrders?->findLatestByOrderTransaction($transactionId);
        if ($payment?->paidAt !== null) {
            $remaining += max(0, $payment->paidAmount - $payment->refundedAmount - ($this->paymentOrderLock?->reservedRefundAmount($payment->id) ?? 0));
        }

        return $remaining;
    }

    public function refund(OrderReturnEntity $orderReturn, OrderEntity $order, int $amount, Context $context): void
    {
        $customerId = $orderReturn->customerId;

        if ($customerId === null) {
            return;
        }

        $book = function () use ($orderReturn, $order, $amount, $context, $customerId): void {
            $this->payments->refundForOrder(
                $order->getId(),
                $customerId,
                $order->getCurrencyId(),
                CurrencyAmountConverter::toFloat($amount, $orderReturn->currency),
                $context,
                $orderReturn->returnNumber,
            );
        };

        $transactionId = $order->getPrimaryOrderTransactionId();
        $payment = $orderReturn->refundDestination === OrderReturnEntity::REFUND_WALLET && $transactionId !== null
            ? $this->paymentOrders?->findLatestByOrderTransaction($transactionId) : null;

        if ($payment === null || $payment->paidAt === null) {
            $book();

            return;
        }

        if ($this->paymentOrderLock === null || $this->paymentOrders === null) {
            throw WalletException::refundNotPossible($orderReturn->returnNumber, 'wallet payout accounting is unavailable.');
        }

        $this->paymentOrderLock->transaction(function () use ($payment, $orderReturn, $order, $amount, $context, $book): void {
            $balances = $this->paymentOrderLock->lockOrder($payment->id);
            if ($balances === null) {
                throw WalletException::refundNotPossible($orderReturn->returnNumber, 'the collected payment no longer exists.');
            }

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('orderId', $order->getId()), new EqualsFilter('type', WalletTransactionTypes::REFUND), new EqualsFilter('externalReference', $orderReturn->returnNumber));
            if ($this->transactionRepository->search($criteria, $context)->getEntities()->count() > 0) {
                return;
            }

            $walletRemaining = max(0, CurrencyAmountConverter::fromFloat(
                -$this->sum($order->getId(), WalletTransactionTypes::CONSUME, $context)
                - $this->sum($order->getId(), WalletTransactionTypes::REFUND, $context),
                $orderReturn->currency
            ));
            $redirected = max(0, $amount - $walletRemaining);
            $available = $balances['paidAmount'] - $balances['refundedAmount'] - $this->paymentOrderLock->reservedRefundAmount($payment->id);
            if ($redirected > $available) {
                throw WalletException::refundNotPossible($orderReturn->returnNumber, 'the collected payment has insufficient refundable funds.');
            }

            if ($redirected > 0) {
                $this->paymentOrders->applyRefund($payment, $redirected);
            }
            $book();
        });
    }

    /**
     * The sum of the ledger entries of one type for an order - negative for a debit, positive for a
     * credit. An order has a handful of entries at most, so they are summed here instead of in SQL.
     */
    private function sum(string $orderId, string $type, Context $context, ?string $excludeReference = null): float
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('orderId', $orderId),
            new EqualsFilter('type', $type)
        );

        $total = 0.0;

        foreach ($this->transactionRepository->search($criteria, $context)->getEntities() as $transaction) {
            if ($transaction instanceof CustomerWalletTransactionEntity
                && ($excludeReference === null || $transaction->getExternalReference() !== $excludeReference)
            ) {
                $total += $transaction->getAmount();
            }
        }

        return $total;
    }
}
