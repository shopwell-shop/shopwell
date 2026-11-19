<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Graph;

use Shopwell\Core\Framework\Log\Package;

/**
 * Immutable draft wire value, never an executable or published graph.
 * The codec owns admission; consumers cannot mutate its nested JSON by reference.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class DraftGraph
{
    public function __construct(public string $json)
    {
    }
}
