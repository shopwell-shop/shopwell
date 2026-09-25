<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Flow\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Flow\Dispatching\StorableFlow;
use Shopwell\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Shopwell\Core\Content\MailTemplate\MailTemplateEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\DataBag;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(FlowSendMailActionEvent::class)]
class FlowSendMailActionEventTest extends TestCase
{
    public function testEventConstructorParameters(): void
    {
        $context = Context::createDefaultContext();
        $flow = new StorableFlow('foo', $context);

        $expectDataBag = new DataBag(['data' => 'data']);
        $mailTemplate = new MailTemplateEntity();

        $event = new FlowSendMailActionEvent($expectDataBag, $mailTemplate, $flow);

        static::assertSame($context, $event->getContext());
        static::assertSame($expectDataBag, $event->getDataBag());
        static::assertSame($mailTemplate, $event->getMailTemplate());
        static::assertSame($flow, $event->getStorableFlow());
    }
}
