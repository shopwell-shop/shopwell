<?php declare(strict_types=1);

namespace Shopwell\Elasticsearch\DependencyInjection;

use Shopwell\Core\Framework\DependencyInjection\CompilerPass\AbstractMigrationReplacementCompilerPass;
use Shopwell\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class ElasticsearchMigrationCompilerPass extends AbstractMigrationReplacementCompilerPass
{
    protected function getMigrationPath(): string
    {
        return \dirname(__DIR__);
    }

    protected function getMigrationNamespacePart(): string
    {
        return 'Elasticsearch';
    }
}
