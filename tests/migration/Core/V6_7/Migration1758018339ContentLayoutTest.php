<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Migration\V6_7\Migration1758018339ContentLayout;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1758018339ContentLayout::class)]
class Migration1758018339ContentLayoutTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1758018339, (new Migration1758018339ContentLayout())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $migration = new Migration1758018339ContentLayout();

        // content_layout is referenced by foreign keys from the *_content_layout assignment tables;
        // disable foreign key checks so the parent table can be dropped without touching its dependents
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $this->connection->executeStatement('DROP TABLE IF EXISTS `content_layout`');

            static::assertFalse(TableHelper::tableExists($this->connection, 'content_layout'));

            $migration->update($this->connection);
            $migration->update($this->connection);

            static::assertTrue(TableHelper::tableExists($this->connection, 'content_layout'));
            $layouts = $this->connection->fetchAllAssociative('SELECT LOWER(HEX(id)) AS id, name, root_source, layout FROM content_layout');
            static::assertCount(13, $layouts);
            $roots = array_column($layouts, 'root_source');
            static::assertContains('category', $roots);
            static::assertContains('product', $roots);
            static::assertContains('landing_page', $roots);
            static::assertContains('none', $roots);
            foreach ($layouts as $layout) {
                static::assertNotSame([], json_decode($layout['layout'], true, 512, \JSON_THROW_ON_ERROR));
            }

            $defaults = [];
            foreach ($layouts as $layout) {
                $defaults[$layout['name']] = json_decode($layout['layout'], true, 512, \JSON_THROW_ON_ERROR);
            }
            static::assertSame('Sw:Content:Text', $defaults['Default category'][0]['component']);
            static::assertSame('<h1>{{map:category.name}}</h1>', $defaults['Default category'][0]['properties']['text']);
            static::assertSame('Sw:Content:PageNotFound', $defaults['Page not found'][0]['component']);
            static::assertContains('Sw:Product:GaranLabel', array_column($defaults['Default product'], 'component'));
            $garanLabel = array_values(array_filter($defaults['Default product'], static fn (array $element): bool => $element['component'] === 'Sw:Product:GaranLabel'));
            static::assertEquals(['type' => 'root', 'id' => 'product'], $garanLabel[0]['acceptsContext']['product']['source']);

            $table = TableHelper::getTable($this->connection, 'content_layout');
            static::assertCount(7, $table->columns);

            static::assertTrue(TableHelper::columnExists($this->connection, 'content_layout', 'id'));
            static::assertTrue(TableHelper::columnExists($this->connection, 'content_layout', 'name'));
            static::assertTrue(TableHelper::columnExists($this->connection, 'content_layout', 'version'));
            static::assertTrue(TableHelper::columnExists($this->connection, 'content_layout', 'layout'));
            static::assertTrue(TableHelper::columnExists($this->connection, 'content_layout', 'root_source'));
            static::assertTrue(TableHelper::columnExists($this->connection, 'content_layout', 'created_at'));
            static::assertTrue(TableHelper::columnExists($this->connection, 'content_layout', 'updated_at'));

            // root_source is NOT NULL with no column default: it is a Required field, so every DAL write supplies it.
            $rootSource = TableHelper::getColumnOfTable($this->connection, 'content_layout', 'root_source');
            static::assertTrue($rootSource->isNotNull);
            static::assertNull($rootSource->defaultValue);

            // No entity field maps `schema`, so the table does not carry the column.
            static::assertFalse(TableHelper::columnExists($this->connection, 'content_layout', 'schema'));

            static::assertTrue(
                TableHelper::indexExists($this->connection, 'content_layout', 'uniq.content_layout.name_version'),
                'Unique index on (name, version) must exist',
            );

            $this->assertSeededLayoutsRemainEditable($layouts);
        } finally {
            // restore the parent table so its dependents keep a consistent schema, then re-enable enforcement
            try {
                $migration->update($this->connection);
            } finally {
                $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }

    /**
     * Raw SQL seeding bypasses the write gate, so a readable layout can still reject every editor save.
     * Exercise a real property edit through the normal repository for every freshly seeded layout.
     *
     * @param list<array<string, mixed>> $layouts
     */
    private function assertSeededLayoutsRemainEditable(array $layouts): void
    {
        /** @var EntityRepository<ContentLayoutCollection> $repository */
        $repository = KernelLifecycleManager::getKernel()->getContainer()->get('content_layout.repository');
        $rejections = [];

        foreach ($layouts as $layout) {
            static::assertIsString($layout['layout']);
            static::assertIsString($layout['id']);
            static::assertIsString($layout['name']);
            $elements = json_decode($layout['layout'], true, 512, \JSON_THROW_ON_ERROR);
            static::assertIsArray($elements);

            $property = match ($elements[0]['component']) {
                'Sw:Filter:Panel' => 'visibleFilterCount',
                'Sw:Content:Text' => 'text',
                default => 'title',
            };
            $value = $property === 'visibleFilterCount'
                ? 7
                : ($elements[0]['properties'][$property] ?? '') . '<p>Saved default layout</p>';
            $elements[0]['properties'][$property] = $value;

            try {
                $repository->update([
                    ['id' => $layout['id'], 'layout' => $elements],
                ], Context::createDefaultContext());

                $stored = $this->connection->fetchOne(
                    'SELECT layout FROM content_layout WHERE id = UNHEX(:id)',
                    ['id' => $layout['id']],
                );
                static::assertIsString($stored);
                $savedElements = json_decode($stored, true, 512, \JSON_THROW_ON_ERROR);
                static::assertIsArray($savedElements);
                static::assertSame($value, $savedElements[0]['properties'][$property], $layout['name']);
            } catch (WriteException $exception) {
                $rejections[$layout['name']] = $exception->getMessage();
            } finally {
                $this->connection->executeStatement(
                    'UPDATE content_layout SET layout = :layout, updated_at = NULL WHERE id = UNHEX(:id)',
                    ['layout' => $layout['layout'], 'id' => $layout['id']],
                );
            }
        }

        static::assertSame([], $rejections, 'Every seeded layout must accept and persist an editor property change.');
    }
}
