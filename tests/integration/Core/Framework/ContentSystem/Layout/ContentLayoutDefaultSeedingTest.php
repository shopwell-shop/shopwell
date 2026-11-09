<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ContentSystem\Layout;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopwell\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopwell\Core\Framework\ContentSystem\Validation\LayoutGate;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Test\Stub\ContentSystem\TestElementTypeLoader;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class ContentLayoutDefaultSeedingTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
    }

    #[TestDox('seeds a type primitive default into a content layout written by a plain DAL create, so the stored tree is resolvable')]
    public function testPlainDalCreateSeedsPrimitiveDefaults(): void
    {
        $context = Context::createDefaultContext();
        $id = $this->ids->get('layout');

        // A plain DAL create with the raw-array payload the Admin / Sync API and fixtures build, bypassing the
        // mutation ops: the element carries no headline, so only the write-boundary seeder can seed it.
        $this->repository()->create([[
            'id' => $id,
            'name' => 'seeder-test',
            'version' => '1.0.0',
            'rootSource' => 'none',
            'layout' => [['id' => $this->ids->get('element'), 'component' => TestElementTypeLoader::DEFAULTED_PRIMITIVE, 'properties' => []]],
        ]], $context);

        $layout = $this->repository()->search(new Criteria([$id]), $context)->getEntities()->first();
        static::assertInstanceOf(ContentLayoutEntity::class, $layout);

        $tree = $layout->getLayout();
        static::assertCount(1, $tree);

        $headline = $tree[0]->property('headline');
        static::assertNotNull($headline);
        static::assertSame('Seeded headline', $headline->asString());

        // Pass [] (a bound source contributing no root context), not null: a null root context skips the
        // binding-scope checks, so isResolvable() would hold trivially. [] runs them against the seeded primitive.
        static::assertTrue($this->gate()->resolvability($tree, [])->isResolvable());
    }

    /**
     * @return EntityRepository<ContentLayoutCollection>
     */
    private function repository(): EntityRepository
    {
        $repository = $this->getContainer()->get('content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }

    private function gate(): LayoutGate
    {
        $gate = $this->getContainer()->get(LayoutGate::class);
        static::assertInstanceOf(LayoutGate::class, $gate);

        return $gate;
    }
}
