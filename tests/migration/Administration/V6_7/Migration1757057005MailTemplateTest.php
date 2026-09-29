<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Administration\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopwell\Administration\Migration\V6_7\Migration1757057005MailTemplate;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Migration\Traits\MailUpdate;
use Shopwell\Tests\Migration\MailTemplateMigrationTestCase;
use Shopwell\Tests\Migration\Translations;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1757057005MailTemplate::class)]
class Migration1757057005MailTemplateTest extends MailTemplateMigrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1757057005, (new Migration1757057005MailTemplate())->getCreationTimestamp());
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1757057005MailTemplate();
        static::assertSame(1757057005, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        // prepare the test
        $expectedTranslations = new Translations();
        $expectedTranslations->setEnPlain('en plain text');
        $expectedTranslations->setEnHtml('<h1>en HTML</h1>');
        $expectedTranslations->setZhPlain('de plain text');
        $expectedTranslations->setZhHtml('<h1>de HTML</h1>');

        $mailTranslations = new MailUpdate(
            'admin_sso_user_invite',
            $expectedTranslations->getEnPlain(),
            $expectedTranslations->getEnHtml(),
            $expectedTranslations->getZhPlain(),
            $expectedTranslations->getZhHtml(),
        );

        $this->updateMail($mailTranslations, $this->connection);
        $currentTranslations = $this->getMailTemplateTranslations($mailTranslations->getType());

        $this->assertMailTemplateTranslations($expectedTranslations, $currentTranslations->translations);

        // Start with the test
        $migration = new Migration1757057005MailTemplate();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $dir = realpath(__DIR__ . '/../../../../src/Administration/Migration/V6_7/assets');
        $expectedTranslations = new Translations();
        $expectedTranslations->setEnPlain($dir . '/sso_user_invitation_mail.en-GB.txt');
        $expectedTranslations->setEnHtml($dir . '/sso_user_invitation_mail.en-GB.html.twig');
        $expectedTranslations->setZhPlain($dir . '/sso_user_invitation_mail.zh-CN.txt');
        $expectedTranslations->setZhHtml($dir . '/sso_user_invitation_mail.zh-CN.html.twig');

        $currentTranslations = $this->getMailTemplateTranslations($mailTranslations->getType());

        $this->assertMailTemplateTranslations($expectedTranslations, $currentTranslations->translations);
    }
}
