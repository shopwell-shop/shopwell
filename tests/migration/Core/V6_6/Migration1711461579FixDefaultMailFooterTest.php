<?php

declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Migration\V6_6\Migration1711461579FixDefaultMailFooter;
use Shopwell\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1711461579FixDefaultMailFooter::class)]
class Migration1711461579FixDefaultMailFooterTest extends TestCase
{
    use MigrationTestTrait;

    private Migration1711461579FixDefaultMailFooter $migration;

    private Connection $connection;

    private string $zhCnLanguageId;

    protected function setUp(): void
    {
        $this->migration = new Migration1711461579FixDefaultMailFooter();
        $this->connection = KernelLifecycleManager::getConnection();
        $zhCnLanguageId = $this->fetchLanguageId($this->connection, 'zh-CN');
        static::assertIsString($zhCnLanguageId);
        $this->zhCnLanguageId = $zhCnLanguageId;
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1711461579, (new Migration1711461579FixDefaultMailFooter())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->ensureMailFooterHasTypo();

        $this->migration->update($this->connection);

        static::assertFalse($this->hasMailFooterTypo());
    }

    private function ensureMailFooterHasTypo(): void
    {
        if ($this->hasMailFooterTypo()) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE mail_header_footer_translation
            SET footer_plain = REPLACE(footer_plain, \'银行账户\', \'银行帐户\')
            WHERE language_id = :id',
            ['id' => $this->zhCnLanguageId],
            ['id' => ParameterType::BINARY]
        );

        static::assertTrue($this->hasMailFooterTypo());
    }

    private function hasMailFooterTypo(): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1
             FROM mail_header_footer_translation
             WHERE footer_plain LIKE \'%银行帐户%\'
                AND language_id = :id;',
            ['id' => $this->zhCnLanguageId],
            ['id' => ParameterType::BINARY]
        );
    }
}
