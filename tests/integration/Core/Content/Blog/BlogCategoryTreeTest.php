<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Blog;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryEntity;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryTree;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class BlogCategoryTreeTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testCategoriesAppendAndNativeTreeIndicesArePersisted(): void
    {
        $parent = $this->category('Parent');
        $first = $this->category('First', $parent);
        $second = $this->category('Second', $parent);
        $firstEntity = $this->read($first);
        $secondEntity = $this->read($second);

        static::assertSame($parent, $firstEntity->parentId);
        static::assertNull($firstEntity->afterCategoryId);
        static::assertSame($first, $secondEntity->afterCategoryId);
        static::assertSame('|' . $parent . '|', $secondEntity->path);
        static::assertSame(2, $secondEntity->level);
        static::assertSame(2, $this->read($parent)->childCount);
    }

    public function testMovingBetweenParentsRepairsBothSiblingChainsAndDescendantPaths(): void
    {
        $source = $this->category('Source');
        $target = $this->category('Target');
        $first = $this->category('First', $source);
        $moving = $this->category('Moving', $source);
        $last = $this->category('Last', $source);
        $descendant = $this->category('Descendant', $moving);
        $targetFirst = $this->category('Target first', $target);
        $targetLast = $this->category('Target last', $target);

        static::getContainer()->get(BlogCategoryTree::class)->move($moving, $target, $targetFirst, Context::createDefaultContext());

        static::assertSame($first, $this->read($last)->afterCategoryId);
        static::assertSame($target, $this->read($moving)->parentId);
        static::assertSame($targetFirst, $this->read($moving)->afterCategoryId);
        static::assertSame($moving, $this->read($targetLast)->afterCategoryId);
        static::assertSame('|' . $target . '|' . $moving . '|', $this->read($descendant)->path);
        static::assertSame(3, $this->read($descendant)->level);
        static::assertSame(2, $this->read($source)->childCount);
        static::assertSame(3, $this->read($target)->childCount);
    }

    public function testSameParentMoveToFirstPositionIsPersisted(): void
    {
        $parent = $this->category('Parent');
        $first = $this->category('First', $parent);
        $second = $this->category('Second', $parent);

        static::getContainer()->get(BlogCategoryTree::class)->move($second, $parent, null, Context::createDefaultContext());

        static::assertNull($this->read($second)->afterCategoryId);
        static::assertSame($second, $this->read($first)->afterCategoryId);
    }

    public function testParentOnlyRepositoryUpdateAppendsAfterTheTargetSiblings(): void
    {
        $source = $this->category('Source');
        $target = $this->category('Target');
        $sourceFirst = $this->category('Source first', $source);
        $moving = $this->category('Moving', $source);
        $sourceLast = $this->category('Source last', $source);
        $targetFirst = $this->category('Target first', $target);

        static::getContainer()->get('blog_category.repository')->update([
            ['id' => $moving, 'parentId' => $target],
        ], Context::createDefaultContext());

        static::assertSame($target, $this->read($moving)->parentId);
        static::assertSame($targetFirst, $this->read($moving)->afterCategoryId);
        static::assertSame($sourceFirst, $this->read($sourceLast)->afterCategoryId);
    }

    public function testFirstInsertKeepsTheTreeLockedUntilTheOuterTransactionCompletes(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM blog_category'));
        $this->category('First category');
        $other = new Connection($connection->getParams(), $connection->getDriver(), $connection->getConfiguration());
        $other->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
        $other->beginTransaction();

        try {
            try {
                $other->fetchOne('SELECT id FROM blog_category_tree_lock WHERE id = 1 FOR UPDATE');
                static::fail('The category write must retain its tree lock through the outer transaction.');
            } catch (LockWaitTimeoutException) {
                static::assertTrue($connection->isTransactionActive());
            }
        } finally {
            $other->rollBack();
            $other->close();
        }
    }

    public function testMovingUnderItselfIsRejectedAndLeavesTheTreeUnchanged(): void
    {
        $parent = $this->category('Parent');
        try {
            static::getContainer()->get(BlogCategoryTree::class)->move($parent, $parent, null, Context::createDefaultContext());
            static::fail('Self-parenting must fail.');
        } catch (BlogException $exception) {
            static::assertSame('CONTENT__BLOG_CATEGORY_CYCLE', $exception->getErrorCode());
        }

        static::assertNull($this->read($parent)->parentId);
        static::assertNull($this->read($parent)->afterCategoryId);
    }

    public function testSiblingOrderingCycleRollsBackTheWholeBatch(): void
    {
        $first = $this->category('First');
        $second = $this->category('Second');
        try {
            static::getContainer()->get('blog_category.repository')->update([
                ['id' => $first, 'afterCategoryId' => $second],
                ['id' => $second, 'afterCategoryId' => $first],
            ], Context::createDefaultContext());
            static::fail('A sibling ordering cycle must fail.');
        } catch (BlogException $exception) {
            static::assertSame('CONTENT__BLOG_CATEGORY_POSITION_INVALID', $exception->getErrorCode());
        }

        static::assertNull($this->read($first)->afterCategoryId);
        static::assertSame($first, $this->read($second)->afterCategoryId);
    }

    public function testMovingUnderDescendantIsRejectedAndLeavesTreeUnchanged(): void
    {
        $parent = $this->category('Parent');
        $child = $this->category('Child', $parent);
        try {
            static::getContainer()->get(BlogCategoryTree::class)->move($parent, $child, null, Context::createDefaultContext());
            static::fail('A category must not become its own ancestor.');
        } catch (BlogException $exception) {
            static::assertSame('CONTENT__BLOG_CATEGORY_CYCLE', $exception->getErrorCode());
        }

        static::assertNull($this->read($parent)->parentId);
        static::assertSame($parent, $this->read($child)->parentId);
    }

    public function testDirectBatchWritesCannotIntroduceParentCycles(): void
    {
        $first = $this->category('First');
        $second = $this->category('Second');
        $this->expectExceptionObject(BlogException::categoryCycle($first));

        static::getContainer()->get('blog_category.repository')->update([
            ['id' => $first, 'parentId' => $second],
            ['id' => $second, 'parentId' => $first],
        ], Context::createDefaultContext());
    }

    public function testPredecessorMustBelongToTheTargetParent(): void
    {
        $firstParent = $this->category('First parent');
        $secondParent = $this->category('Second parent');
        $moving = $this->category('Moving', $firstParent);
        $foreignSibling = $this->category('Foreign sibling', $secondParent);
        try {
            static::getContainer()->get(BlogCategoryTree::class)->move($moving, $firstParent, $foreignSibling, Context::createDefaultContext());
            static::fail('A predecessor from another parent must fail.');
        } catch (BlogException $exception) {
            static::assertSame('CONTENT__BLOG_CATEGORY_POSITION_INVALID', $exception->getErrorCode());
        }

        static::assertSame($firstParent, $this->read($moving)->parentId);
        static::assertNull($this->read($moving)->afterCategoryId);
        static::assertSame($secondParent, $this->read($foreignSibling)->parentId);
    }

    public function testDeletingALeafRepairsTheChainAndChildCount(): void
    {
        $parent = $this->category('Parent');
        $first = $this->category('First', $parent);
        $second = $this->category('Second', $parent);
        $last = $this->category('Last', $parent);

        static::getContainer()->get('blog_category.repository')->delete([['id' => $second]], Context::createDefaultContext());

        static::assertSame($first, $this->read($last)->afterCategoryId);
        static::assertSame(2, $this->read($parent)->childCount);
    }

    public function testDeletingACategoryWithChildrenIsRejected(): void
    {
        $parent = $this->category('Parent');
        $this->category('Child', $parent);
        $this->expectException(RestrictDeleteViolationException::class);

        static::getContainer()->get('blog_category.repository')->delete([['id' => $parent]], Context::createDefaultContext());
    }

    private function category(string $name, ?string $parentId = null): string
    {
        $id = Uuid::randomHex();
        static::getContainer()->get('blog_category.repository')->create([['id' => $id, 'name' => $name, 'slug' => $id, 'active' => true, 'parentId' => $parentId]], Context::createDefaultContext());

        return $id;
    }

    private function read(string $id): BlogCategoryEntity
    {
        $category = static::getContainer()->get('blog_category.repository')->search(new Criteria([$id]), Context::createDefaultContext())->first();
        static::assertInstanceOf(BlogCategoryEntity::class, $category);

        return $category;
    }
}
