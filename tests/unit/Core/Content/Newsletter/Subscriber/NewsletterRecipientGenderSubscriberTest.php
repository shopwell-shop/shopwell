<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Newsletter\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Newsletter\NewsletterEvents;
use Shopwell\Core\Content\Newsletter\Subscriber\NewsletterRecipientGenderSubscriber;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryDefinition;

/**
 * A newsletter recipient without a gender breaks every salutation in the mail templates, so the
 * subscriber fills the "not specified" entry right after the write. It has to leave an explicit value
 * alone and only touch the row that is actually null - overwriting a chosen gender would be worse
 * than the empty field it is meant to fix.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(NewsletterRecipientGenderSubscriber::class)]
class NewsletterRecipientGenderSubscriberTest extends TestCase
{
    public function testTheSubscriberListensToTheRecipientWrite(): void
    {
        static::assertSame(
            [NewsletterEvents::NEWSLETTER_RECIPIENT_WRITTEN_EVENT => 'setDefaultGender'],
            NewsletterRecipientGenderSubscriber::getSubscribedEvents()
        );
    }

    public function testARecipientWithoutAGenderGetsTheNotSpecifiedEntry(): void
    {
        $id = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                static::stringContains('UPDATE `newsletter_recipient` SET `gender` = :notSpecified'),
                [
                    'id' => Uuid::fromHexToBytes($id),
                    'notSpecified' => DictionaryEntryDefinition::NOT_SPECIFIED,
                ]
            );

        $subscriber = new NewsletterRecipientGenderSubscriber($connection);

        $subscriber->setDefaultGender($this->event([
            ['id' => $id, 'email' => 'reader@example.com'],
        ]));
    }

    public function testAnExplicitGenderIsLeftUntouched(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $subscriber = new NewsletterRecipientGenderSubscriber($connection);

        $subscriber->setDefaultGender($this->event([
            ['id' => Uuid::randomHex(), 'gender' => 'female'],
        ]));
    }

    public function testAPayloadWithoutAnIdIsSkippedInsteadOfWritingNull(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $subscriber = new NewsletterRecipientGenderSubscriber($connection);

        $subscriber->setDefaultGender($this->event([
            ['email' => 'reader@example.com'],
        ]));
    }

    public function testOnlyTheRecipientsWithoutAGenderAreUpdated(): void
    {
        $first = Uuid::randomHex();
        $second = Uuid::randomHex();

        $written = [];

        $connection = static::createStub(Connection::class);
        $connection->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $params) use (&$written): int {
                $written[] = $params['id'];

                return 1;
            }
        );

        $subscriber = new NewsletterRecipientGenderSubscriber($connection);
        $subscriber->setDefaultGender($this->event([
            ['id' => $first, 'gender' => null],
            ['id' => $second, 'gender' => 'male'],
        ]));

        static::assertSame([Uuid::fromHexToBytes($first)], $written);
    }

    /**
     * @param list<array<string, mixed>> $payloads
     */
    private function event(array $payloads): EntityWrittenEvent
    {
        $results = [];

        foreach ($payloads as $index => $payload) {
            $results[] = new EntityWriteResult(
                $payload['id'] ?? 'generated-' . $index,
                $payload,
                'newsletter_recipient',
                EntityWriteResult::OPERATION_UPDATE,
            );
        }

        return new EntityWrittenEvent(
            'newsletter_recipient',
            $results,
            Context::createDefaultContext(),
        );
    }
}
