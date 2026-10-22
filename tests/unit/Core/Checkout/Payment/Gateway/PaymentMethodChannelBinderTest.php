<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelCollection;
use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelEntity;
use Shopwell\Core\Checkout\Payment\Gateway\PaymentGatewayHandler;
use Shopwell\Core\Checkout\Payment\Gateway\PaymentMethodChannelBinder;
use Shopwell\Core\Checkout\Payment\PaymentException as CheckoutPaymentException;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigService;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentMethodChannelBinder::class)]
class PaymentMethodChannelBinderTest extends TestCase
{
    private const PAYMENT_METHOD_ID = 'payment-method-id';

    private const OTHER_PAYMENT_METHOD_ID = 'other-payment-method-id';

    private const OTHER_APP_ID = 'other-app-id';

    public function testBindingReturnsNullWhenTheMethodIsNotBoundYet(): void
    {
        $binder = $this->binder($this->repository());

        static::assertNull($binder->binding(self::PAYMENT_METHOD_ID, Context::createDefaultContext()));
    }

    public function testBindingReturnsTheStoredBinding(): void
    {
        $binding = $this->binding(self::PAYMENT_METHOD_ID);

        $binder = $this->binder($this->repository([$binding]));

        static::assertSame($binding, $binder->binding(self::PAYMENT_METHOD_ID, Context::createDefaultContext()));
    }

    /**
     * An unbound method is shown with the account the shop's own checkout collects on, so the operator
     * sees a filled row instead of a choice that has exactly one answer.
     */
    public function testAppOfAnswersThePlatformTenantWhenTheMethodIsNotBound(): void
    {
        $binder = $this->binder($this->repository(), $this->paymentMethodRepository(), $this->appRepository());

        $app = $binder->appOf(null, Context::createDefaultContext());

        static::assertSame(PaymentGatewayFixture::PLATFORM_APP_ID, $app->id);
        static::assertSame(PaymentAppEntity::PLATFORM_TECHNICAL_NAME, $app->technicalName);
    }

    public function testAppOfAnswersTheAppOfTheBinding(): void
    {
        $app = $this->externalApp();
        $binding = $this->binding(self::PAYMENT_METHOD_ID, $app->id);
        $binding->paymentApp = $app;

        $binder = $this->binder($this->repository(), $this->paymentMethodRepository(), $this->appRepository([$app]));

        static::assertSame($app, $binder->appOf($binding, Context::createDefaultContext()));
    }

    /**
     * The operator picks a channel, and the method has to collect through it afterwards - which is two
     * writes: the binding row, and the gateway handler on the payment method.
     */
    public function testBindStoresTheBindingAndPutsTheGatewayHandlerOnTheMethod(): void
    {
        $platformApp = PaymentGatewayFixture::platformAppEntity();

        $bindingRepository = $this->createMock(EntityRepository::class);
        $bindingRepository->method('search')->willReturn($this->searchResult());
        $bindingRepository->expects($this->once())
            ->method('upsert')
            ->with(static::callback(static function (array $payload): bool {
                static::assertCount(1, $payload);
                static::assertSame(self::PAYMENT_METHOD_ID, $payload[0]['paymentMethodId']);
                static::assertSame(PaymentGatewayFixture::PLATFORM_APP_ID, $payload[0]['paymentAppId']);
                static::assertSame(PaymentGatewayFixture::CHANNEL, $payload[0]['channelTechnicalName']);
                static::assertIsString($payload[0]['id']);

                return true;
            }));

        $paymentMethodRepository = $this->createMock(EntityRepository::class);
        $paymentMethodRepository->method('search')->willReturn($this->searchResult([$this->paymentMethod('Invoice')]));
        $paymentMethodRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $payload): bool {
                static::assertSame(self::PAYMENT_METHOD_ID, $payload[0]['id']);
                static::assertSame(PaymentGatewayHandler::class, $payload[0]['handlerIdentifier']);

                return true;
            }));

        $binder = $this->binder(
            $bindingRepository,
            $paymentMethodRepository,
            $this->repository([$platformApp])
        );

        $binding = $binder->bind(
            self::PAYMENT_METHOD_ID,
            PaymentGatewayFixture::CHANNEL,
            $platformApp->id,
            Context::createDefaultContext()
        );

        static::assertSame(self::PAYMENT_METHOD_ID, $binding->paymentMethodId);
        static::assertSame($platformApp->id, $binding->paymentAppId);
        static::assertSame(PaymentGatewayFixture::CHANNEL, $binding->channelTechnicalName);
    }

    /**
     * A single account needs no choice: without an app the tenant of the platform is the one that
     * collects, and that is the account the shop's checkout has always used.
     */
    public function testBindFallsBackToThePlatformTenantWhenNoAppWasSent(): void
    {
        $bindingRepository = $this->createMock(EntityRepository::class);
        $bindingRepository->method('search')->willReturn($this->searchResult());
        $bindingRepository->expects($this->once())
            ->method('upsert')
            ->with(static::callback(static function (array $payload): bool {
                static::assertSame(PaymentGatewayFixture::PLATFORM_APP_ID, $payload[0]['paymentAppId']);

                return true;
            }));

        $binder = $this->binder($bindingRepository, $this->paymentMethodRepository(), $this->appRepository());

        $binding = $binder->bind(
            self::PAYMENT_METHOD_ID,
            PaymentGatewayFixture::CHANNEL,
            null,
            Context::createDefaultContext()
        );

        static::assertSame(PaymentGatewayFixture::PLATFORM_APP_ID, $binding->paymentAppId);
    }

    public function testBindRejectsAnUnknownApp(): void
    {
        $binder = $this->binder(
            $this->repository(),
            $this->paymentMethodRepository(),
            $this->appRepository()
        );

        $this->expectExceptionObject(PaymentException::appNotFound('unknown-app-id'));

        $binder->bind(
            self::PAYMENT_METHOD_ID,
            PaymentGatewayFixture::CHANNEL,
            'unknown-app-id',
            Context::createDefaultContext()
        );
    }

    /**
     * A switched off account cannot collect, so binding it would produce a method that fails on the
     * first payment.
     */
    public function testBindRejectsADisabledApp(): void
    {
        $binder = $this->binder(
            $this->repository(),
            $this->paymentMethodRepository(),
            $this->appRepository([$this->externalApp(active: false)])
        );

        $this->expectExceptionObject(PaymentException::appDisabled('other-app'));

        $binder->bind(
            self::PAYMENT_METHOD_ID,
            PaymentGatewayFixture::CHANNEL,
            self::OTHER_APP_ID,
            Context::createDefaultContext()
        );
    }

    /**
     * Re-binding keeps the row: writing a second one would run into the unique key over (app, channel),
     * and a method collects through one channel at a time.
     */
    public function testRebindingReusesTheExistingRow(): void
    {
        $existing = $this->binding(self::PAYMENT_METHOD_ID);

        $bindingRepository = $this->createMock(EntityRepository::class);
        $bindingRepository->method('search')->willReturnCallback(
            fn (Criteria $criteria): EntitySearchResult => $this->hasNotEqualsFilter($criteria)
                ? $this->searchResult()
                : $this->searchResult([$existing])
        );
        $bindingRepository->expects($this->once())
            ->method('upsert')
            ->with(static::callback(static function (array $payload) use ($existing): bool {
                static::assertSame($existing->id, $payload[0]['id']);

                return true;
            }));

        $binder = $this->binder($bindingRepository, $this->paymentMethodRepository(), $this->appRepository());

        $binding = $binder->bind(
            self::PAYMENT_METHOD_ID,
            PaymentGatewayFixture::CHANNEL,
            null,
            Context::createDefaultContext()
        );

        static::assertSame($existing->id, $binding->id);
    }

    /**
     * A method that already dispatches on the gateway handler is not rewritten: the second write is
     * what the binding needs, not a habit of the binder.
     */
    public function testBindDoesNotRewriteAHandlerThatIsAlreadyTheGatewayHandler(): void
    {
        $paymentMethodRepository = $this->createMock(EntityRepository::class);
        $paymentMethodRepository->method('search')->willReturn($this->searchResult([
            $this->paymentMethod('Alipay', PaymentGatewayHandler::class),
        ]));
        $paymentMethodRepository->expects($this->never())->method('update');

        $binder = $this->binder($this->repository(), $paymentMethodRepository, $this->appRepository());

        $binder->bind(
            self::PAYMENT_METHOD_ID,
            PaymentGatewayFixture::CHANNEL,
            null,
            Context::createDefaultContext()
        );
    }

    public function testBindRejectsAnUnknownPaymentMethod(): void
    {
        $binder = $this->binder($this->repository(), $this->repository(), $this->appRepository());

        $this->expectExceptionObject(CheckoutPaymentException::unknownPaymentMethodById(self::PAYMENT_METHOD_ID));

        $binder->bind(
            self::PAYMENT_METHOD_ID,
            PaymentGatewayFixture::CHANNEL,
            null,
            Context::createDefaultContext()
        );
    }

    public function testBindRejectsAnUnknownChannel(): void
    {
        $binder = $this->binder($this->repository(), $this->paymentMethodRepository(), $this->appRepository());

        $this->expectExceptionObject(PaymentException::unknownChannel('alipay_card'));

        $binder->bind(
            self::PAYMENT_METHOD_ID,
            'alipay_card',
            null,
            Context::createDefaultContext()
        );
    }

    /**
     * A channel that is pulled up by an sdk belongs to an app that renders the payment itself. The
     * checkout refuses to hand a storefront payer over to it - after the order was placed - so the
     * binding is refused here, where the mistake can still be corrected.
     */
    public function testBindRejectsAChannelThatIsOpenedByAnSdk(): void
    {
        $binder = $this->binder(
            $this->repository(),
            $this->paymentMethodRepository(),
            $this->appRepository(),
            flow: PaymentFlow::SDK
        );

        $this->expectExceptionObject(PaymentException::channelNotAvailableInStorefront(PaymentGatewayFixture::CHANNEL));

        $binder->bind(
            self::PAYMENT_METHOD_ID,
            PaymentGatewayFixture::CHANNEL,
            null,
            Context::createDefaultContext()
        );
    }

    public function testBindRejectsAnInterfaceWithoutMerchantAccount(): void
    {
        $binder = $this->binder(
            $this->repository(),
            $this->paymentMethodRepository(),
            $this->appRepository(),
            providerConfig: $this->providerConfig(enabled: true, credentials: [])
        );

        $this->expectExceptionObject(PaymentException::providerNotConfigured(PaymentGatewayFixture::PROVIDER));

        $binder->bind(
            self::PAYMENT_METHOD_ID,
            PaymentGatewayFixture::CHANNEL,
            null,
            Context::createDefaultContext()
        );
    }

    public function testBindRejectsADisabledInterface(): void
    {
        $binder = $this->binder(
            $this->repository(),
            $this->paymentMethodRepository(),
            $this->appRepository(),
            providerConfig: $this->providerConfig(enabled: false)
        );

        $this->expectExceptionObject(PaymentException::providerNotConfigured(PaymentGatewayFixture::PROVIDER));

        $binder->bind(
            self::PAYMENT_METHOD_ID,
            PaymentGatewayFixture::CHANNEL,
            null,
            Context::createDefaultContext()
        );
    }

    public function testBindRejectsAChannelThePlatformDoesNotOffer(): void
    {
        $binder = $this->binder(
            $this->repository(),
            $this->paymentMethodRepository(),
            $this->appRepository(),
            channelConfigExists: false
        );

        $this->expectExceptionObject(PaymentException::channelDisabled(PaymentGatewayFixture::CHANNEL));

        $binder->bind(
            self::PAYMENT_METHOD_ID,
            PaymentGatewayFixture::CHANNEL,
            null,
            Context::createDefaultContext()
        );
    }

    public function testBindRejectsAChannelAnotherMethodAlreadyCollectsThrough(): void
    {
        $taken = $this->binding(self::OTHER_PAYMENT_METHOD_ID);
        $taken->paymentMethod = $this->paymentMethod('Alipay of the other method', PaymentGatewayHandler::class, self::OTHER_PAYMENT_METHOD_ID);

        $binder = $this->binder(
            $this->repository([$taken]),
            $this->repository([
                $this->paymentMethod('Invoice'),
                $this->paymentMethod('Alipay of the other method', PaymentGatewayHandler::class, self::OTHER_PAYMENT_METHOD_ID),
            ]),
            $this->appRepository()
        );

        $this->expectExceptionObject(PaymentException::paymentMethodChannelTaken(
            'Shopfront',
            PaymentGatewayFixture::CHANNEL,
            self::OTHER_PAYMENT_METHOD_ID,
            'Alipay of the other method'
        ));

        $binder->bind(
            self::PAYMENT_METHOD_ID,
            PaymentGatewayFixture::CHANNEL,
            null,
            Context::createDefaultContext()
        );
    }

    /**
     * The pair is what is unique, not the channel name: an app collects on its own merchant account, so
     * the same channel of another app is a different account and cannot collide.
     */
    public function testBindAcceptsTheSameChannelNameOnAnotherApp(): void
    {
        $otherApp = $this->externalApp();
        $taken = $this->binding(self::OTHER_PAYMENT_METHOD_ID, $otherApp->id);

        $binder = $this->binder(
            $this->repository([$taken]),
            $this->paymentMethodRepository(),
            $this->appRepository([$otherApp, PaymentGatewayFixture::platformAppEntity()])
        );

        $binding = $binder->bind(
            self::PAYMENT_METHOD_ID,
            PaymentGatewayFixture::CHANNEL,
            null,
            Context::createDefaultContext()
        );

        static::assertSame(PaymentGatewayFixture::PLATFORM_APP_ID, $binding->paymentAppId);
    }

    public function testTakenListsThePairsOfTheOtherMethods(): void
    {
        $binding = $this->binding(self::OTHER_PAYMENT_METHOD_ID);
        $binding->paymentMethod = $this->paymentMethod('Alipay', PaymentGatewayHandler::class, self::OTHER_PAYMENT_METHOD_ID);
        $binding->paymentApp = PaymentGatewayFixture::platformAppEntity();

        $binder = $this->binder($this->repository([$binding]), $this->paymentMethodRepository(), $this->appRepository());

        static::assertSame([[
            'paymentAppId' => PaymentGatewayFixture::PLATFORM_APP_ID,
            'paymentAppName' => 'Shopfront',
            'channelTechnicalName' => PaymentGatewayFixture::CHANNEL,
            'paymentMethodId' => self::OTHER_PAYMENT_METHOD_ID,
            'paymentMethodName' => 'Alipay',
        ]], $binder->taken(self::PAYMENT_METHOD_ID, Context::createDefaultContext()));
    }

    /**
     * The name of the method in the way is what the operator can act on, so it is read when the
     * binding did not load it as an association.
     */
    public function testTakenReadsTheNameOfAMethodThatWasNotLoadedWithTheBinding(): void
    {
        $binding = $this->binding(self::OTHER_PAYMENT_METHOD_ID);

        $binder = $this->binder(
            $this->repository([$binding]),
            $this->repository([$this->paymentMethod('Alipay', PaymentGatewayHandler::class, self::OTHER_PAYMENT_METHOD_ID)]),
            $this->appRepository()
        );

        $taken = $binder->taken(self::PAYMENT_METHOD_ID, Context::createDefaultContext());

        static::assertSame('Alipay', $taken[0]['paymentMethodName']);
        // the app was not loaded either, so the raw id stands in for the name
        static::assertSame(PaymentGatewayFixture::PLATFORM_APP_ID, $taken[0]['paymentAppName']);
    }

    /**
     * @param EntityRepository<PaymentMethodGatewayChannelCollection>|null $bindingRepository
     * @param EntityRepository<PaymentMethodCollection>|null $paymentMethodRepository
     * @param EntityRepository<PaymentAppCollection>|null $appRepository
     */
    private function binder(
        ?EntityRepository $bindingRepository = null,
        ?EntityRepository $paymentMethodRepository = null,
        ?EntityRepository $appRepository = null,
        ?PaymentProviderConfigEntity $providerConfig = null,
        bool $channelConfigExists = true,
        PaymentFlow $flow = PaymentFlow::REDIRECT,
    ): PaymentMethodChannelBinder {
        return new PaymentMethodChannelBinder(
            $bindingRepository ?? $this->repository(),
            $paymentMethodRepository ?? $this->repository(),
            $appRepository ?? $this->appRepository(),
            PaymentGatewayFixture::channelRegistry($flow),
            new PaymentProviderConfigService(
                $this->repository([$providerConfig ?? $this->providerConfig()]),
                $this->repository($channelConfigExists ? [$this->channelConfig()] : []),
                new PaymentCredentialCipher('unit-test-cipher-secret'),
            )
        );
    }

    /**
     * @return EntityRepository<PaymentMethodCollection>
     */
    private function paymentMethodRepository(): EntityRepository
    {
        return $this->repository([$this->paymentMethod('Invoice')]);
    }

    /**
     * @param list<PaymentAppEntity>|null $apps
     *
     * @return EntityRepository<PaymentAppCollection>
     */
    private function appRepository(?array $apps = null): EntityRepository
    {
        return $this->repository($apps ?? [PaymentGatewayFixture::platformAppEntity()]);
    }

    /**
     * A repository that answers the way the dal does for the filters this test uses: by id, by a single
     * condition and by a conjunction of conditions. Handing in the rows of one table and letting the
     * criteria decide keeps the tests honest about what the caller really asked for.
     *
     * @param list<Entity> $entities
     *
     * @phpstan-ignore missingType.generics (The stub serves every collection type the caller asserts.)
     */
    private function repository(array $entities = []): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            function (Criteria $criteria) use ($entities): EntitySearchResult {
                $matches = $entities;

                if ($criteria->getIds() !== []) {
                    $matches = array_values(array_filter(
                        $matches,
                        static fn (Entity $entity): bool => \in_array($entity->getUniqueIdentifier(), $criteria->getIds(), true)
                    ));
                }

                foreach ($criteria->getFilters() as $filter) {
                    $matches = array_values(array_filter(
                        $matches,
                        fn (Entity $entity): bool => $this->matchesFilter($entity, $filter)
                    ));
                }

                return PaymentGatewayFixture::searchResult($matches);
            }
        );

        return $repository;
    }

    private function matchesFilter(Entity $entity, Filter $filter): bool
    {
        // a negated filter wraps the filter it negates, so it is resolved before the conjunction
        if ($filter instanceof NotFilter) {
            $inner = array_map(fn (Filter $query): bool => $this->matchesFilter($entity, $query), $filter->getQueries());
            $matched = $filter->getOperator() === MultiFilter::CONNECTION_OR
                ? \in_array(true, $inner, true)
                : !\in_array(false, $inner, true);

            return !$matched;
        }

        if ($filter instanceof MultiFilter) {
            $results = array_map(fn (Filter $query): bool => $this->matchesFilter($entity, $query), $filter->getQueries());

            return $filter->getOperator() === MultiFilter::CONNECTION_OR
                ? \in_array(true, $results, true)
                : !\in_array(false, $results, true);
        }

        $vars = $entity->getVars();

        if ($filter instanceof EqualsFilter) {
            return ($vars[$filter->getField()] ?? null) === $filter->getValue();
        }

        if ($filter instanceof EqualsAnyFilter) {
            return \in_array($vars[$filter->getField()] ?? null, $filter->getValue(), true);
        }

        return true;
    }

    /**
     * @param list<Entity> $entities
     *
     * @return EntitySearchResult<EntityCollection<Entity>>
     */
    private function searchResult(array $entities = []): EntitySearchResult
    {
        return PaymentGatewayFixture::searchResult($entities);
    }

    private function hasNotEqualsFilter(Criteria $criteria): bool
    {
        foreach ($criteria->getFilters() as $filter) {
            if ($filter instanceof NotEqualsFilter) {
                return true;
            }
        }

        return false;
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

    private function externalApp(string $id = self::OTHER_APP_ID, bool $active = true): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->id = $id;
        $app->setUniqueIdentifier($id);
        $app->technicalName = 'other-app';
        $app->name = 'Other app';
        $app->type = PaymentAppEntity::TYPE_EXTERNAL;
        $app->active = $active;
        $app->appSecret = 'secret';
        $app->settings = [];

        return $app;
    }

    /**
     * @param array<string, mixed>|null $credentials
     */
    private function providerConfig(
        bool $enabled = true,
        ?array $credentials = ['app_id' => 'platform-app-id'],
    ): PaymentProviderConfigEntity {
        $config = new PaymentProviderConfigEntity();
        $config->id = 'provider-config-id';
        $config->setUniqueIdentifier($config->id);
        $config->providerTechnicalName = PaymentGatewayFixture::PROVIDER;
        $config->enabled = $enabled;
        $config->credentials = $credentials;

        return $config;
    }

    private function channelConfig(bool $enabled = true): PaymentChannelConfigEntity
    {
        $config = new PaymentChannelConfigEntity();
        $config->id = 'channel-config-id';
        $config->setUniqueIdentifier($config->id);
        $config->providerTechnicalName = PaymentGatewayFixture::PROVIDER;
        $config->channelTechnicalName = PaymentGatewayFixture::CHANNEL;
        $config->enabled = $enabled;

        return $config;
    }
}
