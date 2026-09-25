<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @final
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PostAppDeletedEvent extends Event implements ShopwellEvent
{
    final public const NAME = 'app.deleted.post';

    public function __construct(
        public readonly string $appName,
        public readonly string $sourceType,
        private readonly Context $context,
        public readonly bool $keepUserData = false
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
