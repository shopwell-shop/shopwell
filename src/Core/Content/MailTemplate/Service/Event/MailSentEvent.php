<?php declare(strict_types=1);

namespace Shopwell\Core\Content\MailTemplate\Service\Event;

use Monolog\Level;
use Shopwell\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Shopwell\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopwell\Core\Content\Mail\Service\AbstractMailFactory;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\EventData\ArrayType;
use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Event\EventData\ScalarValueType;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\Log\LogAware;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @phpstan-import-type MailNameCombination from AbstractMailFactory
 * @phpstan-import-type Contents from AbstractMailFactory
 */
#[Package('after-sales')]
class MailSentEvent extends Event implements LogAware, ScalarValuesAware, FlowEventAware
{
    final public const EVENT_NAME = 'mail.sent';

    /**
     * @param MailNameCombination $recipients
     * @param Contents $contents
     */
    public function __construct(
        private readonly string $subject,
        private readonly array $recipients,
        private readonly array $contents,
        private readonly Context $context,
        private readonly ?string $eventName = null
    ) {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(FlowMailVariables::SUBJECT, new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add(FlowMailVariables::CONTENTS, new ScalarValueType(ScalarValueType::TYPE_STRING), [EventDataCollection::HIDDEN_FROM_WEBHOOK => true])
            ->add(FlowMailVariables::RECIPIENTS, new ArrayType(new ScalarValueType(ScalarValueType::TYPE_STRING)));
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [
            FlowMailVariables::SUBJECT => $this->subject,
            FlowMailVariables::CONTENTS => $this->contents,
            FlowMailVariables::RECIPIENTS => $this->recipients,
        ];
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * @return Contents
     */
    public function getContents(): array
    {
        return $this->contents;
    }

    /**
     * @return MailNameCombination
     */
    public function getRecipients(): array
    {
        return $this->recipients;
    }

    public function getLogData(): array
    {
        return [
            'eventName' => $this->eventName,
            'subject' => $this->subject,
            'recipients' => $this->recipients,
            'contents' => $this->contents,
        ];
    }

    public function getLogLevel(): Level
    {
        return Level::Info;
    }
}
