<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\ContactForm\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopwell\Core\Content\Flow\Dispatching\StorableFlow;
use Shopwell\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\DataBag;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ContactFormEvent::class)]
class ContactFormEventTest extends TestCase
{
    public function testScalarValuesCorrectly(): void
    {
        $event = new ContactFormEvent(
            Context::createDefaultContext(),
            'sales-channel-id',
            new MailRecipientStruct(['foo' => 'bar']),
            new DataBag(['foo' => 'bar', 'bar' => 'baz'])
        );

        $storer = new ScalarValuesStorer();

        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);

        $storer->restore($flow);

        static::assertArrayHasKey('contactFormData', $flow->data());
        static::assertSame(['foo' => 'bar', 'bar' => 'baz'], $flow->data()['contactFormData']);
    }

    public function testExposesItsPayload(): void
    {
        $context = Context::createDefaultContext();
        $recipients = new MailRecipientStruct(['foo' => 'bar']);

        $event = new ContactFormEvent($context, 'sales-channel-id', $recipients, new DataBag(['subject' => 'question']));

        static::assertSame(ContactFormEvent::EVENT_NAME, $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame($recipients, $event->getMailStruct());
        static::assertSame('sales-channel-id', $event->getSalesChannelId());
        static::assertSame(['subject' => 'question'], $event->getContactFormData());
        static::assertSame(['contactFormData'], array_keys(ContactFormEvent::getAvailableData()->toArray()));
    }
}
