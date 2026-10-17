<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopwell\Core\Content\MailTemplate\MailTemplateTypes;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Migration\Traits\MailUpdate;
use Shopwell\Core\Migration\V6_7\Migration1791377945FixLineItemImageInOrderConfirmationMail;
use Shopwell\Tests\Migration\MailTemplateMigrationTestCase;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1791377945FixLineItemImageInOrderConfirmationMail::class)]
class Migration1791377945FixLineItemImageInOrderConfirmationMailTest extends MailTemplateMigrationTestCase
{
    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1791377945, (new Migration1791377945FixLineItemImageInOrderConfirmationMail())->getCreationTimestamp());
    }

    public function testMigrationReappliesOrderConfirmationMailTemplate(): void
    {
        $migration = new Migration1791377945FixLineItemImageInOrderConfirmationMail();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $expected = new MailUpdate(MailTemplateTypes::MAILTYPE_ORDER_CONFIRM);
        $expected->loadByDirectoryName('order_confirmation_mail');

        $translations = $this->getMailTemplateTranslations(MailTemplateTypes::MAILTYPE_ORDER_CONFIRM)->translations;

        static::assertSame($expected->getEnPlain(), $translations->getEnPlain());
        static::assertSame($expected->getEnHtml(), $translations->getEnHtml());
        static::assertSame($expected->getZhPlain(), $translations->getZhPlain());
        static::assertSame($expected->getZhHtml(), $translations->getZhHtml());
    }
}
