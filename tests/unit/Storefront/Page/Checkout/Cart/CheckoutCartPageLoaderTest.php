<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Error\ErrorCollection;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRouteResponse;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Country\CountryCollection;
use Shopwell\Core\System\Country\CountryDefinition;
use Shopwell\Core\System\Country\CountryEntity;
use Shopwell\Core\System\Country\SalesChannel\CountryRoute;
use Shopwell\Core\System\Country\SalesChannel\CountryRouteResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopwell\Storefront\Checkout\Cart\SalesChannel\StorefrontCartGatewayResult;
use Shopwell\Storefront\Page\Checkout\Cart\CheckoutCartPage;
use Shopwell\Storefront\Page\Checkout\Cart\CheckoutCartPageLoader;
use Shopwell\Storefront\Page\GenericPageLoader;
use Shopwell\Storefront\Page\MetaInformation;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CheckoutCartPageLoader::class)]
class CheckoutCartPageLoaderTest extends TestCase
{
    public function testRobotsMetaSetIfGiven(): void
    {
        $page = new CheckoutCartPage();
        $page->setMetaInformation(new MetaInformation());

        $pageLoader = static::createStub(GenericPageLoader::class);
        $pageLoader
            ->method('load')
            ->willReturn($page);

        $page = $this->createLoader($pageLoader)->load(
            new Request(),
            $this->getContextWithDummyCustomer()
        );

        static::assertNotNull($page->getMetaInformation());
        static::assertSame('noindex,follow', $page->getMetaInformation()->getRobots());
    }

    public function testRobotsMetaNotSetIfGiven(): void
    {
        $page = new CheckoutCartPage();

        $pageLoader = static::createStub(GenericPageLoader::class);
        $pageLoader
            ->method('load')
            ->willReturn($page);

        $page = $this->createLoader($pageLoader)->load(
            new Request(),
            $this->getContextWithDummyCustomer()
        );

        static::assertNull($page->getMetaInformation());
    }

    public function testPaymentShippingAndCountryMethodsAreSetToPage(): void
    {
        $paymentMethods = new PaymentMethodCollection([
            (new PaymentMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
            (new PaymentMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
        ]);

        $shippingMethods = new ShippingMethodCollection([
            (new ShippingMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
            (new ShippingMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
        ]);

        $countries = new CountryCollection([
            (new CountryEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex(), 'position' => 0]),
            (new CountryEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex(), 'position' => 1]),
        ]);

        $response = new CheckoutGatewayRouteResponse(
            $paymentMethods,
            $shippingMethods,
            new ErrorCollection()
        );

        $countryResponse = new CountryRouteResponse(
            new EntitySearchResult(
                CountryDefinition::ENTITY_NAME,
                2,
                $countries,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $cartService = static::createStub(StorefrontCartFacade::class);
        $cartService
            ->method('getWithCheckoutGateway')
            ->willReturn(new StorefrontCartGatewayResult(new Cart('test'), $response));

        $countryRoute = static::createStub(CountryRoute::class);
        $countryRoute
            ->method('load')
            ->willReturn($countryResponse);

        $page = $this->createLoader(cartService: $cartService, countryRoute: $countryRoute)->load(
            new Request(),
            static::createStub(SalesChannelContext::class)
        );

        static::assertSame($paymentMethods, $page->getPaymentMethods());
        static::assertSame($shippingMethods, $page->getShippingMethods());
        static::assertSame($countries, $page->getCountries());
    }

    public function testNoCountrySetIfLoggedIn(): void
    {
        $countries = new CountryCollection([
            (new CountryEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex(), 'position' => 0]),
            (new CountryEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex(), 'position' => 1]),
        ]);

        $countryResponse = new CountryRouteResponse(
            new EntitySearchResult(
                CountryDefinition::ENTITY_NAME,
                2,
                $countries,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $countryRoute = static::createStub(CountryRoute::class);
        $countryRoute
            ->method('load')
            ->willReturn($countryResponse);

        $page = $this->createLoader(countryRoute: $countryRoute)->load(
            new Request(),
            $this->getContextWithDummyCustomer()
        );

        static::assertCount(0, $page->getCountries());
    }

    private function createLoader(
        ?GenericPageLoader $pageLoader = null,
        ?StorefrontCartFacade $cartService = null,
        ?CountryRoute $countryRoute = null,
    ): CheckoutCartPageLoader {
        return new CheckoutCartPageLoader(
            $pageLoader ?? static::createStub(GenericPageLoader::class),
            static::createStub(EventDispatcher::class),
            $cartService ?? $this->createCartService(),
            $countryRoute ?? static::createStub(CountryRoute::class),
            static::createStub(AbstractTranslator::class),
        );
    }

    private function createCartService(): StorefrontCartFacade
    {
        $cartService = static::createStub(StorefrontCartFacade::class);
        $cartService
            ->method('getWithCheckoutGateway')
            ->willReturn(new StorefrontCartGatewayResult(
                new Cart('test'),
                new CheckoutGatewayRouteResponse(
                    new PaymentMethodCollection(),
                    new ShippingMethodCollection(),
                    new ErrorCollection()
                )
            ));

        return $cartService;
    }

    private function getContextWithDummyCustomer(): SalesChannelContext
    {
        $address = (new CustomerAddressEntity())->assign(['id' => Uuid::randomHex(), 'countryId' => Uuid::randomHex()]);

        $customer = new CustomerEntity();
        $customer->assign([
            'activeBillingAddress' => $address,
            'activeShippingAddress' => $address,
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context
            ->method('getCustomer')
            ->willReturn($customer);
        $context
            ->method('getToken')
            ->willReturn('token');

        return $context;
    }
}
