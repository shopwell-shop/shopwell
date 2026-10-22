<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\OrderReturn\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnRouteResponse;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderReturnRouteResponse::class)]
class OrderReturnRouteResponseTest extends TestCase
{
    public function testTheAnswerIsOkByDefault(): void
    {
        $struct = new ArrayStruct(['orderReturn' => ['id' => 'return-id']]);

        $response = new OrderReturnRouteResponse($struct);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame($struct, $response->getResult());
        static::assertSame($struct, $response->getObject());
    }

    public function testASuccessfulCreationAnswersWithCreated(): void
    {
        $response = new OrderReturnRouteResponse(new ArrayStruct([]), Response::HTTP_CREATED);

        static::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    }
}
