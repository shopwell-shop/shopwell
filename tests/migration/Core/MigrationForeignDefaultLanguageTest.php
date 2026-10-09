<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationCollection;
use Shopwell\Core\Framework\Migration\MigrationCollectionLoader;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Maintenance\System\Service\ShopConfigurator;
use Shopwell\Core\Migration\V6_3\Migration1536233560BasicData;
use Shopwell\Core\Migration\V6_3\Migration1589357321AddCountries;
use Shopwell\Core\Migration\V6_4\Migration1636964297AddDefaultTaxRate;
use Shopwell\Tests\Migration\MigrationUntouchedDbTestTrait;

/**
 * @internal
 *
 * MigrationCollection would be the natural covers target, but the migration job scopes
 * the coverage source to the src/*\/Migration directories, so no Framework class is a
 * valid target here; the replayed migrations must not receive smoke-level attribution either.
 */
#[Package('framework')]
#[RunTestsInSeparateProcesses]
#[CoversNothing]
class MigrationForeignDefaultLanguageTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use MigrationUntouchedDbTestTrait;

    public function testFreshInstallationUsesChineseDefaults(): void
    {
        $orgConnection = static::getContainer()->get(Connection::class);
        $orgConnection->rollBack();

        $connection = $this->setupDB($orgConnection);

        foreach ($this->collectMigrations()->getMigrationSteps() as $className => $migration) {
            $migration->update($connection);

            if ($this->isBasicDataMigration($className)) {
                static::assertSame('CNY', $connection->fetchOne(
                    'SELECT iso_code FROM currency WHERE id = :currencyId',
                    ['currencyId' => Uuid::fromHexToBytes(Defaults::CURRENCY)]
                ));
                static::assertSame(1, (int) $connection->fetchOne(
                    'SELECT COUNT(*) FROM tax WHERE name = :name AND tax_rate = 0',
                    ['name' => 'Reduced rate 2']
                ));
            }
        }

        foreach ($this->collectMigrations()->getMigrationSteps() as $migration) {
            $migration->updateDestructive($connection);
        }

        $addCountries = new Migration1589357321AddCountries();
        $addCountries->update($connection);

        static::assertSame('en-GB', $connection->fetchOne(
            'SELECT locale.code
             FROM language
             INNER JOIN locale ON locale.id = language.translation_code_id
             WHERE language.id = :languageId',
            ['languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        ));

        $eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);
        $clock = static::createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable());

        (new ShopConfigurator($connection, $eventDispatcher, $clock))->setDefaultLanguage('zh-CN');

        static::assertSame('zh-CN', $connection->fetchOne(
            'SELECT locale.code
             FROM language
             INNER JOIN locale ON locale.id = language.translation_code_id
             WHERE language.id = :languageId',
            ['languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        ));

        static::assertSame([
            'en-GB' => 'China',
            'zh-CN' => '中国',
        ], $connection->fetchAllKeyValue(
            'SELECT locale.code, country_translation.name
             FROM country
             INNER JOIN country_translation ON country_translation.country_id = country.id
             INNER JOIN language ON language.id = country_translation.language_id
             INNER JOIN locale ON locale.id = language.translation_code_id
             WHERE country.iso3 = :iso3
             ORDER BY locale.code',
            ['iso3' => 'CHN']
        ));
        static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM country WHERE iso3 = :iso3', ['iso3' => 'CHN']));

        static::assertSame([
            'iso_code' => 'CNY',
            'factor' => '1',
        ], $connection->fetchAssociative(
            'SELECT iso_code, factor FROM currency WHERE id = :currencyId',
            ['currencyId' => Uuid::fromHexToBytes(Defaults::CURRENCY)]
        ));
        static::assertSame([
            'en-GB' => 'Chinese Yuan',
            'zh-CN' => '人民币',
        ], $connection->fetchAllKeyValue(
            'SELECT locale.code, currency_translation.name
             FROM currency_translation
             INNER JOIN language ON language.id = currency_translation.language_id
             INNER JOIN locale ON locale.id = language.translation_code_id
             WHERE currency_translation.currency_id = :currencyId
             ORDER BY locale.code',
            ['currencyId' => Uuid::fromHexToBytes(Defaults::CURRENCY)]
        ));

        // Every built-in currency must carry a Chinese name, otherwise the Administration
        // falls back to the english/native name while the default language is zh-CN.
        static::assertSame([
            'CHF' => '瑞士法郎',
            'CNY' => '人民币',
            'CZK' => '捷克克朗',
            'DKK' => '丹麦克朗',
            'EUR' => '欧元',
            'GBP' => '英镑',
            'NOK' => '挪威克朗',
            'PLN' => '波兰兹罗提',
            'SEK' => '瑞典克朗',
            'USD' => '美元',
        ], $connection->fetchAllKeyValue(
            'SELECT currency.iso_code, currency_translation.name
             FROM currency
             INNER JOIN currency_translation ON currency_translation.currency_id = currency.id
             INNER JOIN language ON language.id = currency_translation.language_id
             INNER JOIN locale ON locale.id = language.translation_code_id
             WHERE locale.code = :localeCode
             ORDER BY currency.iso_code',
            ['localeCode' => 'zh-CN']
        ));

        $languageIds = $connection->fetchAllKeyValue(
            'SELECT locale.code, language.id
             FROM language
             INNER JOIN locale ON locale.id = language.translation_code_id'
        );
        static::assertArrayHasKey('en-GB', $languageIds);
        static::assertArrayHasKey('zh-CN', $languageIds);

        // Built-in CMS layouts are shipped in both languages. A slot that was only written
        // for the system language loses its config in the other language, which makes the
        // element render unconfigured.
        static::assertSame(0, (int) $connection->fetchOne(
            'SELECT COUNT(*)
             FROM cms_slot_translation english
             LEFT JOIN cms_slot_translation chinese
                ON chinese.cms_slot_id = english.cms_slot_id
                AND chinese.cms_slot_version_id <=> english.cms_slot_version_id
                AND chinese.language_id = :zhCnLanguageId
             WHERE english.language_id = :enGbLanguageId
               AND chinese.cms_slot_id IS NULL',
            [
                'zhCnLanguageId' => $languageIds['zh-CN'],
                'enGbLanguageId' => $languageIds['en-GB'],
            ]
        ));

        // The sidebar listing layout must not fall back to the second language's original name.
        static::assertSame('含侧栏的默认分类布局', $connection->fetchOne(
            'SELECT chinese.name
             FROM cms_page_translation english
             INNER JOIN cms_page_translation chinese
                ON chinese.cms_page_id = english.cms_page_id
                AND chinese.cms_page_version_id <=> english.cms_page_version_id
                AND chinese.language_id = :zhCnLanguageId
             WHERE english.language_id = :enGbLanguageId
               AND english.name = :name',
            [
                'zhCnLanguageId' => $languageIds['zh-CN'],
                'enGbLanguageId' => $languageIds['en-GB'],
                'name' => 'Default listing layout with sidebar',
            ]
        ));

        static::assertSame(1, (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM tax WHERE name = :name AND tax_rate = 0',
            ['name' => 'Reduced rate 2']
        ));
        $zeroTaxId = $connection->fetchOne(
            'SELECT id FROM tax WHERE name = :name AND tax_rate = 0',
            ['name' => 'Reduced rate 2']
        );
        static::assertIsString($zeroTaxId);

        $defaultTaxConfig = $connection->fetchOne(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :key',
            ['key' => Migration1636964297AddDefaultTaxRate::CONFIG_KEY]
        );
        static::assertIsString($defaultTaxConfig);
        static::assertSame(
            Uuid::fromBytesToHex($zeroTaxId),
            json_decode($defaultTaxConfig, true, 512, \JSON_THROW_ON_ERROR)['_value']
        );

        $orgConnection->beginTransaction();
    }

    /**
     * No en-GB as language, de-LI as Default language and zh-CN as second language
     * All en-GB contents should be written in de-LI and zh-CN contents will be written in zh-CN
     */
    public function testMigrationWithoutEnGb(): void
    {
        $orgConnection = static::getContainer()->get(Connection::class);
        $orgConnection->rollBack();

        $connection = $this->setupDB($orgConnection);

        $migrationCollection = $this->collectMigrations();

        foreach ($migrationCollection->getMigrationSteps() as $_className => $migration) {
            try {
                $migration->update($connection);
            } catch (\Exception $e) {
                static::fail($_className . \PHP_EOL . $e->getMessage());
            }

            if ($this->isBasicDataMigration($_className)) {
                $deLiLocale = $connection->fetchAssociative(
                    'SELECT * FROM `locale` WHERE `code` = :code',
                    [
                        'code' => 'de-LI',
                    ]
                );
                static::assertIsArray($deLiLocale);

                $connection->update(
                    'language',
                    [
                        'name' => 'ForeignLang',
                        'locale_id' => $deLiLocale['id'],
                        'translation_code_id' => $deLiLocale['id'],
                    ],
                    ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
                );
            }
        }
        foreach ($migrationCollection->getMigrationSteps() as $_className => $migration) {
            try {
                $migration->updateDestructive($connection);
            } catch (\Exception $e) {
                static::fail($_className . \PHP_EOL . $e->getMessage());
            }
        }

        $templateDefault = $connection->fetchAssociative(
            'SELECT subject FROM mail_template_translation
                WHERE subject = :subject AND language_id = :languageId',
            [
                'subject' => 'Password recovery',
                'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            ]
        );
        static::assertIsArray($templateDefault);
        static::assertSame('Password recovery', $templateDefault['subject']);

        $zhCnLanguage = $connection->fetchAssociative(
            'SELECT * FROM `language` WHERE `name` = :name',
            [
                'name' => '简体中文',
            ]
        );
        static::assertIsArray($zhCnLanguage);

        $templateZhCn = $connection->fetchAssociative(
            'SELECT subject FROM mail_template_translation
                WHERE subject = :subject AND language_id = :languageId',
            [
                'subject' => '密码找回',
                'languageId' => $zhCnLanguage['id'],
            ]
        );

        static::assertIsArray($templateZhCn);
        static::assertSame('密码找回', $templateZhCn['subject']);

        $orgConnection->beginTransaction();
    }

    /**
     * No En-GB and no zh-CN as language, de-LI as Default language and de-LU as second language
     * All en-GB contents should be written in de-LI and zh-CN contents will not be written
     * de-LI will be left empty
     */
    public function testMigrationWithoutEnGbOrDe(): void
    {
        $orgConnection = static::getContainer()->get(Connection::class);
        $orgConnection->rollBack();

        $connection = $this->setupDB($orgConnection);

        $migrationCollection = $this->collectMigrations();

        $secondLanguage = [];

        foreach ($migrationCollection->getMigrationSteps() as $_className => $migration) {
            try {
                $migration->update($connection);
            } catch (\Exception $e) {
                static::fail($_className . \PHP_EOL . $e->getMessage());
            }

            if ($this->isBasicDataMigration($_className)) {
                $deLiLocale = $connection->fetchAssociative(
                    'SELECT * FROM `locale` WHERE `code` = :code',
                    [
                        'code' => 'de-LI',
                    ]
                );
                static::assertIsArray($deLiLocale);
                $connection->update(
                    'language',
                    [
                        'name' => 'ForeignLang',
                        'locale_id' => $deLiLocale['id'],
                        'translation_code_id' => $deLiLocale['id'],
                    ],
                    ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
                );
                $deLuLocale = $connection->fetchAssociative(
                    'SELECT * FROM `locale` WHERE `code` = :code',
                    [
                        'code' => 'de-LU',
                    ]
                );
                static::assertIsArray($deLuLocale);

                $secondLanguage = $connection->fetchAssociative(
                    'SELECT * FROM `language` WHERE `name` = :name',
                    [
                        'name' => '简体中文',
                    ]
                );
                static::assertIsArray($secondLanguage);

                $connection->update(
                    'language',
                    [
                        'name' => 'OtherForeignLang',
                        'locale_id' => $deLuLocale['id'],
                        'translation_code_id' => $deLuLocale['id'],
                    ],
                    ['name' => '简体中文']
                );
            }
        }

        foreach ($migrationCollection->getMigrationSteps() as $_className => $migration) {
            try {
                $migration->updateDestructive($connection);
            } catch (\Exception $e) {
                static::fail($_className . \PHP_EOL . $e->getMessage());
            }
        }

        $templateDefault = $connection->fetchAssociative(
            'SELECT subject FROM mail_template_translation
                WHERE subject = :subject AND language_id = :languageId',
            [
                'subject' => 'Password recovery',
                'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            ]
        );
        static::assertIsArray($templateDefault);
        static::assertSame('Password recovery', $templateDefault['subject']);

        $templateDeLu = $connection->fetchAssociative(
            'SELECT subject FROM mail_template_translation
                WHERE subject = :subject AND language_id = :languageId',
            [
                'subject' => 'Password recovery',
                'languageId' => $secondLanguage['id'],
            ]
        );
        static::assertFalse($templateDeLu);

        $orgConnection->beginTransaction();
    }

    /**
     * En-GB and zh-CN as language, but de-LI as Default language
     * All en-GB contents should be written in En-GB and de-LI and zh-CN should be filled with zh-CN contents
     */
    public function testMigrationWithEnGbAndDeButDifferentDefault(): void
    {
        $orgConnection = static::getContainer()->get(Connection::class);
        $orgConnection->rollBack();

        $connection = $this->setupDB($orgConnection);

        $migrationCollection = $this->collectMigrations();
        $enGbId = Uuid::randomBytes();

        foreach ($migrationCollection->getMigrationSteps() as $_className => $migration) {
            try {
                $migration->update($connection);
            } catch (\Exception $e) {
                static::fail($_className . \PHP_EOL . $e->getMessage());
            }

            if ($this->isBasicDataMigration($_className)) {
                $deLiLocale = $connection->fetchAssociative(
                    'SELECT * FROM `locale` WHERE `code` = :code',
                    [
                        'code' => 'de-LI',
                    ]
                );
                static::assertIsArray($deLiLocale);
                $connection->update(
                    'language',
                    [
                        'name' => 'ForeignLang',
                        'locale_id' => $deLiLocale['id'],
                        'translation_code_id' => $deLiLocale['id'],
                    ],
                    ['id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
                );
                $enGbLocale = $connection->fetchAssociative(
                    'SELECT * FROM `locale` WHERE `code` = :code',
                    [
                        'code' => 'en-GB',
                    ]
                );
                static::assertIsArray($enGbLocale);

                $connection->insert(
                    'language',
                    [
                        'id' => $enGbId,
                        'name' => 'English',
                        'locale_id' => $enGbLocale['id'],
                        'translation_code_id' => $enGbLocale['id'],
                        'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ]
                );
            }
        }

        foreach ($migrationCollection->getMigrationSteps() as $_className => $migration) {
            try {
                $migration->updateDestructive($connection);
            } catch (\Exception $e) {
                static::fail($_className . \PHP_EOL . $e->getMessage());
            }
        }

        $templateDefault = $connection->fetchAssociative(
            'SELECT subject FROM mail_template_translation
                WHERE subject = :subject AND language_id = :languageId',
            [
                'subject' => 'Password recovery',
                'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            ]
        );
        static::assertIsArray($templateDefault);
        static::assertSame('Password recovery', $templateDefault['subject']);

        $templateEnGb = $connection->fetchAssociative(
            'SELECT subject FROM mail_template_translation
                WHERE subject = :subject AND language_id = :languageId',
            [
                'subject' => 'Password recovery',
                'languageId' => $enGbId,
            ]
        );
        static::assertIsArray($templateEnGb);
        static::assertSame('Password recovery', $templateEnGb['subject']);

        $orgConnection->beginTransaction();
    }

    private function isBasicDataMigration(string $className): bool
    {
        return $className === Migration1536233560BasicData::class;
    }

    private function collectMigrations(): MigrationCollection
    {
        return static::getContainer()
            ->get(MigrationCollectionLoader::class)
            ->collectAllForVersion(
                static::getContainer()->getParameter('kernel.shopwell_version'),
                MigrationCollectionLoader::VERSION_SELECTION_ALL
            );
    }

    private function setupDB(Connection $orgConnection): Connection
    {
        // Be sure that we are on the no migrations db
        static::assertStringContainsString('_no_migrations', $this->databaseName, 'Wrong DB ' . $this->databaseName);

        $orgConnection->executeStatement('DROP DATABASE IF EXISTS `' . $this->databaseName . '`');

        $orgConnection->executeStatement('CREATE DATABASE `' . $this->databaseName . '` DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_unicode_ci');

        $connection = new Connection(
            array_merge(
                $orgConnection->getParams(),
                [
                    'dbname' => $this->databaseName,
                ]
            ),
            $orgConnection->getDriver(),
            $orgConnection->getConfiguration(),
        );

        $dumpFile = file_get_contents(__DIR__ . '/../../../src/Core/schema.sql');
        static::assertIsString($dumpFile);

        $connection->executeStatement($dumpFile);

        return $connection;
    }
}
