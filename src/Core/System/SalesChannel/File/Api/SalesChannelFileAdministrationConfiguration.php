<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\File\Api;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class SalesChannelFileAdministrationConfiguration
{
    /**
     * @param array<string, string> $templateOverrides
     */
    public function __construct(
        public string $id,
        public bool $enabled,
        public array $templateOverrides,
    ) {
    }
}
