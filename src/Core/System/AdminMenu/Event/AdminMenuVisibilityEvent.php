<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Narrows navigation for runtime conditions such as tenant features or licensing.
 * Hiding a declared parent also hides its descendants. This does not grant access:
 * role selections and route/API ACL remain authoritative.
 *
 * @final
 */
#[Package('fundamentals@framework')]
class AdminMenuVisibilityEvent extends Event
{
    /**
     * @var array<string, true>
     */
    private array $hiddenKeys = [];

    public function __construct(
        private readonly Context $context,
        private readonly ?string $locale,
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function hide(string ...$internalKeys): void
    {
        foreach ($internalKeys as $key) {
            $this->hiddenKeys[$key] = true;
        }
    }

    public function isHidden(string $internalKey): bool
    {
        return isset($this->hiddenKeys[$internalKey]);
    }
}
