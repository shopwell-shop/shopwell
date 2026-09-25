<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Seo\HreflangLoader;
use Shopwell\Core\Content\Seo\HreflangLoaderParameter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Generator;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(HreflangLoader::class)]
class HreflangLoaderTest extends TestCase
{
    private RouterInterface&Stub $router;

    private Connection&Stub $connection;

    private HreflangLoader $loader;

    protected function setUp(): void
    {
        $this->router = static::createStub(RouterInterface::class);
        $this->connection = static::createStub(Connection::class);
        $this->loader = new HreflangLoader($this->router, $this->connection);
    }

    public function testSubfolderDeploymentStripsBasePath(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->getSalesChannel()->setHreflangActive(true);

        $languageId1 = Uuid::randomHex();
        $languageId2 = Uuid::randomHex();
        $domainId1 = Uuid::randomHex();
        $domainId2 = Uuid::randomHex();
        $productId = Uuid::randomHex();

        $this->router
            ->method('generate')
            ->willReturn('/shopwell/public/detail/' . $productId);

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls(
                [
                    ['languageId' => Uuid::fromHexToBytes($languageId1), 'id' => Uuid::fromHexToBytes($domainId1), 'url' => 'https://test.de', 'locale' => 'de-DE', 'onlyLocale' => false],
                    ['languageId' => Uuid::fromHexToBytes($languageId2), 'id' => Uuid::fromHexToBytes($domainId2), 'url' => 'https://test.de/en', 'locale' => 'en-GB', 'onlyLocale' => false],
                ],
                [
                    ['seoPathInfo' => 'nice-product', 'languageId' => Uuid::fromHexToBytes($languageId1)],
                    ['seoPathInfo' => 'nice-product-en', 'languageId' => Uuid::fromHexToBytes($languageId2)],
                ]
            );

        $parameter = new HreflangLoaderParameter(
            'frontend.detail.page',
            ['productId' => $productId],
            $salesChannelContext,
            false,
            '/shopwell/public'
        );

        $result = $this->loader->load($parameter);

        static::assertCount(2, $result);
        $urls = array_map(static fn ($item) => $item->getUrl(), $result->getElements());
        static::assertContains('https://test.de/nice-product', $urls);
        static::assertContains('https://test.de/en/nice-product-en', $urls);
    }

    public function testNoBasePathDoesNotModifyPath(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->getSalesChannel()->setHreflangActive(true);

        $languageId1 = Uuid::randomHex();
        $languageId2 = Uuid::randomHex();
        $domainId1 = Uuid::randomHex();
        $domainId2 = Uuid::randomHex();
        $productId = Uuid::randomHex();

        $this->router
            ->method('generate')
            ->willReturn('/detail/' . $productId);

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls(
                [
                    ['languageId' => Uuid::fromHexToBytes($languageId1), 'id' => Uuid::fromHexToBytes($domainId1), 'url' => 'https://test.de', 'locale' => 'de-DE', 'onlyLocale' => false],
                    ['languageId' => Uuid::fromHexToBytes($languageId2), 'id' => Uuid::fromHexToBytes($domainId2), 'url' => 'https://test.de/en', 'locale' => 'en-GB', 'onlyLocale' => false],
                ],
                [
                    ['seoPathInfo' => 'nice-product', 'languageId' => Uuid::fromHexToBytes($languageId1)],
                    ['seoPathInfo' => 'nice-product-en', 'languageId' => Uuid::fromHexToBytes($languageId2)],
                ]
            );

        $parameter = new HreflangLoaderParameter(
            'frontend.detail.page',
            ['productId' => $productId],
            $salesChannelContext,
        );

        $result = $this->loader->load($parameter);

        static::assertCount(2, $result);
        $urls = array_map(static fn ($item) => $item->getUrl(), $result->getElements());
        static::assertContains('https://test.de/nice-product', $urls);
        static::assertContains('https://test.de/en/nice-product-en', $urls);
    }
}
