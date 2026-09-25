<?php declare(strict_types=1);

namespace Shopwell\Core\Content\MailTemplate\Service\Event;

use Monolog\Level;
use Shopwell\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Shopwell\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Event\EventData\ScalarValueType;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\Log\LogAware;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('after-sales')]
class MailErrorEvent extends Event implements LogAware, ScalarValuesAware, FlowEventAware
{
    final public const NAME = 'mail.sent.error';

    private readonly Level $logLevel;

    /**
     * @param array<string, mixed> $templateData
     */
    public function __construct(
        private readonly Context $context,
        ?Level $logLevel = Level::Debug,
        private readonly ?\Throwable $throwable = null,
        private readonly ?string $message = null,
        private readonly ?string $template = null,
        private readonly ?array $templateData = []
    ) {
        $this->logLevel = $logLevel ?? Level::Debug;
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [FlowMailVariables::EVENT_NAME => self::NAME];
    }

    public function getThrowable(): ?\Throwable
    {
        return $this->throwable;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getLogLevel(): Level
    {
        return $this->logLevel;
    }

    public function getLogData(): array
    {
        $logData = [];

        if ($this->getThrowable()) {
            $throwable = $this->getThrowable();
            $logData['exception'] = (string) $throwable;
        }

        if ($this->message) {
            $logData['message'] = $this->message;
        }

        if ($this->template) {
            $logData['template'] = $this->template;
        }

        $logData['eventName'] = null;

        if ($this->templateData) {
            $logData['templateData'] = $this->templateData;
            $logData['eventName'] = $this->templateData['eventName'] ?? null;
        }

        return $logData;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(FlowMailVariables::EVENT_NAME, new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    /**
     * @return mixed[]|null
     */
    public function getTemplateData(): ?array
    {
        return $this->templateData;
    }
}
