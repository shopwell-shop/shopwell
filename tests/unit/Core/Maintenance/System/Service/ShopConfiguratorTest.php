<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Maintenance\System\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Maintenance\MaintenanceException;
use Shopwell\Core\Maintenance\System\Service\ShopConfigurator;
use Shopwell\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShopConfigurator::class)]
class ShopConfiguratorTest extends TestCase
{
    private ShopConfigurator $shopConfigurator;

    private Connection&MockObject $connection;

    private CollectingEventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->shopConfigurator = new ShopConfigurator($this->connection, $this->eventDispatcher);
    }

    public function testUpdateBasicInformation(): void
    {
        $this->connection->expects($this->exactly(2))->method('executeStatement')->willReturnCallback(static function (string $sql, array $parameters): int {
            static::assertSame(
                'INSERT INTO `system_config` (`id`, `configuration_key`, `configuration_value`, `sales_channel_id`, `created_at`)
            VALUES (:id, :key, :value, NULL, NOW())
            ON DUPLICATE KEY UPDATE
                `configuration_value` = :value,
                `updated_at` = NOW()',
                trim($sql)
            );

            static::assertArrayHasKey('id', $parameters);
            static::assertArrayHasKey('key', $parameters);
            static::assertArrayHasKey('value', $parameters);

            if ($parameters['key'] === 'core.basicInformation.shopName') {
                static::assertSame('{"_value":"test-shop"}', $parameters['value']);
            } else {
                static::assertSame('core.basicInformation.email', $parameters['key']);
                static::assertSame('{"_value":"shop@test.com"}', $parameters['value']);
            }

            return 1;
        });

        $this->shopConfigurator->updateBasicInformation('test-shop', 'shop@test.com');
    }

    public function testSetDefaultLanguageWithoutCurrentLocale(): void
    {
        $this->expectExceptionObject(MaintenanceException::shopConfigurationNotValid('Default language locale not found'));

        $this->connection->expects($this->once())->method('fetchAssociative')->willReturnCallback(static function (string $sql, array $parameters): false {
            static::assertSame(
                'SELECT locale.id, locale.code
             FROM language
             INNER JOIN locale ON translation_code_id = locale.id
             WHERE language.id = :languageId',
                trim($sql)
            );

            static::assertArrayHasKey('languageId', $parameters);
            static::assertSame(Defaults::LANGUAGE_SYSTEM, Uuid::fromBytesToHex($parameters['languageId']));

            return false;
        });

        try {
            $this->shopConfigurator->setDefaultLanguage('vi-VN');
        } finally {
            static::assertCount(0, $this->eventDispatcher->getEvents());
        }
    }

    public function testSetDefaultLanguageMatchCurrentLocale(): void
    {
        $currentLocaleId = Uuid::randomBytes();

        $this->connection->expects($this->once())->method('fetchAssociative')->willReturnCallback(static function (string $sql, array $parameters) use ($currentLocaleId) {
            static::assertSame(
                'SELECT locale.id, locale.code
             FROM language
             INNER JOIN locale ON translation_code_id = locale.id
             WHERE language.id = :languageId',
                trim($sql)
            );

            static::assertArrayHasKey('languageId', $parameters);
            static::assertSame(Defaults::LANGUAGE_SYSTEM, Uuid::fromBytesToHex($parameters['languageId']));

            return ['id' => $currentLocaleId, 'code' => 'vi-VN'];
        });

        $this->connection->expects($this->once())->method('fetchOne')->willReturnCallback(static function (string $sql, array $parameters) use ($currentLocaleId) {
            static::assertSame(
                'SELECT locale.id FROM  locale WHERE LOWER(locale.code) = LOWER(:iso)',
                trim($sql)
            );

            static::assertArrayHasKey('iso', $parameters);
            static::assertSame('vi-VN', $parameters['iso']);

            return $currentLocaleId;
        });

        $this->connection->expects($this->never())->method('executeStatement');
        $this->connection->expects($this->never())->method('prepare');

        $this->shopConfigurator->setDefaultLanguage('vi_VN');
    }

    public function testSetDefaultLanguageWithUnavailableIso(): void
    {
        $this->expectExceptionObject(MaintenanceException::shopConfigurationNotValid('Locale with iso-code "vi-VN" not found'));

        $currentLocaleId = Uuid::randomBytes();

        $this->connection->expects($this->once())->method('fetchAssociative')->willReturnCallback(static function (string $sql, array $parameters) use ($currentLocaleId) {
            static::assertSame(
                'SELECT locale.id, locale.code
             FROM language
             INNER JOIN locale ON translation_code_id = locale.id
             WHERE language.id = :languageId',
                trim($sql)
            );

            static::assertArrayHasKey('languageId', $parameters);
            static::assertSame(Defaults::LANGUAGE_SYSTEM, Uuid::fromBytesToHex($parameters['languageId']));

            return ['id' => $currentLocaleId, 'code' => 'vi-VN'];
        });

        $this->connection->expects($this->once())->method('fetchOne')->willReturnCallback(static function (string $sql, array $parameters) {
            static::assertSame(
                'SELECT locale.id FROM  locale WHERE LOWER(locale.code) = LOWER(:iso)',
                trim($sql)
            );

            static::assertArrayHasKey('iso', $parameters);
            static::assertSame('vi-VN', $parameters['iso']);

            return null;
        });

        $this->shopConfigurator->setDefaultLanguage('vi_VN');
    }
}
