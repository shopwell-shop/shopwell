<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Plugin\Context;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationCollection;
use Shopwell\Core\Framework\Plugin;

#[Package('framework')]
class UpdateContext extends InstallContext
{
    public function __construct(
        Plugin $plugin,
        Context $context,
        string $currentShopwellVersion,
        string $currentPluginVersion,
        MigrationCollection $migrationCollection,
        private readonly string $updatePluginVersion
    ) {
        parent::__construct($plugin, $context, $currentShopwellVersion, $currentPluginVersion, $migrationCollection);
    }

    public function getUpdatePluginVersion(): string
    {
        return $this->updatePluginVersion;
    }
}
