<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Mail\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Mail\Service\Mail;
use Shopwell\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopwell\Core\Content\MailTemplate\MailTemplateEntity;
use Shopwell\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Mail::class)]
class MailTest extends TestCase
{
    public function testMailInstance(): void
    {
        $mail = new Mail();
        $mail->addAttachmentUrl('foobar');

        static::assertSame(['foobar'], $mail->getAttachmentUrls());

        $attachmentsConfig = new MailAttachmentsConfig(
            Context::createDefaultContext(),
            new MailTemplateEntity(),
            new MailSendSubscriberConfig(false),
            [],
            Uuid::randomHex()
        );

        $mail->setMailAttachmentsConfig($attachmentsConfig);

        static::assertSame($attachmentsConfig, $mail->getMailAttachmentsConfig());
    }
}
