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
        private readonly string $shopwellVersion,
        private readonly ?string $instanceId
    ) {
    }

    public function getShopwellVersion(): string
    {
        return $this->shopwellVersion;
    }

    public function getInstanceId(): ?string
    {
        return $this->instanceId;
    }
}
