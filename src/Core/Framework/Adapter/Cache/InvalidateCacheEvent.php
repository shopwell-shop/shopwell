<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Adapter\Cache;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class InvalidateCacheEvent extends Event
{
    /**
     * @param array<string> $keys
     */
    public function __construct(protected array $keys)
    {
    }

    /**
     * @return array<string>
     */
    public function getKeys(): array
    {
        return $this->keys;
    }
}
