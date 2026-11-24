<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Events;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Lets extensions change the allowed file types of product documents.
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
final class ProductDocumentAllowedExtensionsEvent extends Event implements ShopwellEvent
{
    /**
     * @param list<string> $allowedExtensions
     */
    public function __construct(
        private array $allowedExtensions,
        private readonly Context $context,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getAllowedExtensions(): array
    {
        return $this->allowedExtensions;
    }

    /**
     * @param list<string> $allowedExtensions
     */
    public function setAllowedExtensions(array $allowedExtensions): void
    {
        $this->allowedExtensions = $allowedExtensions;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
