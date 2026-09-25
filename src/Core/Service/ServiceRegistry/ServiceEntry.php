<?php declare(strict_types=1);

namespace Shopwell\Core\Service\ServiceRegistry;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
readonly class ServiceEntry
{
    public function __construct(
        public string $name,
        public string $description,
        public string $host,
        public string $appEndpoint,
        public bool $activateOnInstall = true,
    ) {
    }
}
