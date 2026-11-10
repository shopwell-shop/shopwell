<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Organization;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitCollection;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
class OrganizationUnitTreeTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testMovingAndDeletingAUnitUpdatesItsParentsAndDescendants(): void
    {
        $context = Context::createDefaultContext();
        $organizationId = Uuid::randomHex();
        $firstRootId = Uuid::randomHex();
        $secondRootId = Uuid::randomHex();
        $unitId = Uuid::randomHex();
        $leafId = Uuid::randomHex();
        static::getContainer()->get('organization.repository')->create([
            ['id' => $organizationId, 'name' => 'Tree integration organization', 'active' => true],
        ], $context);

        /** @var EntityRepository<OrganizationUnitCollection> $repository */
        $repository = static::getContainer()->get('organization_unit.repository');
        $repository->create([
            ['id' => $firstRootId, 'organizationId' => $organizationId, 'name' => 'First root'],
            ['id' => $secondRootId, 'organizationId' => $organizationId, 'name' => 'Second root'],
            ['id' => $unitId, 'organizationId' => $organizationId, 'parentId' => $firstRootId, 'name' => 'Unit'],
            ['id' => $leafId, 'organizationId' => $organizationId, 'parentId' => $unitId, 'name' => 'Leaf'],
        ], $context);

        $repository->update([['id' => $unitId, 'parentId' => $secondRootId]], $context);
        $units = $repository->search(new Criteria([$firstRootId, $secondRootId, $unitId, $leafId]), $context);
        $firstRoot = $units->get($firstRootId);
        $secondRoot = $units->get($secondRootId);
        $leaf = $units->get($leafId);
        static::assertNotNull($firstRoot);
        static::assertNotNull($secondRoot);
        static::assertNotNull($leaf);
        static::assertSame(0, $firstRoot->getChildCount());
        static::assertSame(1, $secondRoot->getChildCount());
        static::assertStringContainsString($secondRootId, $leaf->getPath() ?? '');
        static::assertStringNotContainsString($firstRootId, $leaf->getPath() ?? '');

        $repository->delete([['id' => $unitId]], $context);
        $units = $repository->search(new Criteria([$secondRootId, $unitId, $leafId]), $context);
        static::assertCount(1, $units);
        static::assertSame(0, $units->first()?->getChildCount());
    }
}
