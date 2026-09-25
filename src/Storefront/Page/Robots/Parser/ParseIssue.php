<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Robots\Parser;

use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore Simple DTO with no business logic
 */
#[Package('discovery')]
class ParseIssue
{
    public function __construct(
        public readonly int $lineNumber,
        public readonly string $lineContent,
        public readonly string $reason,
        public readonly ParseIssueSeverity $severity
    ) {
    }
}
