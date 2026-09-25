<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Validation\Requirements;

use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('framework')]
class UnmetRequirement
{
    public function __construct(
        public readonly string $appName,
        public readonly string $requirementName,
        public readonly string $actionableResolution,
    ) {
    }
}
