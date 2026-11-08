<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Mail\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Mail\Service\Mail;
use Shopwell\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopwell\Core\Content\MailTemplate\MailTemplateEntity;
use Shopwell\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Annotation\DisabledFeatures;
use Shopwell\Core\Test\Assert\Serialization;

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

        $restored = Serialization::assertRoundTrip($mail);
        static::assertEquals($attachmentsConfig, $restored->getMailAttachmentsConfig());
        static::assertSame(['foobar'], $restored->getAttachmentUrls());
    }

    #[DataProvider('sensitivityProvider')]
    public function testSensitivitySurvivesSerializationAndCloning(bool $sensitive): void
    {
        $mail = (new Mail())->subject('Subject')->from('shop@example.com')->to('customer@example.com')->text('Body');
        $mail->setSensitive($sensitive);

        static::assertTrue(array_is_list($mail->__serialize()));
        static::assertSame([null, [], $sensitive], \array_slice($mail->__serialize(), -3));
        static::assertSame($sensitive, (clone $mail)->isSensitive());
        static::assertSame($sensitive, Serialization::assertRoundTrip($mail)->isSensitive());
        static::assertStringNotContainsString('X-Shopwell-Sensitive', $mail->toString());
    }

    /**
     * @return \Generator<string, array{bool}>
     */
    public static function sensitivityProvider(): \Generator
    {
        yield 'ordinary Shopwell mail' => [false];
        yield 'sensitive Shopwell mail' => [true];
    }

    /**
     * @deprecated tag:v6.8.0 - Removed with support for the legacy serialized mail format.
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testPreviouslyQueuedMailWithoutClassificationKeepsDefault(): void
    {
        $mail = (new Mail())->subject('Subject')->text('Body')->addAttachmentUrl('attachment.pdf');
        $data = $mail->__serialize();
        array_pop($data);

        $restored = new Mail();
        $restored->__unserialize($data);

        static::assertFalse($restored->isSensitive());
        static::assertSame('Body', $restored->getTextBody());
        static::assertSame(['attachment.pdf'], $restored->getAttachmentUrls());
    }

    /**
     * @deprecated tag:v6.8.0 - Removed with the legacy unserialization path.
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    #[DataProvider('sensitivityProvider')]
    public function testSensitivitySurvivesSerializationBeforeNextMajor(bool $sensitive): void
    {
        $mail = (new Mail())->text('Body')->addAttachmentUrl('attachment.pdf')->setSensitive($sensitive);

        $restored = Serialization::assertRoundTrip($mail);

        static::assertSame($sensitive, $restored->isSensitive());
        static::assertSame('Body', $restored->getTextBody());
        static::assertSame(['attachment.pdf'], $restored->getAttachmentUrls());
    }
}
