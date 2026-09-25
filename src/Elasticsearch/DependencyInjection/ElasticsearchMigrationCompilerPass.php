<?php declare(strict_types=1);

namespace Shopwell\Elasticsearch\DependencyInjection;

use Shopwell\Core\Framework\DependencyInjection\CompilerPass\AbstractMigrationReplacementCompilerPass;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
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
