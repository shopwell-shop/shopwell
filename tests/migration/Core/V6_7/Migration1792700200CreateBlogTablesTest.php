<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1792700200CreateBlogTables;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1792700200CreateBlogTables::class)]
class Migration1792700200CreateBlogTablesTest extends TestCase
{
    private Connection $shared;

    private Connection $connection;

    private string $database;

    protected function setUp(): void
    {
        $this->shared = KernelLifecycleManager::getConnection();
        $this->database = 'blog_baseline_' . Uuid::randomHex();
        $this->shared->executeStatement('CREATE DATABASE `' . $this->database . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->connection = new Connection(array_replace($this->shared->getParams(), ['dbname' => $this->database]), $this->shared->getDriver(), $this->shared->getConfiguration());
        foreach (['media', 'language', 'content_layout', 'tag', 'sales_channel', 'customer', 'system_config'] as $table) {
            $this->connection->executeStatement('CREATE TABLE `' . $table . '` LIKE `' . $this->shared->getDatabase() . '`.`' . $table . '`');
        }
    }

    protected function tearDown(): void
    {
        $this->connection->close();
        $this->shared->executeStatement('DROP DATABASE `' . $this->database . '`');
    }

    public function testFreshInstallationCreatesTheCompleteBlogSchema(): void
    {
        (new Migration1792700200CreateBlogTables())->update($this->connection);

        $schema = $this->connection->createSchemaManager();
        static::assertTrue($schema->tablesExist(['blog', 'blog_category', 'blog_tag', 'blog_content_layout', 'blog_category_tree_lock']));
        static::assertSame('0', (string) $schema->introspectTableByUnquotedName('blog')->getColumn('active')->getDefault());
        $category = $schema->introspectTableByUnquotedName('blog_category');
        foreach (['parent_id', 'after_category_id', 'path', 'level', 'child_count'] as $column) {
            static::assertTrue($category->hasColumn($column));
        }
        static::assertContains($category->getForeignKey('fk.blog_category.parent_id')->getOnDeleteAction()->value, ['RESTRICT', 'NO ACTION']);
        static::assertSame('SET NULL', $category->getForeignKey('fk.blog_category.after_category_id')->getOnDeleteAction()->value);
        static::assertSame([1], array_map(intval(...), $this->connection->fetchFirstColumn('SELECT id FROM blog_category_tree_lock')));
    }

    public function testFreshInstallationSeedsArticleVideoAndListingLayouts(): void
    {
        (new Migration1792700200CreateBlogTables())->update($this->connection);

        $layouts = $this->connection->fetchAllAssociative('SELECT LOWER(HEX(id)) AS id, name, root_source, layout FROM content_layout ORDER BY name');
        static::assertCount(3, $layouts);
        static::assertSame(['blog', 'none', 'blog'], array_column($layouts, 'root_source'));
        static::assertSame(['Default blog article', 'Default blog listing', 'Default blog video'], array_column($layouts, 'name'));
        foreach ($layouts as $layout) {
            $elements = json_decode($layout['layout'], true, 512, \JSON_THROW_ON_ERROR);
            static::assertSame($layout['root_source'] === 'none' ? 'Sw:Blog:Feed' : 'Sw:Blog:Article', $elements[0]['component']);
        }
        static::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM system_config'));
        foreach (['post', 'video', 'listing'] as $type) {
            $value = $this->connection->fetchOne('SELECT configuration_value FROM system_config WHERE configuration_key = :key', ['key' => 'core.content_system.default_blog_' . $type . '_content_layout']);
            static::assertContains(json_decode($value, true, 512, \JSON_THROW_ON_ERROR)['_value'], array_column($layouts, 'id'));
        }
    }

    public function testRerunningInstallationDoesNotReplaceEditedDefaults(): void
    {
        $migration = new Migration1792700200CreateBlogTables();
        $migration->update($this->connection);
        $this->connection->executeStatement('UPDATE content_layout SET layout = :layout WHERE name = :name', ['layout' => '[{"component":"Sw:Content:Text"}]', 'name' => 'Default blog article']);
        $this->connection->executeStatement('UPDATE system_config SET configuration_value = :value WHERE configuration_key = :key', ['value' => '{"_value":"merchant-choice"}', 'key' => 'core.content_system.default_blog_post_content_layout']);

        $migration->update($this->connection);

        static::assertSame([['component' => 'Sw:Content:Text']], json_decode($this->connection->fetchOne('SELECT layout FROM content_layout WHERE name = :name', ['name' => 'Default blog article']), true, 512, \JSON_THROW_ON_ERROR));
        static::assertSame(['_value' => 'merchant-choice'], json_decode($this->connection->fetchOne('SELECT configuration_value FROM system_config WHERE configuration_key = :key', ['key' => 'core.content_system.default_blog_post_content_layout']), true, 512, \JSON_THROW_ON_ERROR));
        static::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM content_layout'));
    }
}
