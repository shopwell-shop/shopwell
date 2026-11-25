<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Effect;

use Shopwell\Core\Framework\Log\Package;

/**
 * Select published output ports; null requests the unambiguous single outgoing connection.
 */
#[Package('framework')]
final readonly class AdvanceEffect
{
    /**
     * @var list<string>|null
     */
    public ?array $outputPorts;

    /**
     * @param list<string>|null $outputPorts
     */
    public function __construct(?array $outputPorts = null)
    {
        $this->outputPorts = $outputPorts === null ? null : array_map(static fn (string $port): string => $port, $outputPorts);
    }
}
