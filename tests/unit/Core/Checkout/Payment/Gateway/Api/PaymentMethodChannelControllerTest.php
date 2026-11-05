<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\Gateway\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelCollection;
use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelEntity;
use Shopwell\Core\Checkout\Payment\Gateway\Api\PaymentMethodChannelController;
use Shopwell\Core\Checkout\Payment\Gateway\Api\PaymentMethodChannelSaveDto;
use Shopwell\Core\Checkout\Payment\Gateway\PaymentGatewayHandler;
use Shopwell\Core\Checkout\Payment\Gateway\PaymentMethodChannelBinder;
use Shopwell\Core\Checkout\Payment\PaymentException;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Method\PaymentMethod;
use Shopwell\Core\System\Payment\Method\PaymentMethodRegistry;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Tests\Unit\Core\Checkout\Payment\Gateway\PaymentGatewayFixture;
use Shopwell\Tests\Unit\Core\System\Payment\Api\PaymentControllerFixture;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentMethodChannelController::class)]
class PaymentMethodChannelControllerTest extends TestCase
{
    public function testReadPresentsTheLogicalBindingAndAvailableApps(): void
    {
        $app = PaymentGatewayFixture::platformAppEntity();
        $binding = $this->binding($app);
        $data = $this->decode($this->controller($app, $binding)->read('checkout-method', Context::createDefaultContext()));

        static::assertSame(['paymentAppId' => $app->id, 'methodTechnicalName' => 'alipay', 'routingPolicy' => 'priority'], $data['binding']);
        static::assertSame($app->id, $data['paymentApp']['id']);
        static::assertTrue($data['collectThroughGateway']);
        static::assertSame([], $data['taken']);
        static::assertSame($app->id, $data['apps'][0]['id']);
    }

    public function testUnboundMethodUsesThePlatformAppAsItsDefault(): void
    {
        $app = PaymentGatewayFixture::platformAppEntity();
        $data = $this->decode($this->controller($app)->read('checkout-method', Context::createDefaultContext()));
        static::assertNull($data['binding']);
        static::assertSame($app->id, $data['paymentApp']['id']);
    }

    public function testSelectableMetadataNeverExposesMerchantAccountsOrCredentials(): void
    {
        $app = PaymentControllerFixture::app('merchant-app', 'private-app-secret');
        $grant = PaymentControllerFixture::appAccount($app->id, credentials: ['app_id' => 'private-merchant-id', 'private_key' => 'private-merchant-key']);
        $app->accounts = [$grant->accountId => $grant];
        $app->channels = ['alipay_h5' => PaymentControllerFixture::appChannel($app->id)];
        $app->allowedIps = ['192.0.2.1'];
        $app->settings = ['private-setting' => 'hidden'];
        $data = $this->decode($this->controller($app, $this->binding($app))->read('checkout-method', Context::createDefaultContext()));

        static::assertSame([[
            'id' => $app->id,
            'name' => $app->name,
            'technicalName' => $app->technicalName,
            'type' => $app->type,
            'active' => true,
            'methods' => [['technicalName' => 'alipay', 'translationKey' => 'payment.alipay']],
        ]], $data['apps']);
        $json = json_encode($data, \JSON_THROW_ON_ERROR);
        foreach (['private-app-secret', 'private-merchant-id', 'private-merchant-key', 'private-setting', 'ownerAppId', 'accountId', 'credentials', 'allowedIps'] as $privateValue) {
            static::assertStringNotContainsString($privateValue, $json);
        }
    }

    public function testReadListsConflictingLogicalMethods(): void
    {
        $app = PaymentGatewayFixture::platformAppEntity();
        $taken = $this->binding($app, 'other-checkout-method');
        $otherMethod = new PaymentMethodEntity();
        $otherMethod->setId('other-checkout-method');
        $otherMethod->setName('Other checkout method');
        $taken->paymentMethod = $otherMethod;
        $data = $this->decode($this->controller($app, taken: [$taken])->read('checkout-method', Context::createDefaultContext()));
        static::assertSame([[
            'paymentAppId' => $app->id,
            'paymentAppName' => $app->name,
            'methodTechnicalName' => 'alipay',
            'paymentMethodId' => 'other-checkout-method',
            'paymentMethodName' => 'Other checkout method',
        ]], $data['taken']);
    }

    public function testSaveReturnsTheStoredBindingAndSelectableMetadata(): void
    {
        $app = PaymentGatewayFixture::platformAppEntity();
        $controller = $this->controller($app, $this->binding($app));
        $data = $this->decode($controller->save('checkout-method', new PaymentMethodChannelSaveDto('alipay', $app->id), Context::createDefaultContext()));
        static::assertSame(['paymentAppId' => $app->id, 'methodTechnicalName' => 'alipay', 'routingPolicy' => 'priority'], $data['binding']);
        static::assertSame($app->id, $data['apps'][0]['id']);
    }

    public function testReadRejectsAnUnknownCheckoutMethod(): void
    {
        $this->expectExceptionObject(PaymentException::unknownPaymentMethodById('missing'));
        $this->controller(PaymentGatewayFixture::platformAppEntity(), knownMethod: false)->read('missing', Context::createDefaultContext());
    }

    public function testSaveRejectsAnUnknownCheckoutMethod(): void
    {
        $this->expectExceptionObject(PaymentException::unknownPaymentMethodById('missing'));
        $this->controller(PaymentGatewayFixture::platformAppEntity(), knownMethod: false)->save('missing', new PaymentMethodChannelSaveDto('alipay'), Context::createDefaultContext());
    }

    /**
     * @param list<PaymentMethodGatewayChannelEntity> $taken
     */
    private function controller(PaymentAppEntity $app, ?PaymentMethodGatewayChannelEntity $binding = null, array $taken = [], bool $knownMethod = true): PaymentMethodChannelController
    {
        $methods = [];
        if ($knownMethod) {
            $method = new PaymentMethodEntity();
            $method->setId('checkout-method');
            $method->setHandlerIdentifier(PaymentGatewayHandler::class);
            $methods[] = $method;
        }
        $bindings = StaticEntityRepository::of(PaymentMethodGatewayChannelCollection::class, array_fill(0, 16, static function (Criteria $criteria) use ($binding, $taken): PaymentMethodGatewayChannelCollection {
            foreach ($criteria->getFilters() as $filter) {
                if ($filter instanceof NotEqualsFilter) {
                    return new PaymentMethodGatewayChannelCollection($taken);
                }
            }

            return new PaymentMethodGatewayChannelCollection($binding === null ? [] : [$binding]);
        }));
        $methodRepository = new StaticEntityRepository(array_fill(0, 16, new PaymentMethodCollection($methods)));
        $apps = new StaticEntityRepository(array_fill(0, 16, new PaymentAppCollection([$app])));
        $binder = new PaymentMethodChannelBinder($bindings, $methodRepository, $apps, new PaymentMethodRegistry([new PaymentMethod('alipay', 'payment.alipay')]), PaymentGatewayFixture::channelRegistry());

        return new PaymentMethodChannelController($binder, $methodRepository);
    }

    private function binding(PaymentAppEntity $app, string $methodId = 'checkout-method'): PaymentMethodGatewayChannelEntity
    {
        $binding = new PaymentMethodGatewayChannelEntity();
        $binding->id = 'binding-' . $methodId;
        $binding->setUniqueIdentifier($binding->id);
        $binding->paymentMethodId = $methodId;
        $binding->paymentAppId = $app->id;
        $binding->paymentApp = $app;
        $binding->methodTechnicalName = 'alipay';
        $binding->routingPolicy = 'priority';

        return $binding;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(JsonResponse $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
