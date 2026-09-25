<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\LineItemFactoryHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\CartException;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\LineItemFactoryHandler\CreditLineItemFactory;
use Shopwell\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopwell\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Content\Product\Cart\ProductCartProcessor;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CreditLineItemFactory::class)]
class CreditLineItemFactoryTest extends TestCase
{
    public function testSupports(): void
    {
        $factory = new CreditLineItemFactory(
            static::createStub(PriceDefinitionFactory::class),
            static::createStub(EntityRepository::class)
        );

        static::assertTrue($factory->supports('credit'));
        static::assertFalse($factory->supports('product'));
        static::assertFalse($factory->supports('custom'));
        static::assertFalse($factory->supports('promotion'));
        static::assertFalse($factory->supports('discount'));
        static::assertFalse($factory->supports('container'));
        static::assertFalse($factory->supports('foo'));
    }

    public function testCreate(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->setPermissions([ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES => true]);

        $data = [
            'id' => 'test-id',
            'type' => 'credit',
            'referencedId' => 'test-referenced-id',
            'quantity' => 5,
            'removable' => true,
            'stackable' => true,
            'label' => 'test-label',
            'description' => 'test-description',
        ];

        $factory = new CreditLineItemFactory(
            static::createStub(PriceDefinitionFactory::class),
            static::createStub(EntityRepository::class)
        );

        $lineItem = $factory->create($data, $context);

        static::assertSame('test-id', $lineItem->getId());
        static::assertSame('credit', $lineItem->getType());
        static::assertSame('test-referenced-id', $lineItem->getReferencedId());
        static::assertSame(5, $lineItem->getQuantity());
        static::assertTrue($lineItem->isRemovable());
        static::assertTrue($lineItem->isStackable());
        static::assertSame('test-label', $lineItem->getLabel());
        static::assertSame('test-description', $lineItem->getDescription());
        static::assertTrue($lineItem->isModified());
    }

    public function testCreateWithCoverId(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->setPermissions([ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES => true]);

        $data = [
            'id' => 'test-id',
            'type' => 'credit',
            'coverId' => 'test-cover-id',
        ];

        $expectedCriteria = new Criteria(['test-cover-id']);
        $mediaEntity = new MediaEntity();
        $mediaEntity->setId('test-cover-id');

        $result = new EntitySearchResult(
            'media',
            1,
            new EntityCollection([$mediaEntity]),
            null,
            $expectedCriteria,
            $context->getContext()
        );

        $mediaRepo = $this->createMock(EntityRepository::class);
        $mediaRepo
            ->expects($this->once())
            ->method('search')
            ->with(static::equalTo($expectedCriteria), $context->getContext())
            ->willReturn($result);

        $factory = new CreditLineItemFactory(
            static::createStub(PriceDefinitionFactory::class),
            $mediaRepo
        );

        $lineItem = $factory->create($data, $context);

        static::assertSame('test-id', $lineItem->getId());
        static::assertSame('credit', $lineItem->getType());
        static::assertNull($lineItem->getReferencedId());
        static::assertSame(1, $lineItem->getQuantity());
        static::assertSame($mediaEntity, $lineItem->getCover());
        static::assertTrue($lineItem->isModified());
    }

    public function testCreateWithPriceDefinition(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->setPermissions([ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES => true]);

        $data = [
            'id' => 'test-id',
            'type' => 'credit',
            'priceDefinition' => [
                'type' => 'test-type',
                'price' => 100,
                'precision' => 2,
            ],
        ];

        $priceDefinition = new AbsolutePriceDefinition(100.0);

        $priceDefinitionFactory = $this->createMock(PriceDefinitionFactory::class);
        $priceDefinitionFactory
            ->expects($this->once())
            ->method('factory')
            ->with(
                static::equalTo($context->getContext()),
                static::equalTo($data['priceDefinition']),
                static::equalTo('credit')
            )
            ->willReturn($priceDefinition);

        $factory = new CreditLineItemFactory(
            $priceDefinitionFactory,
            static::createStub(EntityRepository::class)
        );

        $lineItem = $factory->create($data, $context);

        static::assertSame('test-id', $lineItem->getId());
        static::assertSame('credit', $lineItem->getType());
        static::assertNull($lineItem->getReferencedId());
        static::assertSame(1, $lineItem->getQuantity());
        static::assertSame($priceDefinition, $lineItem->getPriceDefinition());
        static::assertTrue($lineItem->isModified());
    }

    public function testCreateWithoutPermission(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->setPermissions([ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES => false]);

        $data = [
            'id' => 'test-id',
            'type' => 'credit',
        ];

        $factory = new CreditLineItemFactory(
            static::createStub(PriceDefinitionFactory::class),
            static::createStub(EntityRepository::class)
        );

        $this->expectException(CartException::class);

        $factory->create($data, $context);
    }

    public function testUpdateWithoutPermissions(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->setPermissions([ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES => false]);

        $factory = new CreditLineItemFactory(
            static::createStub(PriceDefinitionFactory::class),
            static::createStub(EntityRepository::class)
        );

        $lineItem = new LineItem('test-id', 'credit', null, 1);

        $this->expectException(CartException::class);

        $factory->update($lineItem, [], $context);
    }
}
