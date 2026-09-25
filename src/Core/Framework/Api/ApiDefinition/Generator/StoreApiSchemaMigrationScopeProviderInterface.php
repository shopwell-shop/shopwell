<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\ApiDefinition\Generator;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface StoreApiSchemaMigrationScopeProviderInterface
{
    public const SERVICE_TAG = 'shopwell.store_api_schema_migration.scope_provider';

    public function getScope(): string;

    /**
     * @return list<string>
     */
    public function getDefinitionClassPrefixes(): array;

    /**
     * @return list<string>
     */
    public function getSchemaPaths(): array;

    public function includesAllDefinitions(): bool;
}
