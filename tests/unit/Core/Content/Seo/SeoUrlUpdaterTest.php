<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Seo\SeoException;
use Shopwell\Core\Content\Seo\SeoUrlGenerator;
use Shopwell\Core\Content\Seo\SeoUrlPersister;
use Shopwell\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopwell\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopwell\Core\Content\Seo\SeoUrlUpdater;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Language\LanguageCollection;
use Shopwell\Core\System\Language\LanguageEntity;
use Shopwell\Core\System\SalesChannel\SalesChannelCollection;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlUpdater::class)]
class SeoUrlUpdaterTest extends TestCase
{
    /**
     * @var StaticEntityRepository<LanguageCollection>
     */
    private StaticEntityRepository $languageRepository;

    private SeoUrlRouteRegistry $seoUrlRouteRegistry;

    private SeoUrlGenerator&MockObject $seoUrlGenerator;

    private SeoUrlPersister&MockObject $seoUrlPersister;

    private Connection&Stub $connection;

    /**
     * @var StaticEntityRepository<SalesChannelCollection>
     */
    private StaticEntityRepository $salesChannelRepository;

    protected function setUp(): void
    {
        $this->seoUrlGenerator = $this->createMock(SeoUrlGenerator::class);
        $this->seoUrlPersister = $this->createMock(SeoUrlPersister::class);
        $this->connection = static::createStub(Connection::class);
    }

    public function testUpdateWithoutDomain(): void
    {
        $seoUrlUpdater = $this->createSeoUrlUpdater([], [], [new ProductPageSeoUrlRoute(new ProductDefinition())]);

        $this->connection->method('fetchAllAssociative')->willReturn([]);
        $this->seoUrlGenerator->expects($this->never())->method('generate');
        $this->seoUrlPersister->expects($this->never())->method('updateSeoUrls');

        $seoUrlUpdater->update(ProductPageSeoUrlRoute::ROUTE_NAME, []);
    }

    public function testUpdateWithoutDefaultTemplates(): void
    {
        $seoUrlUpdater = $this->createSeoUrlUpdater([], [], [new ProductPageSeoUrlRoute(new ProductDefinition())]);

        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'salesChannelId' => Uuid::randomHex(),
                'languageId' => Uuid::randomHex(),
            ],
        ]);
        $this->connection->method('fetchAllKeyValue')->willReturn([]);

        $this->seoUrlGenerator->expects($this->never())->method('generate');
        $this->seoUrlPersister->expects($this->never())->method('updateSeoUrls');

        $this->expectExceptionObject(new \RuntimeException('Default templates not configured'));
        $seoUrlUpdater->update(ProductPageSeoUrlRoute::ROUTE_NAME, []);
    }

    public function testUpdateWithoutRoute(): void
    {
        $seoUrlUpdater = $this->createSeoUrlUpdater();

        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'salesChannelId' => Uuid::randomHex(),
                'languageId' => Uuid::randomHex(),
            ],
        ]);

        $this->connection->method('fetchAllKeyValue')->willReturn(
            [
                '' => '{{ product.translated.name }}/{{ product.productNumber }}',
            ]
        );

        $this->seoUrlGenerator->expects($this->never())->method('generate');
        $this->seoUrlPersister->expects($this->never())->method('updateSeoUrls');
        $this->expectExceptionObject(SeoException::seoUrlRouteNotFound('test'));

        $seoUrlUpdater->update('test', []);
    }

    public function testUpdateWithOutSalesChannel(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'salesChannelId' => Uuid::randomHex(),
                'languageId' => Uuid::randomHex(),
            ],
        ]);

        $this->connection->method('fetchAllKeyValue')->willReturn(
            [
                '' => '{{ product.translated.name }}/{{ product.productNumber }}',
            ]
        );

        $seoUrlUpdater = $this->createSeoUrlUpdater(
            [
                new LanguageCollection([]),
            ],
            [
                new SalesChannelCollection([]),
            ],
            [
                new ProductPageSeoUrlRoute(new ProductDefinition()),
            ]
        );

        $this->seoUrlGenerator->expects($this->never())->method('generate');
        $this->seoUrlPersister->expects($this->never())->method('updateSeoUrls');

        $seoUrlUpdater->update(ProductPageSeoUrlRoute::ROUTE_NAME, []);
    }

    public function testUpdateGetPersisted(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'salesChannelId' => 'testSalsesChannelId',
                'languageId' => 'testLanguageId',
            ],
        ]);

        $this->connection->method('fetchAllKeyValue')->willReturn(
            [
                '' => '{{ product.translated.name }}/{{ product.productNumber }}',
            ]
        );

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('testSalsesChannelId');

        $language = new LanguageEntity();
        $language->setId('testLanguageId');

        $seoUrlUpdater = $this->createSeoUrlUpdater(
            [
                new LanguageCollection([
                    $language,
                ]),
            ],
            [
                new SalesChannelCollection([
                    $salesChannel,
                ]),
            ],
            [
                new ProductPageSeoUrlRoute(new ProductDefinition()),
            ]
        );

        $this->seoUrlGenerator->expects($this->once())->method('generate');
        $this->seoUrlPersister->expects($this->once())->method('updateSeoUrls');

        $seoUrlUpdater->update(ProductPageSeoUrlRoute::ROUTE_NAME, []);
    }

    /**
     * @param LanguageCollection[] $languageSearches
     * @param SalesChannelCollection[] $salesChannelSearches
     * @param SeoUrlRouteInterface[] $seoUrlRoutes
     */
    private function createSeoUrlUpdater(
        array $languageSearches = [],
        array $salesChannelSearches = [],
        array $seoUrlRoutes = []
    ): SeoUrlUpdater {
        $this->languageRepository = new StaticEntityRepository($languageSearches);
        $this->seoUrlRouteRegistry = new SeoUrlRouteRegistry($seoUrlRoutes);
        $this->salesChannelRepository = new StaticEntityRepository($salesChannelSearches);

        return new SeoUrlUpdater(
            $this->languageRepository,
            $this->seoUrlRouteRegistry,
            $this->seoUrlGenerator,
            $this->seoUrlPersister,
            $this->connection,
            $this->salesChannelRepository
        );
    }
}
