<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Category\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\SalesChannel\SalesChannelCategoryDefinition;
use Shopwell\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopwell\Core\Content\Category\Service\CategoryUrlGenerator;
use Shopwell\Core\Content\Category\Subscriber\CategorySubscriber;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CategorySubscriber::class)]
class CategorySubscriberTest extends TestCase
{
    public function testSalesChannelCategoryLoadedGeneratesSeoUrl(): void
    {
        $generator = static::createStub(CategoryUrlGenerator::class);
        $generator->method('generate')->willReturn('https://example.com/category');
        $category = new SalesChannelCategoryEntity();
        $category->setId(Uuid::randomHex());

        (new CategorySubscriber($generator))->salesChannelCategoryLoaded(new SalesChannelEntityLoadedEvent(new SalesChannelCategoryDefinition(), [$category], Generator::generateSalesChannelContext()));

        static::assertSame('https://example.com/category', $category->getSeoUrl());
    }
}
