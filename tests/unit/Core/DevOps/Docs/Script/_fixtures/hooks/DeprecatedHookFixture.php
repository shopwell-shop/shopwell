<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\DevOps\Docs\Script\_fixtures\hooks;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Script\Execution\DeprecatedHook;
use Shopwell\Core\Framework\Script\Execution\Hook;

/**
 * Triggered when something deprecated happens.
 *
 * @hook-use-case app_lifecycle
 *
 * @since 6.3.0.0
 */
class DeprecatedHookFixture extends Hook implements DeprecatedHook
{
    final public const HOOK_NAME = 'deprecated-hook';

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

    public static function getDeprecationNotice(): string
    {
        return 'Use something else instead.';
    }
}
