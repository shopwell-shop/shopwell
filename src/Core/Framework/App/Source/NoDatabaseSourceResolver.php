<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Source;

use Shopwell\Core\Framework\App\ActiveAppsLoader;
use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class NoDatabaseSourceResolver
{
    public function __construct(private readonly ActiveAppsLoader $activeAppsLoader)
    {
    }

    public function filesystem(string $appName): Filesystem
    {
        foreach ($this->activeAppsLoader->getActiveApps() as $activeApp) {
            if ($activeApp['name'] === $appName) {
                return new Filesystem($activeApp['path']);
            }
        }

        throw AppException::notFoundByField($appName, 'name');
    }
}
