<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\ApiDefinition\Generator;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class CoreStoreApiSchemaMigrationScopeProvider implements StoreApiSchemaMigrationScopeProviderInterface
{
    public const SCOPE = 'core';

    private const PLATFORM_NAMESPACES = [
        'Shopwell\\Administration\\',
        'Shopwell\\Core\\',
        'Shopwell\\Elasticsearch\\',
        'Shopwell\\Storefront\\',
    ];

    private readonly string $schemaPath;

    public function __construct(
        ?string $schemaPath = null,
    ) {
        $this->schemaPath = $schemaPath ?? __DIR__ . '/Schema/StoreApi';
    }

    public function getScope(): string
    {
        return self::SCOPE;
    }

    public function getDefinitionClassPrefixes(): array
    {
        return self::PLATFORM_NAMESPACES;
    }

    public function getSchemaPaths(): array
    {
        return [$this->schemaPath];
    }

    public function includesAllDefinitions(): bool
    {
        return false;
    }
}
