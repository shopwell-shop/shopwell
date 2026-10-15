<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\PaymentRefundController;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundCollection;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentRefundController::class)]
class PaymentRefundControllerTest extends TestCase
{
    private const PATH = '/payment-api/v1/payment-orders/PG-1/refunds';

    public function testARefundWithoutCredentialsIsRejected(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'secret-a');

        $this->expectExceptionObject(PaymentException::missingAuthParameters(['app_id']));

        $this->controller($app, PaymentControllerFixture::repository(PaymentOrderCollection::class), PaymentControllerFixture::repository(PaymentRefundCollection::class))
            ->create(new Request(), 'PG-1');
    }

    public function testARefundOfAnUnknownOrderIsNotFound(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'secret-a');

        $this->expectExceptionObject(PaymentException::orderNotFound('PG-1'));

        $this->controller($app, PaymentControllerFixture::repository(PaymentOrderCollection::class), PaymentControllerFixture::repository(PaymentRefundCollection::class))
            ->create($this->refundRequest(), 'PG-1');
    }

    public function testARefundOfAnUnpaidOrderIsRejected(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'secret-a');
        $order = PaymentControllerFixture::order($app->id, ['paidAt' => null]);

        $this->expectExceptionObject(PaymentException::refundNotAllowed('PG-1'));

        $this->controller($app, PaymentControllerFixture::repository(PaymentOrderCollection::class, [$order]), PaymentControllerFixture::repository(PaymentRefundCollection::class))
            ->create($this->refundRequest(), 'PG-1');
    }

    public function testARefundWithoutAnOutRefundNoIsRejected(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'secret-a');
        $order = PaymentControllerFixture::order($app->id, ['paidAt' => PaymentControllerFixture::clock()->now(), 'paidAmount' => 1000]);

        $request = PaymentControllerFixture::signedRequest('client-a', 'secret-a', Request::METHOD_POST, self::PATH, [
            'amount' => 500,
        ]);

        $this->expectExceptionObject(PaymentException::missingParameter('out_refund_no'));

        $this->controller($app, PaymentControllerFixture::repository(PaymentOrderCollection::class, [$order]), PaymentControllerFixture::repository(PaymentRefundCollection::class))
            ->create($request, 'PG-1');
    }

    public function testANonPositiveRefundAmountIsRejected(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'secret-a');
        $order = PaymentControllerFixture::order($app->id, ['paidAt' => PaymentControllerFixture::clock()->now(), 'paidAmount' => 1000]);

        $this->expectExceptionObject(PaymentException::invalidAmount(0));

        $this->controller($app, PaymentControllerFixture::repository(PaymentOrderCollection::class, [$order]), PaymentControllerFixture::repository(PaymentRefundCollection::class))
            ->create($this->refundRequest(0), 'PG-1');
    }

    public function testARefundThatExceedsThePaidAmountIsRejected(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'secret-a');
        $order = PaymentControllerFixture::order($app->id, ['paidAt' => PaymentControllerFixture::clock()->now(), 'paidAmount' => 1000]);

        $this->expectExceptionObject(PaymentException::refundAmountExceedsOrder('PG-1', 1500, 1000));

        $this->controller($app, PaymentControllerFixture::repository(PaymentOrderCollection::class, [$order]), PaymentControllerFixture::repository(PaymentRefundCollection::class))
            ->create($this->refundRequest(1500), 'PG-1');
    }

    public function testARefundThatAlreadyExistsIsAnsweredAsCreated(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'secret-a');
        $order = PaymentControllerFixture::order($app->id, ['paidAt' => PaymentControllerFixture::clock()->now(), 'paidAmount' => 1000]);
        $refund = PaymentControllerFixture::refund($order->id);

        $controller = $this->controller(
            $app,
            PaymentControllerFixture::repository(PaymentOrderCollection::class, [$order]),
            PaymentControllerFixture::repository(PaymentRefundCollection::class, [$refund]),
            ['finished'],
        );

        $response = $controller->create($this->refundRequest(500), 'PG-1');

        static::assertSame(JsonResponse::HTTP_CREATED, $response->getStatusCode());

        $data = $this->decode($response);

        static::assertSame('PR-1', $data['refund_no']);
        static::assertSame('R-1', $data['out_refund_no']);
        static::assertSame(500, $data['amount']);
        static::assertSame('CNY', $data['currency']);
        static::assertSame('finished', $data['state']);
    }

    private function refundRequest(int $amount = 500): Request
    {
        return PaymentControllerFixture::signedRequest('client-a', 'secret-a', Request::METHOD_POST, self::PATH, [
            'out_refund_no' => 'R-1',
            'amount' => $amount,
            'reason' => 'customer request',
        ]);
    }

    /**
     * @param list<string> $states
     * @param EntityRepository<PaymentOrderCollection> $orderRepository
     * @param EntityRepository<PaymentRefundCollection> $refundRepository
     */
    private function controller(
        PaymentAppEntity $app,
        EntityRepository $orderRepository,
        EntityRepository $refundRepository,
        array $states = []
    ): PaymentRefundController {
        $providers = PaymentControllerFixture::providerRegistry(PaymentControllerFixture::provider('alipay', [PaymentControllerFixture::channel('alipay_h5', 'alipay')]));
        $resolver = PaymentControllerFixture::configResolver($providers, PaymentControllerFixture::channelRegistry($providers));
        $orderService = PaymentControllerFixture::orderService($orderRepository, $resolver);

        return new PaymentRefundController(
            PaymentControllerFixture::authenticator([$app]),
            $orderService,
            PaymentControllerFixture::refundService($refundRepository, $resolver, $orderService),
            PaymentControllerFixture::presenter(array_map(PaymentControllerFixture::stateName(...), $states)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(JsonResponse $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
