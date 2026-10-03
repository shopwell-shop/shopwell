<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\CountryEntity;
use Shopwell\Core\System\Currency\CurrencyEntity;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Core\System\Tax\TaxCollection;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityCacheKeyGenerator::class)]
class EntityCacheKeyGeneratorTest extends TestCase
{
    public function testBuildCmsTag(): void
    {
        static::assertSame('cms-page-foo', EntityCacheKeyGenerator::buildCmsTag('foo'));
    }

    public function testBuildProductTag(): void
    {
        static::assertSame('product-foo', EntityCacheKeyGenerator::buildProductTag('foo'));
    }

    public function testBuildStreamTag(): void
    {
        static::assertSame('product-stream-foo', EntityCacheKeyGenerator::buildStreamTag('foo'));
    }

    #[DataProvider('criteriaHashProvider')]
    public function testCriteriaHash(Criteria $criteria, string $hash): void
    {
        $generator = new EntityCacheKeyGenerator();

        static::assertSame($hash, $generator->getCriteriaHash($criteria));
    }

    public static function criteriaHashProvider(): \Generator
    {
        yield 'empty' => [
            new Criteria(),
            '49df289864c88521854da972597eff49',
        ];

        yield 'prefix-filter' => [
            (new Criteria())->addFilter(new PrefixFilter('foo', 'bar')),
            '3d784bd4ac566ad45d9b73f964606d1a',
        ];

        // this has a different hash because of a different filter type used
        yield 'suffix-filter' => [
            (new Criteria())->addFilter(new SuffixFilter('foo', 'bar')),
            'baa2b3e06780e1e23d4d379715ef03b0',
        ];

        yield 'filter+sort' => [
            (new Criteria())->addFilter(new PrefixFilter('foo', 'bar'))->addSorting(new FieldSorting('foo')),
            '6b0ee8a848b24905dfdd1f84103a883d',
        ];

        yield 'filter+sort+sort-desc' => [
            (new Criteria())->addFilter(new PrefixFilter('foo', 'bar'))->addSorting(new FieldSorting('foo', FieldSorting::DESCENDING)),
            '83096e069a6e98b2966c9144370f8ed7',
        ];

        yield 'filter+agg' => [
            (new Criteria())->addFilter(new PrefixFilter('foo', 'bar'))->addAggregation(new TermsAggregation('foo', 'foo')),
            'c112e895b0497e1a0136f53da1ac5a07',
        ];
    }

    #[DataProvider('contextHashProvider')]
    public function testContextHash(SalesChannelContext $compared): void
    {
        $generator = new EntityCacheKeyGenerator();

        static::assertNotSame(
            $generator->getSalesChannelContextHash(new DummyContext(), ['test']),
            $generator->getSalesChannelContextHash($compared, ['test'])
        );
    }

    public static function contextHashProvider(): \Generator
    {
        yield 'tax state considered for hash' => [
            (new DummyContext())->setTaxStateFluent(CartPrice::TAX_STATE_NET),
        ];

        yield 'currency id considered for hash' => [
            (new DummyContext())->setCurrencyId('foo'),
        ];

        yield 'sales channel id considered for hash' => [
            (new DummyContext())->setSalesChannelId('foo'),
        ];

        yield 'language id chain considered for hash' => [
            (new DummyContext())->setLanguageChain(['foo']),
        ];

        yield 'version considered for hash' => [
            (new DummyContext())->setVersionId('foo'),
        ];

        yield 'rounding mode considered for hash' => [
            (new DummyContext())->setItemRoundingFluent(new CashRoundingConfig(2, 0.5, true)),
        ];

        yield 'rules considered for hash' => [
            (new DummyContext())->setAreaRuleIdsFluent(['test' => ['foo']]),
        ];
    }
}

/**
 * @internal
 */
class DummyContext extends SalesChannelContext
{
    public function __construct()
    {
        $source = new SalesChannelApiSource(TestDefaults::SALES_CHANNEL);

        parent::__construct(
            new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM], Defaults::LIVE_VERSION, 1.0, true, CartPrice::TAX_STATE_GROSS),
            'token',
            'domain-id',
            (new SalesChannelEntity())->assign(['id' => TestDefaults::SALES_CHANNEL]),
            (new CurrencyEntity())->assign(['id' => Defaults::CURRENCY]),
            new CustomerGroupEntity(),
            new TaxCollection(),
            new PaymentMethodEntity(),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            new CustomerEntity(),
            new CashRoundingConfig(2, 0.01, true),
            new CashRoundingConfig(2, 0.01, true),
            Generator::createLanguageInfo(),
        );
    }

    public function setSalesChannelId(string $salesChannelId): self
    {
        $this->salesChannel = (new SalesChannelEntity())->assign(['id' => $salesChannelId]);

        return $this;
    }

    public function setCurrencyId(string $currencyId): self
    {
        $this->currency = (new CurrencyEntity())->assign(['id' => $currencyId]);

        return $this;
    }

    /**
     * @param non-empty-list<string> $chain
     */
    public function setLanguageChain(array $chain): self
    {
        $this->context->assign(['languageIdChain' => $chain]);

        return $this;
    }

    public function setVersionId(string $versionId): self
    {
        $this->context->assign(['versionId' => $versionId]);

        return $this;
    }

    public function setTaxStateFluent(string $taxState): self
    {
        $this->context->setTaxState($taxState);

        return $this;
    }

    /**
     * @param array<string, string[]> $rules
     */
    public function setAreaRuleIdsFluent(array $rules): self
    {
        $this->setAreaRuleIds($rules);

        return $this;
    }

    public function setItemRoundingFluent(CashRoundingConfig $rounding): self
    {
        $this->itemRounding = $rounding;

        return $this;
    }
}
