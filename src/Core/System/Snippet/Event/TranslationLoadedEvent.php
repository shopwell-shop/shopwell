<?php declare(strict_types=1);

namespace Shopwell\Core\System\Snippet\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Log\Package;

/**
 * Dispatched after the translations for a locale have been downloaded and installed.
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class TranslationLoadedEvent implements ShopwellEvent
{
    public function __construct(
        private readonly string $locale,
        private readonly Context $context,
    ) {
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
