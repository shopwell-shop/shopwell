<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartBehavior;
use Shopwell\Core\Checkout\Cart\CartPersister;
use Shopwell\Core\Checkout\Cart\CartRuleLoader;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Country\CountryEntity;
use Shopwell\Core\System\Currency\CurrencyEntity;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class CartRuleLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[DataProvider('taxConfigProvider')]
    public function testTaxFreeConfig(string $accountType, bool $taxCustomerConfig, bool $taxBusinessConfig, string $expectedTaxConfig): void
    {
        /** @var CartRuleLoader $cartRuleLoader */
        $cartRuleLoader = static::getContainer()->get(CartRuleLoader::class);

        $customerGroup = new CustomerGroupEntity();
        $customerGroup->setId(Uuid::randomHex());
        $customerGroup->setDisplayGross(true);

        $customer = new CustomerEntity();
        $customer->setAccountType($accountType);
        $customer->setId(Uuid::randomHex());
        $customer->setGroup($customerGroup);
        $customer->setCompany('test-company');
        $customer->setVatIds(['DE123456789']);

        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setActive(true);
        $country->setShippingAvailable(true);
        $country->setCheckVatIdPattern(false);
        $country->setIsEu(false);

        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setTaxFreeFrom(0.0);
        $currency->setFactor(1.5);

        $taxCustomerConfig = new TaxFreeConfig($taxCustomerConfig);
        $country->setCustomerTax($taxCustomerConfig);

        $taxBusinessConfig = new TaxFreeConfig($taxBusinessConfig);
        $country->setCompanyTax($taxBusinessConfig);

        $salesChannelContext = Generator::generateSalesChannelContext(currency: $currency, currentCustomerGroup: $customerGroup, customer: $customer, country: $country);

        $cart = new Cart('test');

        $result = $cartRuleLoader->loadByCart($salesChannelContext, $cart, new CartBehavior());
        static::assertSame($expectedTaxConfig, $result->getCart()->getPrice()->getTaxStatus());
    }

    public static function taxConfigProvider(): \Generator
    {
        yield 'customer account + customer tax free => tax-free' => [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            true,
            false,
            CartPrice::TAX_STATE_FREE,
        ];

        yield 'business account + customer tax free => no tax-free' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            true,
            false,
            CartPrice::TAX_STATE_GROSS,
        ];

        yield 'customer account + business tax free => no tax-free' => [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            false,
            true,
            CartPrice::TAX_STATE_GROSS,
        ];

        yield 'business tax + business tax free => tax-free' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            false,
            true,
            CartPrice::TAX_STATE_FREE,
        ];

        yield 'customer account + no free tax => no tax-free' => [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            false,
            false,
            CartPrice::TAX_STATE_GROSS,
        ];

        yield 'business account + no free tax => no tax-free' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            false,
            false,
            CartPrice::TAX_STATE_GROSS,
        ];

        yield 'customer account + both free tax => tax-free' => [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            true,
            true,
            CartPrice::TAX_STATE_FREE,
        ];

        yield 'business account + both free tax => tax-free' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            true,
            true,
            CartPrice::TAX_STATE_FREE,
        ];
    }

    public function testExistingCartLoadDoesNotRecreateDeletedCart(): void
    {
        /** @var CartRuleLoader $cartRuleLoader */
        $cartRuleLoader = static::getContainer()->get(CartRuleLoader::class);
        $cartPersister = static::getContainer()->get(CartPersister::class);

        $cart = new Cart(Uuid::randomHex());
        $cart->add(
            (new LineItem('A', LineItem::CUSTOM_LINE_ITEM_TYPE))
                ->setPriceDefinition(new QuantityPriceDefinition(10.0, new TaxRuleCollection()))
                ->setLabel('test')
        );

        $context = $this->createSalesChannelContext($cart->getToken());

        $cartPersister->save($cart, $context);

        $loadedCart = $cartPersister->load($cart->getToken(), $context);
        $loadedCart->setErrorHash('outdated');

        $cartPersister->delete($loadedCart->getToken(), $context);

        $result = $cartRuleLoader->loadByCart($context, $loadedCart, new CartBehavior());

        static::assertTrue($result->getCart()->has('A'));

        $count = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT COUNT(*) FROM cart WHERE token = :token',
            ['token' => $loadedCart->getToken()]
        );

        static::assertSame('0', (string) $count);
    }

    private function createSalesChannelContext(string $token): SalesChannelContext
    {
        return static::getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create($token, TestDefaults::SALES_CHANNEL);
    }
}
