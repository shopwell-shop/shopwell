<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Migration\Traits\UpdateMailTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
abstract class MailTemplateMigrationTestCase extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use UpdateMailTrait;

    public const LANGUAGE_NAME_EN = 'English';
    public const LANGUAGE_NAME_ZH = '简体中文';

    protected Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function assertMailTemplateTranslations(Translations $expected, Translations $current): void
    {
        $fileSystem = new Filesystem();
        if ($fileSystem->exists((string) $expected->getEnPlain())) {
            $expected->setEnPlain($fileSystem->readFile((string) $expected->getEnPlain()));
        }

        if ($fileSystem->exists((string) $expected->getEnHtml())) {
            $expected->setEnHtml($fileSystem->readFile((string) $expected->getEnHtml()));
        }

        if ($fileSystem->exists((string) $expected->getZhPlain())) {
            $expected->setZhPlain($fileSystem->readFile((string) $expected->getZhPlain()));
        }

        if ($fileSystem->exists((string) $expected->getZhHtml())) {
            $expected->setZhHtml($fileSystem->readFile((string) $expected->getZhHtml()));
        }

        static::assertSame($expected->getEnPlain(), $current->getEnPlain());
        static::assertSame($expected->getEnHtml(), $current->getEnHtml());
        static::assertSame($expected->getZhPlain(), $current->getZhPlain());
        static::assertSame($expected->getZhHtml(), $current->getZhHtml());
    }

    public function getMailTemplateTranslations(string $mailTemplateTypeTechnicalName): MailTemplateTranslationResult
    {
        $mailTemplateTypeId = $this->getMailTemplateTypeId($mailTemplateTypeTechnicalName);
        $mailTemplateId = $this->getMailTemplateId($mailTemplateTypeId);

        $translations = $this->getTranslations($mailTemplateId);

        return new MailTemplateTranslationResult(
            $mailTemplateTypeTechnicalName,
            $mailTemplateTypeId,
            $mailTemplateId,
            $translations
        );
    }

    protected function getTranslations(string $mailTemplateId): Translations
    {
        $languages = $this->connection->fetchAllKeyValue('SELECT `name`, `id` FROM `language` WHERE `name` IN ("简体中文", "English")');

        $translationArray = $this->connection->fetchAllAssociativeIndexed(
            'SELECT `language_id`, `content_html`, `content_plain`  FROM `mail_template_translation` WHERE `mail_template_id` = :mailTemplateId',
            [
                'mailTemplateId' => $mailTemplateId,
            ]
        );

        $translations = new Translations();
        foreach ($languages as $languageName => $languageId) {
            if ($languageName === self::LANGUAGE_NAME_EN) {
                $translations->setEnPlain($translationArray[$languageId]['content_plain']);
                $translations->setEnHtml($translationArray[$languageId]['content_html']);
            }

            if ($languageName === self::LANGUAGE_NAME_ZH) {
                $translations->setZhPlain($translationArray[$languageId]['content_plain']);
                $translations->setZhHtml($translationArray[$languageId]['content_html']);
            }
        }

        return $translations;
    }

    protected function getMailTemplateTypeId(string $mailTemplateTypeTechnicalName): string
    {
        $result = $this->connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => $mailTemplateTypeTechnicalName]
        );

        if (!$result) {
            static::fail('Could not find mail template type id. Check the given technical_name.');
        }

        return $result;
    }

    protected function getMailTemplateId(string $mailTemplateTypeId): string
    {
        $result = $this->connection->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :mailTemplateTypeId AND system_default = 1',
            ['mailTemplateTypeId' => $mailTemplateTypeId]
        );

        if (!$result) {
            static::fail('Could not find mail template id');
        }

        return $result;
    }
}
