<?php declare(strict_types=1);

namespace Shopwell\Core\DevOps\StaticAnalyze;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Kernel;

/**
 * @internal
 */
#[Package('framework')]
class StaticAnalyzeKernel extends Kernel
{
    public function getCacheDir(): string
    {
        return \sprintf(
            '%s/var/cache/static_%s',
            $this->getProjectDir(),
            $this->getEnvironment(),
        );
    }
}
