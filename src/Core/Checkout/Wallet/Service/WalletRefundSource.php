<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Service;

use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnRefundSource;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction\CustomerWalletTransactionCollection;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction\CustomerWalletTransactionEntity;
use Shopwell\Core\Checkout\Wallet\WalletTransactionTypes;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyAmountConverter;

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
class WalletRefundSource implements OrderReturnRefundSource
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerWalletTransactionCollection> $transactionRepository
     */
    public function __construct(
        private readonly EntityRepository $transactionRepository,
        private readonly WalletPaymentService $payments,
    ) {
    }

    public function refundableAmount(OrderReturnEntity $orderReturn, OrderEntity $order, Context $context): int
    {
        if ($orderReturn->customerId === null) {
            // a guest return is not tied to an account and therefore never touched a wallet
            return 0;
        }

        $remaining = -$this->sum($order->getId(), WalletTransactionTypes::CONSUME, $context)
            - $this->sum($order->getId(), WalletTransactionTypes::REFUND, $context);

        if ($remaining <= 0.0) {
            return 0;
        }

        return CurrencyAmountConverter::fromFloat($remaining, $orderReturn->currency);
    }

    public function refund(OrderReturnEntity $orderReturn, OrderEntity $order, int $amount, Context $context): void
    {
        $customerId = $orderReturn->customerId;

        if ($customerId === null) {
            return;
        }

        $this->payments->refundForOrder(
            $order->getId(),
            $customerId,
            $order->getCurrencyId(),
            CurrencyAmountConverter::toFloat($amount, $orderReturn->currency),
            $context,
            $orderReturn->returnNumber,
        );
    }

    /**
     * The sum of the ledger entries of one type for an order - negative for a debit, positive for a
     * credit. An order has a handful of entries at most, so they are summed here instead of in SQL.
     */
    private function sum(string $orderId, string $type, Context $context): float
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('orderId', $orderId),
            new EqualsFilter('type', $type)
        );

        $total = 0.0;

        foreach ($this->transactionRepository->search($criteria, $context)->getEntities() as $transaction) {
            if ($transaction instanceof CustomerWalletTransactionEntity) {
                $total += $transaction->getAmount();
            }
        }

        return $total;
    }
}
