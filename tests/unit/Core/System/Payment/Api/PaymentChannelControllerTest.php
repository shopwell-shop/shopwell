<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\PaymentChannelController;
use Shopwell\Core\System\Payment\App\PaymentAppAuthenticator;
use Shopwell\Core\System\Payment\Exception\PaymentAppException;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentChannelController::class)]
class PaymentChannelControllerTest extends TestCase
{
    private const PATH = '/payment-api/v1/channels';

    public function testARequestWithoutCredentialsIsRejected(): void
    {
        $controller = $this->controller(PaymentControllerFixture::authenticator([]), $this->resolver());

        $this->expectExceptionObject(PaymentAppException::missingAuthParameters(['app_id']));

        $controller->list(new Request());
    }

    public function testAnAppWithoutAnyAuthorizationOnlyGetsAnEmptyList(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'secret-a');

        $controller = $this->controller(PaymentControllerFixture::authenticator([$app]), $this->resolver());

        $response = $controller->list(PaymentControllerFixture::signedRequest('client-a', 'secret-a', Request::METHOD_GET, self::PATH));

        static::assertSame(JsonResponse::HTTP_OK, $response->getStatusCode());
        static::assertSame(['channels' => []], $this->decode($response));
    }

    public function testTheChannelsEnabledForTheAppAreListed(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'secret-a');

        $providerRegistry = PaymentControllerFixture::providerRegistry(PaymentControllerFixture::provider('alipay', [PaymentControllerFixture::channel('alipay_h5', 'alipay')]));
        $resolver = PaymentControllerFixture::configResolver(
            $providerRegistry,
            PaymentControllerFixture::channelRegistry($providerRegistry),
            appAccounts: [PaymentControllerFixture::appAccount($app->id)],
            appChannels: [PaymentControllerFixture::appChannel($app->id)],
        );

        $controller = $this->controller(PaymentControllerFixture::authenticator([$app]), $resolver);

        $response = $controller->list(PaymentControllerFixture::signedRequest('client-a', 'secret-a', Request::METHOD_GET, self::PATH));

        $data = $this->decode($response);

        static::assertCount(1, $data['channels']);
        static::assertSame('alipay_h5', $data['channels'][0]['channel']);
        static::assertSame('alipay', $data['channels'][0]['provider']);
        static::assertSame(['redirect'], $data['channels'][0]['interactions']);
        static::assertArrayNotHasKey('source_app_id', $data['channels'][0]);
    }

    public function testAnInvalidSignatureIsRejected(): void
    {
        $app = PaymentControllerFixture::app('client-a', 'secret-a');

        $controller = $this->controller(PaymentControllerFixture::authenticator([$app]), $this->resolver());

        $request = PaymentControllerFixture::signedRequest('client-a', 'wrong-secret', Request::METHOD_GET, self::PATH);

        $this->expectExceptionObject(PaymentAppException::invalidSignature('client-a'));

        $controller->list($request);
    }

    private function controller(PaymentAppAuthenticator $authenticator, PaymentConfigResolver $configResolver): PaymentChannelController
    {
        return new PaymentChannelController($authenticator, $configResolver);
    }

    private function resolver(): PaymentConfigResolver
    {
        return PaymentControllerFixture::configResolver(PaymentControllerFixture::providerRegistry(), PaymentControllerFixture::channelRegistry(PaymentControllerFixture::providerRegistry()));
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(JsonResponse $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
