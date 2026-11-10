<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1794296173RepairOrderReturnReasonTranslations;
use Shopwell\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1794296173RepairOrderReturnReasonTranslations::class)]
class Migration1794296173RepairOrderReturnReasonTranslationsTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testBackfillsMissingSystemTranslationWithoutChangingTheSource(): void
    {
        $englishLanguageId = $this->englishLanguageId();
        $reasonId = $this->createReason();
        $this->insertTranslation($reasonId, $englishLanguageId, '商品破损或存在质量问题');

        $migration = new Migration1794296173RepairOrderReturnReasonTranslations();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame('商品破损或存在质量问题', $this->label($reasonId, Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)));
        static::assertSame('商品破损或存在质量问题', $this->label($reasonId, $englishLanguageId));
        static::assertSame(2, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `order_return_reason_translation` WHERE `order_return_reason_id` = :id',
            ['id' => $reasonId]
        ));
    }

    public function testPreservesMerchantTranslations(): void
    {
        $englishLanguageId = $this->englishLanguageId();
        $reasonId = $this->createReason();
        $this->insertTranslation($reasonId, $englishLanguageId, 'Damaged');
        $this->insertTranslation($reasonId, Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), '商家维护的原因');

        (new Migration1794296173RepairOrderReturnReasonTranslations())->update($this->connection);

        static::assertSame('商家维护的原因', $this->label($reasonId, Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)));
    }

    public function testDoesNotCopyAnEmptySourceLabel(): void
    {
        $englishLanguageId = $this->englishLanguageId();
        $reasonId = $this->createReason();
        $this->insertTranslation($reasonId, $englishLanguageId, '');

        (new Migration1794296173RepairOrderReturnReasonTranslations())->update($this->connection);

        static::assertFalse($this->label($reasonId, Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)));
    }

    public function testDoesNotInventLabelsForUntranslatedReasons(): void
    {
        $this->englishLanguageId();
        $reasonId = $this->createReason();

        (new Migration1794296173RepairOrderReturnReasonTranslations())->update($this->connection);

        static::assertFalse($this->label($reasonId, Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)));
    }

    private function englishLanguageId(): string
    {
        $localeId = $this->connection->fetchOne('SELECT `id` FROM `locale` WHERE `code` = :code', ['code' => 'zh-CN']);
        $this->connection->update('language', ['locale_id' => $localeId], ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]);
        $englishLanguageId = $this->fetchLanguageId($this->connection, 'en-GB');
        if ($englishLanguageId !== null) {
            return $englishLanguageId;
        }

        $englishLanguageId = Uuid::randomBytes();
        $englishLocaleId = $this->connection->fetchOne('SELECT `id` FROM `locale` WHERE `code` = :code', ['code' => 'en-GB']);
        $this->connection->insert('language', [
            'id' => $englishLanguageId,
            'name' => 'English',
            'locale_id' => $englishLocaleId,
            'translation_code_id' => $englishLocaleId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $englishLanguageId;
    }

    private function createReason(): string
    {
        $id = Uuid::randomBytes();
        $this->connection->insert('order_return_reason', [
            'id' => $id,
            'position' => 10,
            'active' => true,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $id;
    }

    private function insertTranslation(string $reasonId, string $languageId, string $label): void
    {
        $this->connection->insert('order_return_reason_translation', [
            'order_return_reason_id' => $reasonId,
            'language_id' => $languageId,
            'label' => $label,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function label(string $reasonId, string $languageId): string|false|null
    {
        return $this->connection->fetchOne(
            'SELECT `label` FROM `order_return_reason_translation` WHERE `order_return_reason_id` = :id AND `language_id` = :language',
            ['id' => $reasonId, 'language' => $languageId]
        );
    }
}
