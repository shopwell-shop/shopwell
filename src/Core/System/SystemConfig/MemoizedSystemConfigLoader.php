<?php declare(strict_types=1);

namespace Shopwell\Core\System\SystemConfig;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SystemConfig\Store\MemoizedSystemConfigStore;

#[Package('framework')]
class MemoizedSystemConfigLoader extends AbstractSystemConfigLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractSystemConfigLoader $decorated,
        private readonly MemoizedSystemConfigStore $memoizedSystemConfigStore
    ) {
    }

    public function getDecorated(): AbstractSystemConfigLoader
    {
        return $this->decorated;
    }

    public function load(?string $salesChannelId): array
    {
        $config = $this->memoizedSystemConfigStore->getConfig($salesChannelId);

        if ($config !== null) {
            return $config;
        }

        $config = $this->getDecorated()->load($salesChannelId);
        $this->memoizedSystemConfigStore->setConfig($salesChannelId, $config);

        return $config;
    }
}
