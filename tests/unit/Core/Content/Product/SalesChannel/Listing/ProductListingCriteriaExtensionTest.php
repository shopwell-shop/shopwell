<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\SalesChannel\Listing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\Extension\ProductListingCriteriaExtension;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Tests\Examples\ProductListingCriteriaExtensionExample;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductListingCriteriaExtension::class)]
class ProductListingCriteriaExtensionTest extends TestCase
{
    public function testProductListingCriteriaExample(): void
    {
        $example = new ProductListingCriteriaExtensionExample();

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($example);

        $extension = new ProductListingCriteriaExtension(
            new Criteria(),
            static::createStub(SalesChannelContext::class),
            'categoryId'
        );

        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: ProductListingCriteriaExtension::NAME,
            extension: $extension,
            function: static function (Criteria $criteria, SalesChannelContext $context, string $categoryId): Criteria {
                $criteria->addFilter(
                    new EqualsFilter('product.categoriesRo.id', $categoryId)
                );

                return $criteria;
            }
        );

        static::assertInstanceOf(Criteria::class, $result);
        static::assertSame([], $result->getFilters());
    }
}
