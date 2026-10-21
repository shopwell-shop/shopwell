<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\OrderReturn\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\OrderReturn\Api\OrderReturnController;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnPresenter;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnService;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A decision of a merchant is not a written state id: it runs through the service. The controller
 * only translates the request into that call and answers with the return in its new shape, so the
 * administration does not have to reload the row.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderReturnController::class)]
class OrderReturnControllerTest extends TestCase
{
    public function testADecisionAnswersWithTheReturnInItsNewShape(): void
    {
        $orderReturn = $this->orderReturn();
        $context = Context::createDefaultContext();

        $service = $this->createMock(OrderReturnService::class);
        $service->expects($this->once())
            ->method('approve')
            ->with('return-id', $context)
            ->willReturn($orderReturn);

        $response = $this->controller($service)->approve('return-id', $context);

        static::assertSame(
            ['id' => 'return-id', 'returnNumber' => 'RT-1'],
            $this->json($response)['orderReturn']
        );
    }

    public function testADeclineCarriesTheReasonAndTheAdministrator(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('administrator-id'));

        $service = $this->createMock(OrderReturnService::class);
        $service->expects($this->once())
            ->method('decline')
            ->with('return-id', 'the box was empty', $context, 'administrator-id')
            ->willReturn($this->orderReturn());

        $this->controller($service)->decline(
            'return-id',
            new Request(request: ['reason' => '  the box was empty  ']),
            $context
        );
    }

    public function testAShopWithoutAnAdministratorPassesNone(): void
    {
        $context = Context::createDefaultContext();

        $service = $this->createMock(OrderReturnService::class);
        $service->expects($this->once())
            ->method('decline')
            ->with('return-id', '', $context, null)
            ->willReturn($this->orderReturn());

        $this->controller($service)->decline('return-id', new Request(), $context);
    }

    public function testACancelCanCarryANote(): void
    {
        $context = Context::createDefaultContext();

        $service = $this->createMock(OrderReturnService::class);
        $service->expects($this->once())
            ->method('cancel')
            ->with('return-id', $context, 'the customer called')
            ->willReturn($this->orderReturn());

        $this->controller($service)->cancel('return-id', new Request(request: ['note' => 'the customer called']), $context);
    }

    public function testACancelWithoutANotePassesNone(): void
    {
        $context = Context::createDefaultContext();

        $service = $this->createMock(OrderReturnService::class);
        $service->expects($this->once())
            ->method('cancel')
            ->with('return-id', $context, null)
            ->willReturn($this->orderReturn());

        $this->controller($service)->cancel('return-id', new Request(request: ['note' => 42]), $context);
    }

    public function testTheReceptionOfTheGoodsGoesThroughTheService(): void
    {
        $context = Context::createDefaultContext();

        $service = $this->createMock(OrderReturnService::class);
        $service->expects($this->once())
            ->method('receive')
            ->with('return-id', $context)
            ->willReturn($this->orderReturn());

        static::assertSame(Response::HTTP_OK, $this->controller($service)->receive('return-id', $context)->getStatusCode());
    }

    public function testThePayoutGoesThroughTheService(): void
    {
        $context = Context::createDefaultContext();

        $service = $this->createMock(OrderReturnService::class);
        $service->expects($this->once())
            ->method('refund')
            ->with('return-id', $context)
            ->willReturn($this->orderReturn());

        static::assertSame(Response::HTTP_OK, $this->controller($service)->refund('return-id', $context)->getStatusCode());
    }

    public function testAMessageOfAMerchantIsWrittenAsTheAgent(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('administrator-id'));
        $orderReturn = $this->orderReturn();

        $service = $this->createMock(OrderReturnService::class);
        $service->expects($this->once())
            ->method('addMessage')
            ->with(
                'return-id',
                'we sent it back',
                OrderReturnEntity::AUTHOR_AGENT,
                'administrator-id',
                null,
                $context
            );
        $service->expects($this->once())
            ->method('get')
            ->with('return-id', $context)
            ->willReturn($orderReturn);

        $this->controller($service)->message(
            'return-id',
            new Request(request: ['content' => ' we sent it back ']),
            $context
        );
    }

    private function controller(OrderReturnService $service): OrderReturnController
    {
        $presenter = static::createStub(OrderReturnPresenter::class);
        $presenter->method('present')->willReturnCallback(
            static fn (OrderReturnEntity $orderReturn): array => [
                'id' => $orderReturn->id,
                'returnNumber' => $orderReturn->returnNumber,
            ]
        );

        return new OrderReturnController($service, $presenter);
    }

    private function orderReturn(): OrderReturnEntity
    {
        return (new OrderReturnEntity())->assign([
            'id' => 'return-id',
            'returnNumber' => 'RT-1',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Response $response): array
    {
        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();

        static::assertIsString($content);

        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }
}
