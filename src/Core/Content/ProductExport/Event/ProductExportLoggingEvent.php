<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductExport\Event;

use Monolog\Level;
use Shopwell\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Shopwell\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopwell\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopwell\Core\Framework\Event\EventData\ScalarValueType;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\Event\MailAware;
use Shopwell\Core\Framework\Log\LogAware;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('inventory')]
class ProductExportLoggingEvent extends Event implements LogAware, MailAware, ScalarValuesAware, FlowEventAware
{
    final public const NAME = 'product_export.log';

    private readonly Level $logLevel;

    /**
     * Do not remove initialization, even though the property is set in the constructor.
     * The property is accessed via reflection in some places and is therefore needing a value.
     */
    private string $name = self::NAME;

    /**
     * @internal
     */
    public function __construct(
        private readonly Context $context,
        ?string $name,
        ?Level $logLevel,
        private readonly ?\Throwable $throwable = null
    ) {
        $this->name = $name ?? self::NAME;
        $this->logLevel = $logLevel ?? Level::Debug;
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [FlowMailVariables::EVENT_NAME => $this->name];
    }

    public function getThrowable(): ?\Throwable
    {
        return $this->throwable;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getLogLevel(): Level
    {
        return $this->logLevel;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getLogData(): array
    {
        $logData = [];

        if ($this->getThrowable()) {
            $throwable = $this->getThrowable();
            $logData['exception'] = (string) $throwable;
        }

        return $logData;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(FlowMailVariables::EVENT_NAME, new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getMailStruct(): MailRecipientStruct
    {
        throw new MailEventConfigurationException('Data for mailRecipientStruct not available.', self::class);
    }

    public function getSalesChannelId(): ?string
    {
        return null;
    }
}
