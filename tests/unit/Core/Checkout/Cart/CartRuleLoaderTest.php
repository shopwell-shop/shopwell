<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopwell\Core\Checkout\Cart\AbstractCartPersister;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartBehavior;
use Shopwell\Core\Checkout\Cart\CartException;
use Shopwell\Core\Checkout\Cart\CartFactory;
use Shopwell\Core\Checkout\Cart\CartRuleLoader;
use Shopwell\Core\Checkout\Cart\Extension\CheckoutCartRuleLoaderExtension;
use Shopwell\Core\Checkout\Cart\Processor;
use Shopwell\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopwell\Core\Checkout\Cart\RuleLoader;
use Shopwell\Core\Checkout\Cart\Tax\TaxDetector;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopwell\Core\Content\Rule\RuleCollection;
use Shopwell\Core\Content\Rule\RuleEntity;
use Shopwell\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopwell\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Country\CountryEntity;
use Shopwell\Core\System\SalesChannel\Context\LanguageInfo;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartRuleLoader::class)]
class CartRuleLoaderTest extends TestCase
{
    public function testLoadByTokenCreatesNewCart(): void
    {
        $newCart = new Cart('test');
        $factory = $this->createMock(CartFactory::class);
        $factory
            ->expects($this->once())
            ->method('createNew')
            ->with('test')
            ->willReturn($newCart);

        $persister = $this->createMock(AbstractCartPersister::class);
        $persister
            ->expects($this->once())
            ->method('load')
            ->with('test')
            ->willThrowException(CartException::tokenNotFound('test'));

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getToken')
            ->willReturn('test');
        $salesChannelContext
            ->expects($this->exactly(2))
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

        $calculatedCart = new Cart('calculated');
        $processor = $this->createMock(Processor::class);
        $processor
            ->expects($this->exactly(3))
            ->method('process')
            ->with(static::isInstanceOf(Cart::class), $salesChannelContext, static::isInstanceOf(CartBehavior::class))
            ->willReturn($calculatedCart);

        $ruleLoader = $this->createMock(RuleLoader::class);
        $ruleLoader
            ->expects($this->once())
            ->method('load')
            ->with($salesChannelContext->getContext())
            ->willReturn(new RuleCollection());

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->with(static::isInstanceOf(CheckoutCartRuleLoaderExtension::class));

        $cartRuleLoader = new CartRuleLoader(
            $persister,
            $processor,
            new NullLogger(),
            static::createStub(CacheInterface::class),
            $ruleLoader,
            static::createStub(TaxDetector::class),
            static::createStub(Connection::class),
            $factory,
            new ExtensionDispatcher($dispatcher),
            static::createStub(AbstractTranslator::class),
        );

        static::assertSame($calculatedCart, $cartRuleLoader->loadByToken($salesChannelContext, $salesChannelContext->getToken())->getCart());
    }

    public function testProcessorHasCorrectRuleIds(): void
    {
        $country = new CountryEntity();
        $country->setId(Generator::COUNTRY);
        $country->setCustomerTax(new TaxFreeConfig());

        $customer = new CustomerEntity();
        $customer->setAccountType(CustomerEntity::ACCOUNT_TYPE_PRIVATE);
        $customer->setId('test-id');

        $salesChannelContext = Generator::generateSalesChannelContext(customer: $customer, country: $country);

        $rule1 = new RuleEntity();
        $rule1->setId(Uuid::randomHex());
        $rule1->setName($rule1->getId());
        $rule1->setPriority(1);
        $rule1->setAreas([RuleAreas::PRODUCT_AREA, RuleAreas::PAYMENT_AREA]);
        $rule1->setPayload(new AlwaysValidRule());

        $rule2 = new RuleEntity();
        $rule2->setId(Uuid::randomHex());
        $rule2->setName($rule2->getId());
        $rule2->setPriority(2);
        $rule2->setAreas([RuleAreas::PRODUCT_AREA]);
        $rule2->setPayload(new AlwaysValidRule());

        $rule3 = new RuleEntity();
        $rule3->setId(Uuid::randomHex());
        $rule3->setName($rule3->getId());
        $rule3->setPriority(3);
        $rule3->setAreas([RuleAreas::PAYMENT_AREA, RuleAreas::PAYMENT_AREA]);
        $rule3->setPayload(new AlwaysValidRule());

        $ruleIds = [$rule1->getId(), $rule2->getId(), $rule3->getId()];
        $areaRuleIds = [
            RuleAreas::PRODUCT_AREA => [$rule1->getId(), $rule2->getId()],
            RuleAreas::PAYMENT_AREA => [$rule1->getId(), $rule3->getId()],
        ];

        $ruleLoader = $this->createMock(RuleLoader::class);
        $ruleLoader
            ->expects($this->once())
            ->method('load')
            ->with($salesChannelContext->getContext())
            ->willReturn(new RuleCollection([$rule1, $rule2, $rule3]))
        ;

        $processor = $this->createMock(Processor::class);
        $processor
            ->expects($this->exactly(3))
            ->method('process')
            ->with(static::isInstanceOf(Cart::class), static::callback(static function (SalesChannelContext $context) use ($ruleIds, $areaRuleIds) {
                static::assertSame($ruleIds, $context->getRuleIds());
                static::assertSame($areaRuleIds, $context->getAreaRuleIds());

                return true;
            }), static::isInstanceOf(CartBehavior::class))
        ;

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->with(static::isInstanceOf(CheckoutCartRuleLoaderExtension::class));

        $cartRuleLoader = new CartRuleLoader(
            static::createStub(AbstractCartPersister::class),
            $processor,
            new NullLogger(),
            static::createStub(CacheInterface::class),
            $ruleLoader,
            static::createStub(TaxDetector::class),
            static::createStub(Connection::class),
            static::createStub(CartFactory::class),
            new ExtensionDispatcher($dispatcher),
            static::createStub(AbstractTranslator::class),
        );

        $cart = new Cart('test');
        $cart->setRuleIds($ruleIds);
        $cartRuleLoader->loadByCart($salesChannelContext, $cart, new CartBehavior());
    }

    public function testTranslatesProcessedCartErrorsWithSalesChannelLocale(): void
    {
        $reason = 'rule not matching or inactive';
        $customer = new CustomerEntity();
        $customer->setId('test-id');
        $customer->setAccountType(CustomerEntity::ACCOUNT_TYPE_PRIVATE);

        $country = new CountryEntity();
        $country->setId(Generator::COUNTRY);
        $country->setCustomerTax(new TaxFreeConfig());

        $salesChannelContext = Generator::generateSalesChannelContext(
            customer: $customer,
            languageInfo: new LanguageInfo('Chinese', 'zh-CN'),
            country: $country,
        );

        $cart = new Cart('test');
        $processedCart = new Cart('processed');
        $processedCart->getErrors()->add(new ShippingMethodBlockedError(
            id: 'shipping-method-id',
            name: 'Standard',
            reason: $reason,
        ));

        $processor = static::createStub(Processor::class);
        $processor
            ->method('process')
            ->willReturn($processedCart);

        $ruleLoader = $this->createMock(RuleLoader::class);
        $ruleLoader
            ->expects($this->once())
            ->method('load')
            ->with($salesChannelContext->getContext())
            ->willReturn(new RuleCollection());

        $translator = $this->createMock(AbstractTranslator::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->willReturnCallback(static function (string $id, array $parameters, ?string $domain, ?string $locale) use ($salesChannelContext, $reason): string {
                static::assertNull($domain);
                static::assertSame($salesChannelContext->getLanguageInfo()->localeCode, $locale);
                static::assertSame('checkout.shipping-method-blocked', $id);
                static::assertSame([
                    '%id%' => 'shipping-method-id',
                    '%name%' => 'Standard',
                    '%reason%' => $reason,
                ], $parameters);

                return '配送方式“Standard”已对您当前的购物车禁用。';
            });

        $cartRuleLoader = new CartRuleLoader(
            static::createStub(AbstractCartPersister::class),
            $processor,
            new NullLogger(),
            static::createStub(CacheInterface::class),
            $ruleLoader,
            static::createStub(TaxDetector::class),
            static::createStub(Connection::class),
            static::createStub(CartFactory::class),
            new ExtensionDispatcher(static::createStub(EventDispatcherInterface::class)),
            $translator,
        );

        $result = $cartRuleLoader->loadByCart($salesChannelContext, $cart, new CartBehavior());

        static::assertSame(
            '配送方式“Standard”已对您当前的购物车禁用。',
            $result->getCart()->getErrors()->first()?->getTranslatedMessage(),
        );
    }
}
