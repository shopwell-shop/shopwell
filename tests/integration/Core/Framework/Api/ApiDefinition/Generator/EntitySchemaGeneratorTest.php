<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\ApiDefinition\Generator\EntitySchemaGenerator;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Tests\Integration\Core\Framework\Api\ApiDefinition\EntityDefinition\SimpleDefinition;

/**
 * @internal
 */
#[Package('framework')]
final class EntitySchemaGeneratorTest extends TestCase
{
    use KernelTestBehaviour;

    public function testAllEntriesHaveProtectionHints(): void
    {
        $definitionRegistry = new DefinitionInstanceRegistry(
            self::getContainer(),
            ['simple' => SimpleDefinition::class],
            ['simple' => 'simple.repository']
        );
        $definitionRegistry->register(new SimpleDefinition(), 'simple');

        $definitions = (new EntitySchemaGenerator())->getSchema($definitionRegistry->getDefinitions());

        static::assertNotCount(0, $definitions);

        foreach ($definitions as $definition) {
            static::assertArrayHasKey('write-protected', $definition);
            static::assertArrayHasKey('read-protected', $definition);
        }
    }

    public function testNoEntriesHaveBothProtectionHintsTrue(): void
    {
        $definitionRegistry = new DefinitionInstanceRegistry(
            static::getContainer(),
            ['simple' => SimpleDefinition::class],
            ['simple' => 'simple.repository']
        );
        $definitionRegistry->register(new SimpleDefinition(), 'simple');

        $definitions = (new EntitySchemaGenerator())->getSchema($definitionRegistry->getDefinitions());

        foreach ($definitions as $definition) {
            static::assertFalse($definition['write-protected'] && $definition['read-protected']);
        }
    }

    public function testSchemaProvidesFieldDescriptions(): void
    {
        $definitionRegistry = new DefinitionInstanceRegistry(
            static::getContainer(),
            ['simple' => SimpleDefinition::class],
            ['simple' => 'simple.repository']
        );
        $definitionRegistry->register(new SimpleDefinition(), 'simple');

        $definitions = (new EntitySchemaGenerator())->getSchema($definitionRegistry->getDefinitions());

        foreach ($definitions as $definition) {
            static::assertArrayHasKey('properties', $definition);
            static::assertIsArray($definition['properties']);

            $descriptions = array_filter(
                array_map(
                    static fn ($property) => $property['description'] ?? null,
                    $definition['properties']
                ),
                static fn ($description) => $description !== null
            );

            static::assertEquals(
                $descriptions,
                [
                    'stringField' => 'A simple string field',
                    'intField' => 'A simple int field',
                    'floatField' => 'A simple float field',
                    'boolField' => 'A simple bool field',
                    'idField' => 'A simple id field',
                ]
            );
        }
    }
}
