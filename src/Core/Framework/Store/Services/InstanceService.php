<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Store\Services;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
class InstanceService
{
    public function __construct(
        private readonly string $shopwareVersion,
        private readonly ?string $instanceId
    ) {
    }

    public function getShopwellVersion(): string
    {
        if (str_ends_with($this->shopwareVersion, '-dev')) {
            return '___VERSION___';
        }

        return $this->shopwareVersion;
    }

    public function getInstanceId(): ?string
    {
        return $this->instanceId;
    }
}
