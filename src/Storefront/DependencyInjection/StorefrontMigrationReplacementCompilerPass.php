<?php declare(strict_types=1);

namespace Shopwell\Storefront\DependencyInjection;

use Shopwell\Core\Framework\DependencyInjection\CompilerPass\AbstractMigrationReplacementCompilerPass;
use Shopwell\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
#[BecomesInternal(version: 'v6.8.0')]
class StorefrontMigrationReplacementCompilerPass extends AbstractMigrationReplacementCompilerPass
{
    protected function getMigrationPath(): string
    {
        return \dirname(__DIR__);
    }

    protected function getMigrationNamespacePart(): string
    {
        return 'Storefront';
    }
}
