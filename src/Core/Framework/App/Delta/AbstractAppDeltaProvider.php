<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Delta;

use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
abstract class AbstractAppDeltaProvider
{
    abstract public function getDeltaName(): string;

    /**
     * @return array<array-key, mixed>
     */
    abstract public function getReport(Manifest $manifest, AppEntity $app): array;

    abstract public function hasDelta(Manifest $manifest, AppEntity $app): bool;
}
