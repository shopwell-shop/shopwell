<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\AdminMenu\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\AdminMenu\AdminMenuDefinitionStruct;
use Shopwell\Core\System\AdminMenu\CoreMenuRow;
use Shopwell\Core\System\AdminMenu\Service\CoreMenuRowsBuilder;

/**
 * The rows a migration inserts and the rows the synchronizer compares against come from here,
 * so the id derivation and the chain order are pinned down once for both of them.
 *
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(CoreMenuRowsBuilder::class)]
class CoreMenuRowsBuilderTest extends TestCase
{
    private CoreMenuRowsBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new CoreMenuRowsBuilder();
    }

    public function testDerivesAStableIdFromTheInternalKey(): void
    {
        $rows = $this->builder->build([$this->declaration('sw-a')]);

        static::assertCount(1, $rows);
        static::assertSame($this->builder->idFor('sw-a'), $rows[0]->id);
        // a second build has to produce the same identity, or a re-run would create a copy
        static::assertSame($rows[0]->id, $this->builder->build([$this->declaration('sw-a')])[0]->id);
        static::assertNotSame($this->builder->idFor('sw-a'), $this->builder->idFor('sw-b'));
    }

    public function testOrdersTheTopLevelByDeclaredPositionAndChainsIt(): void
    {
        $rows = $this->builder->build([
            $this->declaration('third', position: 30),
            $this->declaration('first', position: 10),
            $this->declaration('second', position: 20),
        ]);

        static::assertSame(['first', 'second', 'third'], $this->internalKeys($rows));
        static::assertNull($rows[0]->afterMenuItemId);
        static::assertSame($rows[0]->id, $rows[1]->afterMenuItemId);
        static::assertSame($rows[1]->id, $rows[2]->afterMenuItemId);
        static::assertNull($rows[0]->parentId);
    }

    public function testPutsChildrenBehindTheirParent(): void
    {
        $rows = $this->builder->build([
            $this->declaration('child-b', parent: 'parent', position: 20),
            $this->declaration('parent', position: 10),
            $this->declaration('child-a', parent: 'parent', position: 10),
        ]);

        $byKey = $this->byInternalKey($rows);

        static::assertSame($byKey['parent']->id, $byKey['child-a']->parentId);
        static::assertSame($byKey['parent']->id, $byKey['child-b']->parentId);
        static::assertNull($byKey['child-a']->afterMenuItemId);
        static::assertSame($byKey['child-a']->id, $byKey['child-b']->afterMenuItemId);
    }

    /**
     * A seeder inserts the rows in order; a child whose parent is written later would meet a
     * foreign key that points at nothing.
     */
    public function testWritesParentsBeforeTheirChildren(): void
    {
        $rows = $this->builder->build([
            $this->declaration('grandchild', parent: 'child', position: 10),
            $this->declaration('child', parent: 'parent', position: 10),
            $this->declaration('parent', position: 10),
        ]);

        static::assertSame(['parent', 'child', 'grandchild'], $this->internalKeys($rows));
    }

    /**
     * A row an administrator dragged somewhere keeps its place - it is not part of the output -
     * but the entry that follows it in the declaration still has to be chained behind it.
     */
    public function testLeavesACustomizedEntryOutButKeepsItInTheChain(): void
    {
        $rows = $this->builder->build(
            [
                $this->declaration('first', position: 10),
                $this->declaration('second', position: 20),
                $this->declaration('third', position: 30),
            ],
            ['second' => 'customized-id']
        );

        static::assertSame(['first', 'third'], $this->internalKeys($rows));
        static::assertSame('customized-id', $rows[1]->afterMenuItemId);
    }

    public function testFallsBackToTheTopLevelForADeclarationWithAnUnknownParent(): void
    {
        $rows = $this->builder->build([$this->declaration('orphan', parent: 'nobody')]);

        static::assertCount(1, $rows);
        static::assertNull($rows[0]->parentId);
    }

    /**
     * Two declarations naming each other as their parent are unreachable from the top level.
     * Dropping them would remove menu entries because of a broken declaration.
     */
    public function testKeepsDeclarationsThatFormACycle(): void
    {
        $rows = $this->builder->build([
            $this->declaration('a', parent: 'b'),
            $this->declaration('b', parent: 'a'),
        ]);

        static::assertSame(['a', 'b'], $this->internalKeys($rows));
        static::assertNull($rows[0]->parentId);
        static::assertNull($rows[1]->parentId);
    }

    private function declaration(string $internalKey, ?string $parent = null, ?int $position = null): AdminMenuDefinitionStruct
    {
        return new AdminMenuDefinitionStruct(
            internalKey: $internalKey,
            title: 'snippet.' . $internalKey,
            parent: $parent,
            position: $position,
        );
    }

    /**
     * @param list<CoreMenuRow> $rows
     *
     * @return list<string>
     */
    private function internalKeys(array $rows): array
    {
        return array_map(static fn (CoreMenuRow $row): string => $row->internalKey, $rows);
    }

    /**
     * @param list<CoreMenuRow> $rows
     *
     * @return array<string, CoreMenuRow>
     */
    private function byInternalKey(array $rows): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            $indexed[$row->internalKey] = $row;
        }

        return $indexed;
    }
}
