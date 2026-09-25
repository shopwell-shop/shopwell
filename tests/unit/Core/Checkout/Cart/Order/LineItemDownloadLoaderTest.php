<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Order\LineItemDownloadLoader;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Content\Product\Aggregate\ProductDownload\ProductDownloadCollection;
use Shopwell\Core\Content\Product\Aggregate\ProductDownload\ProductDownloadEntity;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\State;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LineItemDownloadLoader::class)]
class LineItemDownloadLoaderTest extends TestCase
{
    /**
     * @var Stub&EntityRepository<ProductDownloadCollection>
     */
    private Stub&EntityRepository $productDownloadRepository;

    private LineItemDownloadLoader $loader;

    protected function setUp(): void
    {
        $this->productDownloadRepository = static::createStub(EntityRepository::class);

        $this->loader = new LineItemDownloadLoader($this->productDownloadRepository);
    }

    public function testLineItemDoesNotExist(): void
    {
        $payload = $this->loader->load([], Context::createDefaultContext());

        static::assertSame([], $payload);
    }

    public function testLineItemWithoutPayload(): void
    {
        $lineItems = [
            [
                'id' => Uuid::randomHex(),
            ],
        ];

        $payload = $this->loader->load($lineItems, Context::createDefaultContext());

        static::assertSame([], $payload);
    }

    public function testNoPayloadContinue(): void
    {
        $productDownload = new ProductDownloadEntity();
        $productDownload->setId(Uuid::randomHex());
        $productDownload->setProductId(Uuid::randomHex());

        $entitySearchResult = static::createStub(EntitySearchResult::class);
        $entitySearchResult->method('getEntities')->willReturn(new EntityCollection([$productDownload]));
        $productDownloadRepository = $this->createMock(EntityRepository::class);
        $productDownloadRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($entitySearchResult);
        $loader = new LineItemDownloadLoader($productDownloadRepository);

        $lineItems = [
            [
                'id' => Uuid::randomHex(),
                'referencedId' => Uuid::randomHex(),
                'states' => [State::IS_DOWNLOAD],
                'payload' => [
                    LineItem::PAYLOAD_PRODUCT_TYPE => ProductDefinition::TYPE_DIGITAL,
                ],
            ],
        ];

        $payload = $loader->load($lineItems, Context::createDefaultContext());

        static::assertSame([], $payload);
    }

    public function testLoadDownloadsPayload(): void
    {
        $productId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $productDownload = new ProductDownloadEntity();
        $productDownload->setId(Uuid::randomHex());
        $productDownload->setPosition(0);
        $productDownload->setProductId($productId);
        $productDownload->setMediaId($mediaId);
        $productDownload->setMedia(new MediaEntity());

        $entitySearchResult = static::createStub(EntitySearchResult::class);
        $entitySearchResult->method('getEntities')->willReturn(new EntityCollection([$productDownload]));
        $productDownloadRepository = $this->createMock(EntityRepository::class);
        $productDownloadRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($entitySearchResult);
        $loader = new LineItemDownloadLoader($productDownloadRepository);

        $lineItems = [
            [
                'id' => Uuid::randomHex(),
                'referencedId' => $productId,
                'states' => [State::IS_DOWNLOAD],
                'payload' => [
                    LineItem::PAYLOAD_PRODUCT_TYPE => ProductDefinition::TYPE_DIGITAL,
                ],
            ],
        ];

        $payload = $loader->load($lineItems, Context::createDefaultContext());

        static::assertSame([
            [
                [
                    'position' => 0,
                    'mediaId' => $mediaId,
                    'accessGranted' => false,
                ],
            ],
        ], $payload);
    }
}
