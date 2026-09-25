<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Cms\Subscriber;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cms\CmsException;
use Shopwell\Core\Content\Cms\CmsPageCollection;
use Shopwell\Core\Content\Cms\Exception\PageNotFoundException;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('discovery')]
class CmsPageBeforeDefaultChangeSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<CmsPageCollection>
     */
    private EntityRepository $cmsPageRepository;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cmsPageRepository = static::getContainer()->get('cms_page.repository');
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);
    }

    #[DataProvider('validDefaultCmsPageDataProvider')]
    public function testSetDefaultDoesNotThrow(string $validCmsPageId, ?string $salesChannelId): void
    {
        $this->createCmsPage($validCmsPageId);
        $error = null;
        $message = '';

        try {
            $this->systemConfigService->set(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, $validCmsPageId, $salesChannelId);
        } catch (\Throwable $e) {
            $error = $e;
            $message = \sprintf('No error expected, got "%s" with: %s', $error->getMessage(), $error->getTraceAsString());
        }
        static::assertNull($error, $message);
    }

    public static function validDefaultCmsPageDataProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'validCmsPageId with salesChanelId null' => [
            'validCmsPageId' => $ids->get('validCmsPageId'),
            'salesChannelId' => null,
        ];

        yield 'validCmsPageId with default salesChanelId' => [
            'validCmsPageId' => $ids->get('validCmsPageId'),
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
        ];
    }

    #[DataProvider('invalidDefaultCmsPageDataProvider')]
    public function testSetInvalidDefaultThrow(string $invalidCmsPageId, ?string $salesChannelId): void
    {
        if (Feature::isActive('v6.8.0.0')) {
            $this->expectExceptionObject(CmsException::pageNotFound($invalidCmsPageId));
        } else {
            $this->expectExceptionObject(new PageNotFoundException($invalidCmsPageId));
        }
        $this->systemConfigService->set(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, $invalidCmsPageId, $salesChannelId);
    }

    public static function invalidDefaultCmsPageDataProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'invalidCmsPageId with salesChanelId null' => [
            'invalidCmsPageId' => $ids->get('invalidCmsPageId'),
            'salesChannelId' => null,
        ];

        yield 'invalidCmsPageId with default salesChanelId' => [
            'invalidCmsPageId' => $ids->get('invalidCmsPageId'),
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
        ];
    }

    public function testDeleteSalesChannelDefaultDoesNotThrow(): void
    {
        $cmsPage = Uuid::randomHex();
        $this->createCmsPage($cmsPage);
        $error = null;
        $message = '';

        try {
            // set sales channel specific default
            $this->systemConfigService->set(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, $cmsPage, TestDefaults::SALES_CHANNEL);

            // expect to be able to delete the default
            $this->systemConfigService->set(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, null, TestDefaults::SALES_CHANNEL);
        } catch (\Throwable $e) {
            $error = $e;
            $message = \sprintf('No error expected, got "%s" with: %s', $error->getMessage(), $error->getTraceAsString());
        }
        static::assertNull($error, $message);
    }

    public function testDeleteOverallDefaultThrow(): void
    {
        $cmsPage = Uuid::randomHex();
        $this->createCmsPage($cmsPage);

        // set overall default
        $this->systemConfigService->set(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, $cmsPage);

        $this->expectExceptionObject(CmsException::overallDefaultSystemConfigDeletion($cmsPage));
        $this->systemConfigService->set(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, null);
    }

    private function createCmsPage(string $cmsPageId): void
    {
        $cmsPage = [
            'id' => $cmsPageId,
            'name' => 'test page',
            'type' => 'product_detail',
        ];

        $this->cmsPageRepository->create([$cmsPage], Context::createDefaultContext());
    }
}
