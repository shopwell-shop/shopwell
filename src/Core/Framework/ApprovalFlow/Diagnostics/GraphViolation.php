<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Diagnostics;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class GraphViolation
{
    public function __construct(
        public ViolationCategory $category,
        public string $code,
        public string $path,
        public string $message,
    ) {
    }
}
