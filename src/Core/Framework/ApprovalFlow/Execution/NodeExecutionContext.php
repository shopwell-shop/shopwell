<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution;

use Shopwell\Core\Framework\Log\Package;

/**
 * Immutable business evidence supplied by the runtime, never by an editor command.
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalRoutingExtensionTest
 */
#[Package('framework')]
final readonly class NodeExecutionContext
{
    public function __construct(public string $formJson = '{}')
    {
    }
}
