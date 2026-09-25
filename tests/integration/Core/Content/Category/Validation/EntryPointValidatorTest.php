<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Category\Validation;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\CategoryCollection;
use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Category\CategoryEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\System\SalesChannel\SalesChannelCollection;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('discovery')]
class EntryPointValidatorTest extends TestCase
{
    use BasicTestDataBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    /**
     * @var EntityRepository<SalesChannelCollection>
     */
    private EntityRepository $salesChannelRepository;

    protected function setUp(): void
    {
        $this->categoryRepository = static::getContainer()->get(\sprintf('%s.repository', CategoryDefinition::ENTITY_NAME));
        $this->salesChannelRepository = static::getContainer()->get(\sprintf('%s.repository', SalesChannelDefinition::ENTITY_NAME));
    }

    public function testChangeNavigationFail(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = $this->getValidCategoryId();
        $this->salesChannelRepository->update([
            [
                'id' => TestDefaults::SALES_CHANNEL,
                'navigationCategoryId' => $categoryId,
            ],
        ], $context);

        $this->expectException(WriteException::class);
        $this->categoryRepository->update([
            [
                'id' => $categoryId,
                'type' => CategoryDefinition::TYPE_LINK,
            ],
        ], $context);
    }

    public function testChangeServiceFail(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = $this->getValidCategoryId();

        $this->expectException(WriteException::class);
        $this->salesChannelRepository->update([
            [
                'id' => TestDefaults::SALES_CHANNEL,
                'serviceCategory' => [
                    'id' => $categoryId,
                    'type' => CategoryDefinition::TYPE_LINK,
                ],
            ],
        ], $context);
    }

    public function testChangeFooterValid(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = $this->getValidCategoryId();
        $this->salesChannelRepository->update([
            [
                'id' => TestDefaults::SALES_CHANNEL,
                'footerCategory' => [
                    'id' => $categoryId,
                    'type' => CategoryDefinition::TYPE_PAGE,
                ],
            ],
        ], $context);

        /** @var CategoryEntity|null $category */
        $category = $this->categoryRepository->search(new Criteria([$categoryId]), $context)->getEntities()->first();
        static::assertNotNull($category);
        static::assertSame(CategoryDefinition::TYPE_PAGE, $category->getType());
    }
}
