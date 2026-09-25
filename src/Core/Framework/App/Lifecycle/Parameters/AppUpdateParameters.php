<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Lifecycle\Parameters;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore This is a simple DTO and does not require tests
 */
#[Package('framework')]
final readonly class AppUpdateParameters
{
    public function __construct(
        public bool $acceptPermissions = true
    ) {
    }
}
