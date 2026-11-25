<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Effect;

use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\NodeEffectType;
use Shopwell\Core\Framework\Log\Package;

/**
 * Activate an explicit set of published exits inside a child scope with one return boundary.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class OpenScopeEffect implements NodeEffect
{
    /**
     * @var list<string>
     */
    public array $outputPorts;

    /**
     * @param list<string> $outputPorts
     */
    public function __construct(array $outputPorts, public string $joinNodeId)
    {
        $this->outputPorts = array_map(static fn (string $port): string => $port, $outputPorts);
    }

    public function getType(): NodeEffectType
    {
        return NodeEffectType::OpenScope;
    }
}
