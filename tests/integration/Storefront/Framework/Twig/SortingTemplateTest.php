<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopwell\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('discovery')]
class SortingTemplateTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * The `score` sorting is locked, but its translations are editable in the
     * administration, so its label must not be replaced by a snippet.
     */
    public function testScoreSortingKeepsItsTranslatedLabel(): void
    {
        $output = $this->renderSortings(new ProductSortingCollection([
            $this->createSorting(key: 'score', label: 'Label configured in the administration'),
        ]));

        static::assertStringContainsString('<option value="score">Label configured in the administration</option>', $output);
        static::assertStringNotContainsString('Top results', $output);
    }

    public function testConfigurableSortingKeepsItsTranslatedLabel(): void
    {
        $output = $this->renderSortings(new ProductSortingCollection([
            $this->createSorting(key: 'name-asc', label: 'Name A-Z'),
        ]));

        static::assertStringContainsString('<option value="name-asc">Name A-Z</option>', $output);
    }

    private function renderSortings(ProductSortingCollection $sortings): string
    {
        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        return StorefrontTwigRenderer::render(static::getContainer(), '@Storefront/storefront/component/sorting.html.twig', [
            'current' => '',
            'sortings' => $sortings,
        ], $context);
    }

    private function createSorting(string $key, string $label): ProductSortingEntity
    {
        $sorting = new ProductSortingEntity();
        $sorting->setUniqueIdentifier(Uuid::randomHex());
        $sorting->setKey($key);
        $sorting->setTranslated(['label' => $label]);

        return $sorting;
    }
}
