<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\Struct\PayParams;
use Shopwell\Core\System\Payment\Gateway\PayResult;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderPresenter;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundEntity;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentOrderPresenter::class)]
class PaymentOrderPresenterTest extends TestCase
{
    public function testOrderMapsEveryFieldAndFormatsTheDates(): void
    {
        $presenter = new PaymentOrderPresenter($this->stateRepository('paid'));

        $order = new PaymentOrderEntity();
        $order->id = 'order-id';
        $order->paymentNo = 'PG0001';
        $order->outTradeNo = 'app-trade-1';
        $order->paymentAppId = 'app-id';
        $order->providerTechnicalName = 'alipay';
        $order->channelTechnicalName = 'alipay_h5';
        $order->amount = 1000;
        $order->paidAmount = 1000;
        $order->refundedAmount = 250;
        $order->currency = 'CNY';
        $order->subject = 'An order';
        $order->attach = 'attach-data';
        $order->stateId = 'state-paid';
        $order->outPaymentNo = 'channel-trade-1';
        $order->expireAt = new \DateTimeImmutable('2024-01-01T12:00:00+00:00');
        $order->paidAt = new \DateTimeImmutable('2024-01-01T12:05:00+00:00');
        $order->closedAt = new \DateTimeImmutable('2024-01-01T12:10:00+00:00');

        $result = $presenter->order($order);

        static::assertSame('PG0001', $result['payment_no']);
        static::assertSame('app-trade-1', $result['out_trade_no']);
        static::assertSame('alipay', $result['provider']);
        static::assertSame('alipay_h5', $result['channel']);
        static::assertSame(1000, $result['amount']);
        static::assertSame(1000, $result['paid_amount']);
        static::assertSame(250, $result['refunded_amount']);
        static::assertSame('CNY', $result['currency']);
        static::assertSame('An order', $result['subject']);
        static::assertSame('attach-data', $result['attach']);
        static::assertSame('paid', $result['state']);
        static::assertSame('channel-trade-1', $result['out_payment_no']);
        static::assertSame('2024-01-01T12:00:00+00:00', $result['expire_at']);
        static::assertSame('2024-01-01T12:05:00+00:00', $result['paid_at']);
        static::assertSame('2024-01-01T12:10:00+00:00', $result['closed_at']);
        static::assertNull($result['pay']);
    }

    public function testOrderUsesTheResultOutPaymentNoAndPayParameters(): void
    {
        $presenter = new PaymentOrderPresenter($this->stateRepository('opened'));

        $order = new PaymentOrderEntity();
        $order->id = 'order-id';
        $order->paymentNo = 'PG0001';
        $order->outTradeNo = 'app-trade-1';
        $order->paymentAppId = 'app-id';
        $order->providerTechnicalName = 'alipay';
        $order->channelTechnicalName = 'alipay_h5';
        $order->amount = 1000;
        $order->currency = 'CNY';
        $order->subject = 'An order';
        $order->stateId = 'state-opened';
        $order->outPaymentNo = 'stale-trade';

        $params = new PayParams(PaymentFlow::REDIRECT, 'https://pay.example.test/redirect');
        $result = $presenter->order($order, new PayResult($params, 'fresh-trade'));

        static::assertSame('fresh-trade', $result['out_payment_no']);
        static::assertSame($params, $result['pay']);
    }

    public function testOrderKeepsTheOrderOutPaymentNoWhenTheResultDoesNotCarryOne(): void
    {
        $presenter = new PaymentOrderPresenter($this->stateRepository('opened'));

        $order = new PaymentOrderEntity();
        $order->id = 'order-id';
        $order->paymentNo = 'PG0001';
        $order->outTradeNo = 'app-trade-1';
        $order->paymentAppId = 'app-id';
        $order->providerTechnicalName = 'alipay';
        $order->channelTechnicalName = 'alipay_h5';
        $order->amount = 1000;
        $order->currency = 'CNY';
        $order->subject = 'An order';
        $order->stateId = 'state-opened';
        $order->outPaymentNo = 'existing-trade';

        $result = $presenter->order($order, new PayResult(new PayParams(PaymentFlow::REDIRECT)));

        static::assertSame('existing-trade', $result['out_payment_no']);
    }

    public function testOrderOmitsUnsetDatesAndResolvesTheStateToNullWhenItIsUnknown(): void
    {
        $presenter = new PaymentOrderPresenter($this->emptyStateRepository());

        $order = new PaymentOrderEntity();
        $order->id = 'order-id';
        $order->paymentNo = 'PG0002';
        $order->outTradeNo = 'app-trade-2';
        $order->paymentAppId = 'app-id';
        $order->providerTechnicalName = 'wechat';
        $order->channelTechnicalName = 'wechat_native';
        $order->amount = 500;
        $order->currency = 'CNY';
        $order->subject = 'Another order';
        $order->stateId = 'missing-state';

        $result = $presenter->order($order);

        static::assertNull($result['state']);
        static::assertNull($result['expire_at']);
        static::assertNull($result['paid_at']);
        static::assertNull($result['closed_at']);
        static::assertNull($result['out_payment_no']);
        static::assertNull($result['attach']);
    }

    public function testRefundMapsEveryFieldAndFormatsTheRefundedAt(): void
    {
        $presenter = new PaymentOrderPresenter($this->stateRepository('finished'));

        $refund = new PaymentRefundEntity();
        $refund->id = 'refund-id';
        $refund->refundNo = 'PR0001';
        $refund->paymentOrderId = 'order-id';
        $refund->outRefundNo = 'app-refund-1';
        $refund->amount = 250;
        $refund->currency = 'CNY';
        $refund->reason = 'Customer request';
        $refund->stateId = 'state-finished';
        $refund->outRefundId = 'channel-refund-1';
        $refund->refundedAt = new \DateTimeImmutable('2024-02-02T08:30:00+00:00');

        $result = $presenter->refund($refund);

        static::assertSame('PR0001', $result['refund_no']);
        static::assertSame('app-refund-1', $result['out_refund_no']);
        static::assertSame(250, $result['amount']);
        static::assertSame('CNY', $result['currency']);
        static::assertSame('Customer request', $result['reason']);
        static::assertSame('finished', $result['state']);
        static::assertSame('channel-refund-1', $result['out_refund_id']);
        static::assertSame('2024-02-02T08:30:00+00:00', $result['refunded_at']);
    }

    public function testRefundWithoutRefundedAtExposesNull(): void
    {
        $presenter = new PaymentOrderPresenter($this->emptyStateRepository());

        $refund = new PaymentRefundEntity();
        $refund->id = 'refund-id';
        $refund->refundNo = 'PR0002';
        $refund->paymentOrderId = 'order-id';
        $refund->outRefundNo = 'app-refund-2';
        $refund->amount = 100;
        $refund->currency = 'CNY';
        $refund->stateId = 'state-open';

        $result = $presenter->refund($refund);

        static::assertNull($result['state']);
        static::assertNull($result['reason']);
        static::assertNull($result['out_refund_id']);
        static::assertNull($result['refunded_at']);
    }

    public function testStateNameReturnsTheTechnicalNameOfTheState(): void
    {
        $presenter = new PaymentOrderPresenter($this->stateRepository('refunded_partially'));

        static::assertSame('refunded_partially', $presenter->stateName('state-id'));
    }

    public function testStateNameReturnsNullWhenNoStateMatches(): void
    {
        $presenter = new PaymentOrderPresenter($this->emptyStateRepository());

        static::assertNull($presenter->stateName('state-id'));
    }

    /**
     * @return EntityRepository<StateMachineStateCollection>
     */
    private function stateRepository(string $technicalName): EntityRepository
    {
        $state = new StateMachineStateEntity();
        $state->setId('state-id');
        $state->setTechnicalName($technicalName);

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            'state_machine_state',
            1,
            new StateMachineStateCollection([$state]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        return $repository;
    }

    /**
     * @return EntityRepository<StateMachineStateCollection>
     */
    private function emptyStateRepository(): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            'state_machine_state',
            0,
            new StateMachineStateCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        return $repository;
    }
}
