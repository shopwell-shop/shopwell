<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Administration\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Administration\Framework\Search\CriteriaCollection;
use Shopwell\Administration\Service\AdminSearcher;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AdminSearcher::class)]
class AdminSearcherTest extends TestCase
{
    private Stub&DefinitionInstanceRegistry $definitionInstanceRegistry;

    private AdminApiSource $source;

    protected function setUp(): void
    {
        $this->definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $this->source = new AdminApiSource('test');
        $this->source->setIsAdmin(false);
    }

    public function testAdminSearcherSearchWithEmptyCollection(): void
    {
        $adminSearcher = new AdminSearcher($this->definitionInstanceRegistry);

        $entities = new CriteriaCollection();

        static::assertSame([], $adminSearcher->search($entities, Context::createDefaultContext()));
    }

    public function testAdminSearcherSearchWithCriteriaNotInRegistry(): void
    {
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(false);
        $registry->expects($this->never())->method('getRepository');

        $adminSearcher = new AdminSearcher($registry);
        $queries = new CriteriaCollection(['product' => new Criteria()]);

        static::assertSame([], $adminSearcher->search($queries, Context::createDefaultContext($this->source)));
    }

    public function testAdminSearcherSearchWithNoReadAcl(): void
    {
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->expects($this->never())->method('getRepository');

        $adminSearcher = new AdminSearcher($registry);

        $queries = new CriteriaCollection(['product' => new Criteria()]);

        static::assertSame([], $adminSearcher->search($queries, Context::createDefaultContext($this->source)));
    }

    public function testAdminSearcherSearchWithReadAcl(): void
    {
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->expects($this->once())->method('getRepository')->willReturn(
            static::createStub(EntityRepository::class)
        );

        $this->source->setIsAdmin(true);

        $adminSearcher = new AdminSearcher($registry);

        $queries = new CriteriaCollection(['product' => new Criteria()]);

        $result = $adminSearcher->search($queries, Context::createDefaultContext($this->source));

        static::assertCount(1, $result);
        static::assertArrayHasKey('product', $result);

        $productResult = $result['product'];
        static::assertArrayHasKey('data', $productResult);
        static::assertArrayHasKey('total', $productResult);
        static::assertSame(0, $productResult['total']);
    }
}
