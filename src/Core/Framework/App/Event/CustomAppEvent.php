<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Event;

use Shopwell\Core\Content\Flow\Dispatching\Aware\CustomAppAware;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class CustomAppEvent extends Event implements CustomAppAware, FlowEventAware
{
    /**
     * @param array<string, mixed>|null $appData
     */
    public function __construct(
        private readonly string $name,
        private readonly ?array $appData,
        private readonly Context $context
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCustomAppData(): ?array
    {
        return $this->appData;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
