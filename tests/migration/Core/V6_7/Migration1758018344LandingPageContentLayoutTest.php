<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Migration\V6_7\Migration1758018344LandingPageContentLayout;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1758018344LandingPageContentLayout::class)]
class Migration1758018344LandingPageContentLayoutTest extends TestCase
{
    #[TestDox('the content_layout_id foreign key restricts layout deletion while its sibling foreign keys still cascade')]
    public function testContentLayoutForeignKeyRestrictsDeletionWhileSiblingsCascade(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $connection->executeStatement('DROP TABLE IF EXISTS `landing_page_content_layout`');

        $migration = new Migration1758018344LandingPageContentLayout();
        $migration->update($connection);
        $migration->update($connection);

        $contentLayoutForeignKey = 'fk.landing_page_content_layout.content_layout_id';
        $siblingForeignKeys = ['fk.landing_page_content_layout.sales_channel_id'];
        $rules = [];
        foreach ([$contentLayoutForeignKey, ...$siblingForeignKeys] as $name) {
            $rule = $connection->fetchOne(
                'SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = :name',
                ['name' => $name]
            );
            static::assertIsString($rule, \sprintf('Foreign key "%s" was not found.', $name));
            $rules[$name] = $rule;
        }

        static::assertContains($rules[$contentLayoutForeignKey], ['RESTRICT', 'NO ACTION']);
        static::assertSame(
            array_fill_keys($siblingForeignKeys, 'CASCADE'),
            array_intersect_key($rules, array_fill_keys($siblingForeignKeys, true))
        );
    }
}
