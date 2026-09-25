<?php declare(strict_types=1);

namespace Shopwell\Core\Content\MailTemplate\Service\Event;

use Monolog\Level;
use Shopwell\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Shopwell\Core\Content\Flow\Dispatching\Aware\MessageAware;
use Shopwell\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\EventData\ArrayType;
use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Event\EventData\ObjectType;
use Shopwell\Core\Framework\Event\EventData\ScalarValueType;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\Log\LogAware;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Webhook\NotHookable;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('after-sales')]
#[NotHookable]
class MailBeforeSentEvent extends Event implements LogAware, MessageAware, ScalarValuesAware, FlowEventAware
{
    final public const EVENT_NAME = 'mail.after.create.message';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly array $data,
        private readonly Email $message,
        private readonly Context $context,
        private readonly ?string $eventName = null
    ) {
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [FlowMailVariables::DATA => $this->data];
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(FlowMailVariables::DATA, new ArrayType(new ScalarValueType(ScalarValueType::TYPE_STRING)))
            ->add(MessageAware::MESSAGE, new ObjectType());
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getMessage(): Email
    {
        return $this->message;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getLogData(): array
    {
        $data = $this->data;
        unset($data['binAttachments']);

        return [
            'data' => $data,
            'eventName' => $this->eventName,
            'message' => $this->message,
        ];
    }

    public function getLogLevel(): Level
    {
        return Level::Info;
    }
}
