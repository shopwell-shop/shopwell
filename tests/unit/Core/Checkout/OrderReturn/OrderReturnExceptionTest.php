<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\OrderReturn;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * The codes are a contract: the storefront maps them to a text, so a renamed code breaks a message
 * without breaking a test that only looks at the status.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderReturnException::class)]
class OrderReturnExceptionTest extends TestCase
{
    /**
     * @return iterable<string, array{\Closure(): OrderReturnException, int, string}>
     */
    public static function exceptionProvider(): iterable
    {
        yield 'disabled' => [
            static fn (): OrderReturnException => OrderReturnException::disabled(),
            Response::HTTP_BAD_REQUEST,
            OrderReturnException::RETURN_DISABLED_CODE,
        ];

        yield 'not found' => [
            static fn (): OrderReturnException => OrderReturnException::notFound('return-id'),
            Response::HTTP_NOT_FOUND,
            OrderReturnException::RETURN_NOT_FOUND_CODE,
        ];

        yield 'order not found' => [
            static fn (): OrderReturnException => OrderReturnException::orderNotFound('SW-10001'),
            Response::HTTP_NOT_FOUND,
            OrderReturnException::RETURN_ORDER_NOT_FOUND_CODE,
        ];

        yield 'order not finished' => [
            static fn (): OrderReturnException => OrderReturnException::orderNotFinished('SW-10001'),
            Response::HTTP_BAD_REQUEST,
            OrderReturnException::RETURN_ORDER_NOT_FINISHED_CODE,
        ];

        yield 'window expired' => [
            static fn (): OrderReturnException => OrderReturnException::windowExpired('SW-10001', 30),
            Response::HTTP_BAD_REQUEST,
            OrderReturnException::RETURN_WINDOW_EXPIRED_CODE,
        ];

        yield 'invalid type' => [
            static fn (): OrderReturnException => OrderReturnException::invalidType('repair'),
            Response::HTTP_BAD_REQUEST,
            OrderReturnException::RETURN_INVALID_TYPE_CODE,
        ];

        yield 'empty positions' => [
            static fn (): OrderReturnException => OrderReturnException::emptyPositions(),
            Response::HTTP_BAD_REQUEST,
            OrderReturnException::RETURN_EMPTY_POSITIONS_CODE,
        ];

        yield 'line item unknown' => [
            static fn (): OrderReturnException => OrderReturnException::lineItemUnknown('line-item-id'),
            Response::HTTP_BAD_REQUEST,
            OrderReturnException::RETURN_LINE_ITEM_UNKNOWN_CODE,
        ];

        yield 'quantity exceeded' => [
            static fn (): OrderReturnException => OrderReturnException::quantityExceeded('line-item-id', 3, 1),
            Response::HTTP_BAD_REQUEST,
            OrderReturnException::RETURN_QUANTITY_EXCEEDED_CODE,
        ];

        yield 'reason unknown' => [
            static fn (): OrderReturnException => OrderReturnException::reasonUnknown('reason-id'),
            Response::HTTP_BAD_REQUEST,
            OrderReturnException::RETURN_REASON_UNKNOWN_CODE,
        ];

        yield 'guest access denied' => [
            static fn (): OrderReturnException => OrderReturnException::guestAccessDenied(),
            Response::HTTP_FORBIDDEN,
            OrderReturnException::RETURN_GUEST_ACCESS_DENIED_CODE,
        ];

        yield 'state not allowed' => [
            static fn (): OrderReturnException => OrderReturnException::stateNotAllowed('closed', 'approve'),
            Response::HTTP_BAD_REQUEST,
            OrderReturnException::RETURN_STATE_NOT_ALLOWED_CODE,
        ];

        yield 'refund not possible' => [
            static fn (): OrderReturnException => OrderReturnException::refundNotPossible('RT-1', 'no payment'),
            Response::HTTP_BAD_REQUEST,
            OrderReturnException::RETURN_REFUND_NOT_POSSIBLE_CODE,
        ];

        yield 'missing tracking code' => [
            static fn (): OrderReturnException => OrderReturnException::missingTrackingCode(),
            Response::HTTP_BAD_REQUEST,
            OrderReturnException::RETURN_MISSING_TRACKING_CODE_CODE,
        ];

        yield 'empty message' => [
            static fn (): OrderReturnException => OrderReturnException::emptyMessage(),
            Response::HTTP_BAD_REQUEST,
            OrderReturnException::RETURN_EMPTY_MESSAGE_CODE,
        ];
    }

    /**
     * @param \Closure(): OrderReturnException $factory
     */
    #[DataProvider('exceptionProvider')]
    public function testTheStatusAndTheCodeAreTheContract(\Closure $factory, int $status, string $code): void
    {
        $exception = $factory();

        static::assertSame($status, $exception->getStatusCode());
        static::assertSame($code, $exception->getErrorCode());
    }

    public function testAnAnonymousVisitorIsNotToldWhetherTheOrderExists(): void
    {
        $exception = OrderReturnException::orderNotFound('SW-10001');

        static::assertStringNotContainsString('SW-10001', $exception->getMessage());
        static::assertSame('No order matches the given number and email address.', $exception->getMessage());
    }

    public function testTheQuantitiesArePartOfTheMessage(): void
    {
        $exception = OrderReturnException::quantityExceeded('line-item-id', 3, 1);

        static::assertSame(
            'Only 1 of the line item "line-item-id" can still be returned, 3 were requested.',
            $exception->getMessage()
        );
    }
}
