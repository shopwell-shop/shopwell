<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ContentSystem\Storefront;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutDefinition;
use Shopwell\Core\Content\Test\Product\ProductBuilder;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pins Content System layout resolution on real Storefront product and category routes:
 * explicit assignments override type-specific defaults, and variants inherit parent assignments.
 *
 * @internal
 */
#[Package('framework')]
class ContentLayoutAssignmentResolutionTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private IdsCollection $ids;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);

        $this->createCategory();
        $this->createProduct();
        $this->createContentLayout('category-layout', 'category');
        $this->createContentLayout('product-layout', 'product');
        $this->createContentLayout('default-category-layout', 'category');
        $this->createContentLayout('default-product-layout', 'product');
        $this->systemConfigService->set(CategoryContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, $this->ids->get('default-category-layout'));
        $this->systemConfigService->set(ProductContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, $this->ids->get('default-product-layout'));
    }

    protected function tearDown(): void
    {
        // The system config cache outlives the rolled back transaction, so the defaults are unset explicitly.
        $this->systemConfigService->delete(CategoryContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT);
        $this->systemConfigService->delete(ProductContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT);

        parent::tearDown();
    }

    #[TestDox('renders the default category layout without an explicit assignment')]
    public function testCategoryUsesDefaultLayoutWithoutAssignment(): void
    {
        $this->assertRendersContentLayout($this->requestCategory(), 'default-category-layout');
    }

    #[TestDox('renders an explicit category layout without a configured default')]
    public function testExplicitCategoryAssignmentWithoutDefault(): void
    {
        $this->systemConfigService->delete(CategoryContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT);
        $this->assignLayout('category_content_layout.repository', 'categoryId', 'category', 'category-layout');

        $this->assertRendersContentLayout($this->requestCategory(), 'category-layout');
    }

    #[TestDox('renders a replacement category default')]
    public function testCategoryUsesReplacementDefault(): void
    {
        $this->systemConfigService->set(CategoryContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, $this->ids->get('category-layout'));

        $this->assertRendersContentLayout($this->requestCategory(), 'category-layout');
    }

    #[TestDox('renders the explicitly assigned category layout instead of the default Experience Studio category layout')]
    public function testExplicitCategoryAssignmentOverridesDefaultCategoryLayout(): void
    {
        $this->assignLayout('category_content_layout.repository', 'categoryId', 'category', 'category-layout');

        $this->assertRendersContentLayout($this->requestCategory(), 'category-layout');
    }

    #[TestDox('restores the category default after removing an explicit assignment')]
    public function testRemovedCategoryAssignmentRestoresDefaultLayout(): void
    {
        $assignmentId = $this->assignLayout('category_content_layout.repository', 'categoryId', 'category', 'category-layout');
        $this->assertRendersContentLayout($this->requestCategory(), 'category-layout');

        $this->repository('category_content_layout.repository')->delete([['id' => $assignmentId]], Context::createDefaultContext());

        $this->assertRendersContentLayout($this->requestCategory(), 'default-category-layout');
    }

    #[TestDox('does not resolve the default Experience Studio product layout for a category')]
    public function testDefaultProductLayoutDoesNotApplyToCategories(): void
    {
        $this->systemConfigService->set(ProductContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, $this->ids->get('product-layout'));

        $this->assertRendersContentLayout($this->requestCategory(), 'default-category-layout');
    }

    #[TestDox('renders the default product layout without an explicit assignment')]
    public function testProductUsesDefaultLayoutWithoutAssignment(): void
    {
        $this->assertRendersContentLayout($this->requestProduct(), 'default-product-layout');
    }

    #[TestDox('renders an explicit product layout without a configured default')]
    public function testExplicitProductAssignmentWithoutDefault(): void
    {
        $this->systemConfigService->delete(ProductContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT);
        $this->assignLayout('product_content_layout.repository', 'productId', 'product', 'product-layout');

        $this->assertRendersContentLayout($this->requestProduct(), 'product-layout');
    }

    #[TestDox('renders a replacement product default')]
    public function testProductUsesReplacementDefault(): void
    {
        $this->systemConfigService->set(ProductContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, $this->ids->get('product-layout'));

        $this->assertRendersContentLayout($this->requestProduct(), 'product-layout');
    }

    #[TestDox('restores the product default after removing an explicit assignment')]
    public function testRemovedProductAssignmentRestoresDefaultLayout(): void
    {
        $assignmentId = $this->assignLayout('product_content_layout.repository', 'productId', 'product', 'product-layout');
        $this->assertRendersContentLayout($this->requestProduct(), 'product-layout');

        $this->repository('product_content_layout.repository')->delete([['id' => $assignmentId]], Context::createDefaultContext());

        $this->assertRendersContentLayout($this->requestProduct(), 'default-product-layout');
    }

    #[TestDox('does not resolve the default Experience Studio category layout for a product')]
    public function testDefaultCategoryLayoutDoesNotApplyToProducts(): void
    {
        $this->systemConfigService->set(CategoryContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, $this->ids->get('category-layout'));

        $this->assertRendersContentLayout($this->requestProduct(), 'default-product-layout');
    }

    #[TestDox('renders the Experience Studio layout assigned to a variant on the variant detail page')]
    public function testVariantAssignmentOverridesDefaultLayout(): void
    {
        $this->createVariantProduct();
        $this->assignLayout('product_content_layout.repository', 'productId', 'variant', 'product-layout');

        $this->assertRendersContentLayout($this->requestPage('detail/' . $this->ids->get('variant')), 'product-layout');
    }

    #[TestDox('renders the Experience Studio layout assigned to the parent product on a variant without an assignment')]
    public function testVariantInheritsParentAssignment(): void
    {
        $this->createVariantProduct();
        $this->assignLayout('product_content_layout.repository', 'productId', 'variant-parent', 'product-layout');

        $this->assertRendersContentLayout($this->requestPage('detail/' . $this->ids->get('variant')), 'product-layout');
    }

    #[TestDox('renders the variant assignment instead of the parent product assignment')]
    public function testVariantAssignmentOverridesParentAssignment(): void
    {
        $this->createVariantProduct();
        $this->createContentLayout('parent-product-layout', 'product');
        $this->assignLayout('product_content_layout.repository', 'productId', 'variant-parent', 'parent-product-layout');
        $this->assignLayout('product_content_layout.repository', 'productId', 'variant', 'product-layout');

        $this->assertRendersContentLayout($this->requestPage('detail/' . $this->ids->get('variant')), 'product-layout');
    }

    private function assertRendersContentLayout(string $html, string $layoutKey): void
    {
        static::assertStringContainsString('data-element-id="' . $this->ids->get($layoutKey . '-root') . '"', $html);
    }

    private function requestCategory(): string
    {
        return $this->requestPage('navigation/' . $this->ids->get('category'));
    }

    private function requestProduct(): string
    {
        return $this->requestPage('detail/' . $this->ids->get('product'));
    }

    private function requestPage(string $path): string
    {
        $response = $this->request('GET', $path, []);

        // The technical route redirects to the canonical SEO url, which is served by the same controller action.
        if ($response->isRedirection()) {
            $location = (string) $response->headers->get('Location');
            $response = $this->request('GET', ltrim((string) parse_url($location, \PHP_URL_PATH), '/'), []);
        }

        $html = (string) $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $html);

        return $html;
    }

    private function assignLayout(string $repositoryId, string $entityIdField, string $entityKey, string $layoutKey): string
    {
        $assignmentId = Uuid::randomHex();

        $this->repository($repositoryId)->create([[
            'id' => $assignmentId,
            $entityIdField => $this->ids->get($entityKey),
            'salesChannelId' => null,
            'contentLayoutId' => $this->ids->get($layoutKey),
        ]], Context::createDefaultContext());

        return $assignmentId;
    }

    private function createContentLayout(string $layoutKey, string $rootSource): void
    {
        $this->repository('content_layout.repository')->create([[
            'id' => $this->ids->create($layoutKey),
            'name' => $layoutKey,
            'version' => '1.0.0',
            'rootSource' => $rootSource,
            'layout' => [[
                'id' => $this->ids->create($layoutKey . '-root'),
                'component' => 'Sw:Grid:Container',
                'properties' => [],
                'slots' => [],
            ]],
        ]], Context::createDefaultContext());
    }

    private function createCategory(): void
    {
        $navigationCategoryId = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(navigation_category_id)) FROM sales_channel WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($this->getSalesChannelId())]
        );

        $this->repository('category.repository')->create([[
            'id' => $this->ids->create('category'),
            'parentId' => $navigationCategoryId,
            'name' => 'Layout assignment category',
            'active' => true,
        ]], Context::createDefaultContext());
    }

    private function createProduct(): void
    {
        $product = (new ProductBuilder($this->ids, 'product'))
            ->price(10)
            ->visibility($this->getSalesChannelId())
            ->build();

        $this->repository('product.repository')->create([$product], Context::createDefaultContext());
    }

    private function createVariantProduct(): void
    {
        $product = (new ProductBuilder($this->ids, 'variant-parent'))
            ->price(10)
            ->visibility($this->getSalesChannelId())
            ->configuratorSetting('red', 'color')
            ->variant(
                (new ProductBuilder($this->ids, 'variant'))
                    ->option('red', 'color')
                    ->build()
            )
            ->build();

        $this->repository('product.repository')->create([$product], Context::createDefaultContext());
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function repository(string $serviceId): EntityRepository
    {
        $repository = static::getContainer()->get($serviceId);
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
