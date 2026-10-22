<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\Gateway\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelEntity;
use Shopwell\Core\Checkout\Payment\Gateway\Api\PaymentMethodChannelController;
use Shopwell\Core\Checkout\Payment\Gateway\Api\PaymentMethodChannelSaveDto;
use Shopwell\Core\Checkout\Payment\Gateway\PaymentGatewayHandler;
use Shopwell\Core\Checkout\Payment\Gateway\PaymentMethodChannelBinder;
use Shopwell\Core\Checkout\Payment\PaymentException as CheckoutPaymentException;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigService;
use Shopwell\Tests\Unit\Core\Checkout\Payment\Gateway\PaymentGatewayFixture;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentMethodChannelController::class)]
class PaymentMethodChannelControllerTest extends TestCase
{
    private const PAYMENT_METHOD_ID = 'payment-method-id';

    private const OTHER_PAYMENT_METHOD_ID = 'other-payment-method-id';

    private const OTHER_APP_ID = 'other-app-id';

    public function testReadPresentsTheBindingOfTheMethod(): void
    {
        $controller = $this->controller(
            [$this->binding(self::PAYMENT_METHOD_ID)],
            [$this->paymentMethod('Alipay', PaymentGatewayHandler::class)]
        );

        $data = $this->decode($controller->read(self::PAYMENT_METHOD_ID, Context::createDefaultContext()));

        static::assertSame([
            'paymentAppId' => PaymentGatewayFixture::PLATFORM_APP_ID,
            'channelTechnicalName' => PaymentGatewayFixture::CHANNEL,
        ], $data['binding']);
        // the binding does not load the app, so the answer falls back to the account the shop collects on
        static::assertSame([
            'id' => PaymentGatewayFixture::PLATFORM_APP_ID,
            'name' => 'Shopfront',
            'technicalName' => PaymentAppEntity::PLATFORM_TECHNICAL_NAME,
            'type' => PaymentAppEntity::TYPE_PLATFORM,
        ], $data['paymentApp']);
        static::assertTrue($data['collectThroughGateway']);
        // the method itself does not block its own channel
        static::assertSame([], $data['taken']);
    }

    /**
     * An unbound method still answers with an account: the default is the tenant of the platform, which
     * is what the operator sees as a filled row instead of a choice with exactly one answer.
     */
    public function testReadPresentsAnUnboundMethodWithoutABinding(): void
    {
        $controller = $this->controller([], [$this->paymentMethod('Invoice')]);

        $data = $this->decode($controller->read(self::PAYMENT_METHOD_ID, Context::createDefaultContext()));

        static::assertNull($data['binding']);
        static::assertSame(PaymentGatewayFixture::PLATFORM_APP_ID, $data['paymentApp']['id']);
        // the administration tells the operator that saving is what makes the method collect at all
        static::assertFalse($data['collectThroughGateway']);
        static::assertSame([], $data['taken']);
    }

    public function testReadPresentsTheAppTheBindingCollectsOn(): void
    {
        $app = $this->externalApp();
        $binding = $this->binding(self::PAYMENT_METHOD_ID, $app->id);
        $binding->paymentApp = $app;

        $controller = $this->controller([$binding], [$this->paymentMethod('Alipay', PaymentGatewayHandler::class)]);

        $data = $this->decode($controller->read(self::PAYMENT_METHOD_ID, Context::createDefaultContext()));

        static::assertSame($app->id, $data['binding']['paymentAppId']);
        static::assertSame([
            'id' => $app->id,
            'name' => 'Other app',
            'technicalName' => 'other-app',
            'type' => PaymentAppEntity::TYPE_EXTERNAL,
        ], $data['paymentApp']);
    }

    public function testReadListsThePairsOfTheOtherMethodsAsTaken(): void
    {
        $takenBinding = $this->binding(self::OTHER_PAYMENT_METHOD_ID);
        $takenBinding->paymentMethod = $this->paymentMethod(
            'Alipay of the other method',
            PaymentGatewayHandler::class,
            self::OTHER_PAYMENT_METHOD_ID
        );
        $takenBinding->paymentApp = PaymentGatewayFixture::platformAppEntity();

        $controller = $this->controller([], [$this->paymentMethod('Invoice')], [$takenBinding]);

        $data = $this->decode($controller->read(self::PAYMENT_METHOD_ID, Context::createDefaultContext()));

        static::assertSame([[
            'paymentAppId' => PaymentGatewayFixture::PLATFORM_APP_ID,
            'paymentAppName' => 'Shopfront',
            'channelTechnicalName' => PaymentGatewayFixture::CHANNEL,
            'paymentMethodId' => self::OTHER_PAYMENT_METHOD_ID,
            'paymentMethodName' => 'Alipay of the other method',
        ]], $data['taken']);
    }

    /**
     * Saving is what turns a plain payment method into one that collects through the gateway, so the
     * answer has to describe the state after the write and not the one the request assumed.
     */
    public function testSaveBindsTheChannelAndAnswersTheStateAfterTheWrite(): void
    {
        $paymentMethod = $this->paymentMethod('Invoice');

        $controller = $this->controller(
            [],
            [$paymentMethod],
            handlerIsWritten: static function () use ($paymentMethod): void {
                $paymentMethod->setHandlerIdentifier(PaymentGatewayHandler::class);
            }
        );

        $data = $this->decode($controller->save(
            self::PAYMENT_METHOD_ID,
            new PaymentMethodChannelSaveDto(PaymentGatewayFixture::CHANNEL),
            Context::createDefaultContext()
        ));

        static::assertSame([
            'paymentAppId' => PaymentGatewayFixture::PLATFORM_APP_ID,
            'channelTechnicalName' => PaymentGatewayFixture::CHANNEL,
        ], $data['binding']);
        static::assertSame(PaymentGatewayFixture::PLATFORM_APP_ID, $data['paymentApp']['id']);
        static::assertTrue($data['collectThroughGateway']);
    }

    public function testSaveBindsTheAppTheOperatorChose(): void
    {
        $controller = $this->controller(
            [],
            [$this->paymentMethod('Invoice')],
            apps: [$this->externalApp()]
        );

        $data = $this->decode($controller->save(
            self::PAYMENT_METHOD_ID,
            new PaymentMethodChannelSaveDto(PaymentGatewayFixture::CHANNEL, self::OTHER_APP_ID),
            Context::createDefaultContext()
        ));

        static::assertSame(self::OTHER_APP_ID, $data['binding']['paymentAppId']);
        static::assertSame('Other app', $data['paymentApp']['name']);
    }

    public function testSaveRejectsAnUnknownPaymentMethod(): void
    {
        $controller = $this->controller([], []);

        $this->expectExceptionObject(CheckoutPaymentException::unknownPaymentMethodById(self::PAYMENT_METHOD_ID));

        $controller->save(
            self::PAYMENT_METHOD_ID,
            new PaymentMethodChannelSaveDto(PaymentGatewayFixture::CHANNEL),
            Context::createDefaultContext()
        );
    }

    public function testSaveRejectsAChannelAnotherMethodAlreadyCollectsThrough(): void
    {
        $takenBinding = $this->binding(self::OTHER_PAYMENT_METHOD_ID);
        $takenBinding->paymentMethod = $this->paymentMethod(
            'Alipay',
            PaymentGatewayHandler::class,
            self::OTHER_PAYMENT_METHOD_ID
        );

        $controller = $this->controller(
            [],
            [
                $this->paymentMethod('Invoice'),
                $this->paymentMethod('Alipay', PaymentGatewayHandler::class, self::OTHER_PAYMENT_METHOD_ID),
            ],
            [$takenBinding]
        );

        $this->expectExceptionObject(PaymentException::paymentMethodChannelTaken(
            'Shopfront',
            PaymentGatewayFixture::CHANNEL,
            self::OTHER_PAYMENT_METHOD_ID,
            'Alipay'
        ));

        $controller->save(
            self::PAYMENT_METHOD_ID,
            new PaymentMethodChannelSaveDto(PaymentGatewayFixture::CHANNEL),
            Context::createDefaultContext()
        );
    }

    /**
     * @param list<PaymentMethodGatewayChannelEntity> $ownBindings
     * @param list<PaymentMethodEntity> $paymentMethods
     * @param list<PaymentMethodGatewayChannelEntity> $takenBindings
     * @param (\Closure(): void)|null $handlerIsWritten
     * @param list<PaymentAppEntity> $apps
     */
    private function controller(
        array $ownBindings,
        array $paymentMethods,
        array $takenBindings = [],
        ?\Closure $handlerIsWritten = null,
        array $apps = [],
    ): PaymentMethodChannelController {
        // the store is mutable so the answer of a save describes what the save wrote
        $bindings = $ownBindings;
        $appPool = array_merge([PaymentGatewayFixture::platformAppEntity()], $apps);

        $bindingRepository = $this->repository(
            static function (Criteria $criteria) use (&$bindings, $takenBindings): array {
                return self::hasNotEqualsFilter($criteria) ? $takenBindings : $bindings;
            },
            null,
            static function (array $payload) use (&$bindings, $appPool): void {
                // the read after the write loads the app as an association, so the store does the same
                $bindings = [self::bindingFromPayload($payload[0], $appPool)];
            }
        );

        $paymentMethodRepository = $this->repository(
            static fn (Criteria $criteria): array => $criteria->getIds() === []
                ? $paymentMethods
                : array_values(array_filter(
                    $paymentMethods,
                    static fn (Entity $entity): bool => \in_array($entity->getUniqueIdentifier(), $criteria->getIds(), true)
                )),
            $handlerIsWritten
        );

        $appRepository = $this->repository(
            static function (Criteria $criteria) use ($apps): array {
                $all = array_merge([PaymentGatewayFixture::platformAppEntity()], $apps);
                $ids = $criteria->getIds();

                if ($ids === []) {
                    // the tenant of the platform is looked up by its technical name
                    return $all;
                }

                return array_values(array_filter(
                    $all,
                    static fn (Entity $entity): bool => \in_array($entity->getUniqueIdentifier(), $ids, true)
                ));
            }
        );

        $binder = new PaymentMethodChannelBinder(
            $bindingRepository,
            $paymentMethodRepository,
            $appRepository,
            PaymentGatewayFixture::channelRegistry(),
            new PaymentProviderConfigService(
                $this->repository(static fn (Criteria $criteria): array => [PaymentGatewayFixture::providerConfig()]),
                $this->repository(static fn (Criteria $criteria): array => [PaymentGatewayFixture::channelConfig()]),
                new PaymentCredentialCipher('unit-test-cipher-secret'),
            )
        );

        return new PaymentMethodChannelController($binder, $paymentMethodRepository);
    }

    /**
     * @param \Closure(Criteria): list<Entity> $resolve
     * @param (\Closure(): void)|null $onUpdate
     * @param (\Closure(array<int, array<string, mixed>>): void)|null $onUpsert
     *
     * @phpstan-ignore missingType.generics (The stub serves every collection type the caller asserts.)
     */
    private function repository(\Closure $resolve, ?\Closure $onUpdate = null, ?\Closure $onUpsert = null): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            static fn (Criteria $criteria): EntitySearchResult => PaymentGatewayFixture::searchResult($resolve($criteria))
        );

        if ($onUpdate instanceof \Closure) {
            $written = static::createStub(EntityWrittenContainerEvent::class);
            $repository->method('update')->willReturnCallback(static function () use ($onUpdate, $written): EntityWrittenContainerEvent {
                $onUpdate();

                return $written;
            });
        }

        if ($onUpsert instanceof \Closure) {
            $written = static::createStub(EntityWrittenContainerEvent::class);
            $repository->method('upsert')->willReturnCallback(static function (array $payload) use ($onUpsert, $written): EntityWrittenContainerEvent {
                $onUpsert($payload);

                return $written;
            });
        }

        return $repository;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<PaymentAppEntity> $appPool
     */
    private static function bindingFromPayload(array $payload, array $appPool = []): PaymentMethodGatewayChannelEntity
    {
        $binding = new PaymentMethodGatewayChannelEntity();
        $binding->id = (string) $payload['id'];
        $binding->setUniqueIdentifier($binding->id);
        $binding->paymentMethodId = (string) $payload['paymentMethodId'];
        $binding->paymentAppId = (string) $payload['paymentAppId'];
        $binding->channelTechnicalName = (string) $payload['channelTechnicalName'];

        foreach ($appPool as $app) {
            if ($app->id === $binding->paymentAppId) {
                $binding->paymentApp = $app;

                break;
            }
        }

        return $binding;
    }

    private static function hasNotEqualsFilter(Criteria $criteria): bool
    {
        foreach ($criteria->getFilters() as $filter) {
            if ($filter instanceof NotEqualsFilter) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(JsonResponse $response): array
    {
        $content = $response->getContent();
        static::assertIsString($content);

        $decoded = json_decode($content, true);
        static::assertIsArray($decoded);

        return $decoded;
    }

    private function paymentMethod(
        string $name,
        string $handlerIdentifier = 'Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\InvoicePayment',
        string $id = self::PAYMENT_METHOD_ID,
    ): PaymentMethodEntity {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId($id);
        $paymentMethod->setUniqueIdentifier($id);
        $paymentMethod->setHandlerIdentifier($handlerIdentifier);
        $paymentMethod->setName($name);

        return $paymentMethod;
    }

    private function binding(
        string $paymentMethodId,
        string $paymentAppId = PaymentGatewayFixture::PLATFORM_APP_ID,
    ): PaymentMethodGatewayChannelEntity {
        $binding = new PaymentMethodGatewayChannelEntity();
        $binding->id = 'binding-of-' . $paymentMethodId;
        $binding->setUniqueIdentifier($binding->id);
        $binding->paymentMethodId = $paymentMethodId;
        $binding->paymentAppId = $paymentAppId;
        $binding->channelTechnicalName = PaymentGatewayFixture::CHANNEL;

        return $binding;
    }

    private function externalApp(string $id = self::OTHER_APP_ID): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->id = $id;
        $app->setUniqueIdentifier($id);
        $app->technicalName = 'other-app';
        $app->name = 'Other app';
        $app->type = PaymentAppEntity::TYPE_EXTERNAL;
        $app->active = true;
        $app->appSecret = 'secret';
        $app->settings = [];

        return $app;
    }
}
