<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\OrderReturn\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnLineItem\OrderReturnLineItemEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnMessage\OrderReturnMessageEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnReason\OrderReturnReasonEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnStates;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnPresenter;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Currency\CurrencyEntity;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateTranslationEntity;

/**
 * The storefront switches on the technical name and prints the label, so both are handed out. The
 * amount stays in the smallest unit of the currency and the decimals travel with it - a template that
 * has to guess them prints the wrong number for a currency without cents.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderReturnPresenter::class)]
class OrderReturnPresenterTest extends TestCase
{
    public function testAReturnIsPresentedWithEverythingTheStorefrontRenders(): void
    {
        $presented = (new OrderReturnPresenter())->present($this->orderReturn());

        static::assertSame('return-id', $presented['id']);
        static::assertSame('RT2026102109301500ABCD', $presented['returnNumber']);
        static::assertSame('SW-10001', $presented['orderNumber']);
        static::assertSame('return_refund', $presented['type']);
        static::assertSame(OrderReturnStates::STATE_OPEN, $presented['state']);
        static::assertSame('Waiting', $presented['stateLabel']);
        static::assertSame(19900, $presented['refundAmount']);
        static::assertSame('CNY', $presented['currency']);
        static::assertSame('broken', $presented['reasonLabel']);
        static::assertSame(['https://example.com/photo.jpg'], $presented['evidence']);
        static::assertSame('SF-1', $presented['trackingCode']);
        static::assertSame(['id' => 'message-id', 'authorRole' => 'customer', 'content' => 'please help', 'attachments' => [], 'createdAt' => null], $presented['messages'][0]);
    }

    public function testTheDecimalsComeWithTheAmount(): void
    {
        $presented = (new OrderReturnPresenter())->present($this->orderReturn());

        static::assertSame(2, $presented['decimals']);
    }

    public function testWithoutTheCurrencyTheDecimalsFallBackToTwo(): void
    {
        $orderReturn = $this->minimalReturn();
        $orderReturn->order = (new OrderEntity())->assign(['id' => 'order-id']);

        static::assertSame(2, (new OrderReturnPresenter())->present($orderReturn)['decimals']);
    }

    public function testWithoutTheOrderTheNumberStaysEmpty(): void
    {
        static::assertNull((new OrderReturnPresenter())->present($this->minimalReturn())['orderNumber']);
    }

    public function testAReturnWithoutLinesIsPresentedWithoutLines(): void
    {
        $presented = (new OrderReturnPresenter())->present($this->minimalReturn());

        static::assertSame([], $presented['lineItems']);
        static::assertSame([], $presented['messages']);
        static::assertSame([], $presented['evidence']);
    }

    public function testALineItemCarriesTheLabelOfTheOrderedProduct(): void
    {
        $presented = (new OrderReturnPresenter())->present($this->orderReturn());

        static::assertSame([
            'id' => 'return-line-item-id',
            'orderLineItemId' => 'line-item-id',
            'label' => 'Portable speaker',
            'quantity' => 2,
            'refundAmount' => 19900,
            'accepted' => true,
            'restocked' => false,
        ], $presented['lineItems'][0]);
    }

    public function testAListIsPresentedOneByOne(): void
    {
        $presenter = new OrderReturnPresenter();

        $presented = $presenter->presentList([$this->orderReturn(), $this->orderReturn()]);

        static::assertCount(2, $presented);
        static::assertSame('RT2026102109301500ABCD', $presented[1]['returnNumber']);
    }

    private function minimalReturn(): OrderReturnEntity
    {
        return (new OrderReturnEntity())->assign([
            'id' => 'return-id',
            'returnNumber' => 'RT2026102109301500ABCD',
            'orderId' => 'order-id',
            'type' => 'return_refund',
            'refundAmount' => 0,
            'currency' => 'CNY',
        ]);
    }

    private function orderReturn(): OrderReturnEntity
    {
        $state = (new StateMachineStateEntity())->assign([
            'id' => Uuid::randomHex(),
            'technicalName' => OrderReturnStates::STATE_OPEN,
            'translations' => [
                'en-GB' => (new StateMachineStateTranslationEntity())->assign(['name' => 'Waiting']),
            ],
        ]);
        $state->setTranslated(['name' => 'Waiting']);

        $reason = (new OrderReturnReasonEntity())->assign(['id' => 'reason-id']);
        $reason->setTranslated(['label' => 'broken']);

        $orderLineItem = (new OrderLineItemEntity())->assign(['id' => 'line-item-id', 'label' => 'Portable speaker']);

        $returnLineItem = new OrderReturnLineItemEntity();
        $returnLineItem->id = 'return-line-item-id';
        $returnLineItem->orderReturnId = 'return-id';
        $returnLineItem->orderLineItemId = 'line-item-id';
        $returnLineItem->quantity = 2;
        $returnLineItem->refundAmount = 19900;
        $returnLineItem->accepted = true;
        $returnLineItem->restocked = false;
        $returnLineItem->orderLineItem = $orderLineItem;

        $message = new OrderReturnMessageEntity();
        $message->id = 'message-id';
        $message->orderReturnId = 'return-id';
        $message->authorRole = 'customer';
        $message->content = 'please help';

        $currency = (new CurrencyEntity())->assign([
            'id' => 'currency-id',
            'isoCode' => 'CNY',
        ]);
        $currency->setItemRounding(new CashRoundingConfig(2, 0.01, true));

        $order = (new OrderEntity())->assign([
            'id' => 'order-id',
            'orderNumber' => 'SW-10001',
            'currency' => $currency,
        ]);

        return (new OrderReturnEntity())->assign([
            'id' => 'return-id',
            'returnNumber' => 'RT2026102109301500ABCD',
            'orderId' => 'order-id',
            'order' => $order,
            'type' => 'return_refund',
            'stateMachineState' => $state,
            'refundAmount' => 19900,
            'currency' => 'CNY',
            'reasonId' => 'reason-id',
            'reason' => $reason,
            'evidence' => ['https://example.com/photo.jpg'],
            'trackingCode' => 'SF-1',
            'lineItems' => [$returnLineItem],
            'messages' => [$message],
        ]);
    }
}
