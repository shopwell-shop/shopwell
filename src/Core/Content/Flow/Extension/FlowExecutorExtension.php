<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Extension;

use Shopwell\Core\Content\Flow\Dispatching\StorableFlow;
use Shopwell\Core\Content\Flow\Dispatching\Struct\Flow;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;

/**
 * @public
 *
 * @title Flow Executor Extension
 *
 * @description This extension allows you to control the flow of execution or to pre-load and post-load specific data, enabling added monitoring capabilities or the ability to trigger external services.
 *
 * @extends Extension<void>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final class FlowExecutorExtension extends Extension
{
    public const NAME = 'flow.executor';

    /**
     * @internal shopwell owns the __constructor, but the properties are public API
     */
    public function __construct(
        public readonly Flow $flow,
        public readonly StorableFlow $event
    ) {
    }
}
