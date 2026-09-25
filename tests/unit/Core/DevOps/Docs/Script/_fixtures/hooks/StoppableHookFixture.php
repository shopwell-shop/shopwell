<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\DevOps\Docs\Script\_fixtures\hooks;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Script\Execution\Awareness\StoppableHook;
use Shopwell\Core\Framework\Script\Execution\Hook;

/**
 * Triggered when something stoppable happens.
 *
 * @hook-use-case cart_manipulation
 *
 * @since 6.5.0.0
 */
class StoppableHookFixture extends Hook implements StoppableHook
{
    final public const HOOK_NAME = 'stoppable-hook';

    private bool $stopped = false;

    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public static function getServiceIds(): array
    {
        return [];
    }

    public function isPropagationStopped(): bool
    {
        return $this->stopped;
    }

    public function stopPropagation(): void
    {
        $this->stopped = true;
    }
}
