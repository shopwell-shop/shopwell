<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Diagnostics;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class GraphDiagnosticsReport
{
    /**
     * @param list<GraphViolation> $violations
     */
    public function __construct(public array $violations)
    {
    }

    public function isWritable(): bool
    {
        foreach ($this->violations as $violation) {
            if ($violation->category !== ViolationCategory::Draft) {
                return false;
            }
        }

        return true;
    }
}
