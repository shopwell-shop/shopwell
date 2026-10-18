<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Gateway;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundEntity;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopwell\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopwell\Core\Checkout\Payment\Cart\RefundPaymentTransactionStruct;
use Shopwell\Core\Checkout\Payment\PaymentException as CheckoutPaymentException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;
use Shopwell\Core\System\Currency\CurrencyEntity;
use Shopwell\Core\System\Payment\Api\Struct\PaymentRefundRequest;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderReconciler;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundService;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Collects a checkout payment through the generic payment gateway.
 *
 * The class is the bridge in the other direction of the binding table: a payment method whose
 * `handler_identifier` is this class is bound to a gateway channel, and every lifecycle step of its
 * payments runs through the code of that channel - opening it here, writing the paid state back
 * through the event of the gateway, and refunding it through the refund service.
 *
 * The fully qualified class name is persisted as `payment_method.handler_identifier` and the handler
 * registry matches it against the service id exactly. Renaming this class therefore breaks every
 * payment method that already uses it and needs a migration - the name is part of the database
 * contract, not an implementation detail.
 *
 * @internal
 */
#[Package('checkout')]
final class PaymentGatewayHandler extends AbstractPaymentHandler
{
    /**
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionRepository
     * @param EntityRepository<OrderTransactionCaptureRefundCollection> $refundRepository
     */
    public function __construct(
        private readonly EntityRepository $orderTransactionRepository,
        private readonly PaymentMethodChannelResolver $channelResolver,
        private readonly OrderPaymentStarter $starter,
        private readonly PaymentOrderService $paymentOrderService,
        private readonly PaymentOrderReconciler $reconciler,
        private readonly PaymentRefundService $refundService,
        private readonly EntityRepository $refundRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Recurring payments are not supported: the gateway opens a payment with the payer present, the
     * channels that could charge without one are the sdk channels the storefront must not bind.
     */
    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return $type === PaymentHandlerType::REFUND;
    }

    /**
     * The checkout always hands the payer over to the channel, so the response is never null.
     */
    public function pay(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context,
        ?Struct $validateStruct
    ): RedirectResponse {
        $orderTransaction = $this->loadOrderTransaction($transaction->getOrderTransactionId(), $context);
        $order = $orderTransaction->getOrder();

        if (!$order instanceof OrderEntity) {
            throw CheckoutPaymentException::invalidOrder($orderTransaction->getOrderId());
        }

        $channel = $this->channelResolver->channelOf($orderTransaction->getPaymentMethodId(), $context);

        $started = $this->starter->start(
            $order,
            $orderTransaction,
            $channel,
            (string) $transaction->getReturnUrl()
        );

        return new RedirectResponse($this->starter->handOver($started['order'], $started['result']));
    }

    /**
     * The payer came back from the channel. The gateway is asked once about the payment, so a
     * notification that was lost or delayed still settles the transaction.
     */
    public function finalize(Request $request, PaymentTransactionStruct $transaction, Context $context): void
    {
        $paymentOrder = $this->paymentOrderService->findLatestByOrderTransaction($transaction->getOrderTransactionId());

        // not every checkout really opened a payment - a payment method may have been switched before
        // the order was placed - so there is nothing to finalize and nothing to complain about
        if (!$paymentOrder instanceof PaymentOrderEntity) {
            return;
        }

        try {
            $this->reconciler->reconcile($paymentOrder);
        } catch (\Throwable $exception) {
            // The reconciler talks to the channel synchronously, and a timeout or a broken connection
            // throws. Letting that reach the caller would make the payment processor mark the
            // transaction failed although the payment may well have succeeded - the worst outcome
            // there is. The payment stays open and the scheduled reconciliation settles it later.
            $this->logger->warning('The payment could not be reconciled after the payer returned from the channel.', [
                'paymentNo' => $paymentOrder->paymentNo,
                'orderTransactionId' => $transaction->getOrderTransactionId(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Refunds through the gateway, partial refunds included.
     */
    public function refund(RefundPaymentTransactionStruct $transaction, Context $context): void
    {
        $orderTransaction = $this->loadOrderTransaction($transaction->getOrderTransactionId(), $context);

        $paymentOrder = $this->paymentOrderService->findLatestByOrderTransaction($transaction->getOrderTransactionId());

        if (!$paymentOrder instanceof PaymentOrderEntity) {
            /** @phpstan-ignore shopwell.domainException (The error belongs to the payment gateway the checkout wraps.) */
            throw PaymentException::orderNotFound($transaction->getOrderTransactionId());
        }

        // the service resolves the credentials of the channel through the app, and the lookup of the
        // payment order loads it as an association
        $app = $paymentOrder->paymentApp;

        if (!$app instanceof PaymentAppEntity) {
            /** @phpstan-ignore shopwell.domainException (The error belongs to the payment gateway the checkout wraps.) */
            throw PaymentException::orderNotFound($paymentOrder->paymentNo);
        }

        $currency = $orderTransaction->getOrder()?->getCurrency();

        if (!$currency instanceof CurrencyEntity) {
            // the refund amount of the checkout is a price in the currency of the order, the gateway
            // only accepts the smallest unit - without the currency it cannot be converted
            /** @phpstan-ignore shopwell.domainException (The error belongs to the payment gateway the checkout wraps.) */
            throw PaymentException::missingParameter('currency');
        }

        $refund = $this->refundRepository
            ->search(new Criteria([$transaction->getRefundId()]), $context)
            ->getEntities()
            ->first();

        if (!$refund instanceof OrderTransactionCaptureRefundEntity) {
            throw CheckoutPaymentException::unknownRefund($transaction->getRefundId());
        }

        $this->refundService->refund($app, $paymentOrder, new PaymentRefundRequest(
            // the refund of the checkout is the idempotency key of the refund at the gateway
            outRefundNo: $transaction->getRefundId(),
            amount: $this->toSmallestUnit($refund->getAmount()->getTotalPrice(), $currency),
            reason: $refund->getReason()
        ));
    }

    /**
     * @throws CheckoutPaymentException when the transaction does not exist
     */
    private function loadOrderTransaction(string $orderTransactionId, Context $context): OrderTransactionEntity
    {
        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('order');
        // the gateway works in the smallest unit of the currency, which is the rounding of the order
        $criteria->addAssociation('order.currency');

        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->getEntities()->first();

        if (!$orderTransaction instanceof OrderTransactionEntity) {
            throw CheckoutPaymentException::invalidTransaction($orderTransactionId);
        }

        return $orderTransaction;
    }

    private function toSmallestUnit(float $totalPrice, CurrencyEntity $currency): int
    {
        return (int) round($totalPrice * (10 ** $currency->getItemRounding()->getDecimals()));
    }
}
