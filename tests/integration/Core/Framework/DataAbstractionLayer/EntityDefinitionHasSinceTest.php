<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\AttributeMappingDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\AttributeTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class EntityDefinitionHasSinceTest extends TestCase
{
    use KernelTestBehaviour;

    public function testAllDefinitionsHasSince(): void
    {
        $service = static::getContainer()->get(DefinitionInstanceRegistry::class);

        $definitionsWithoutSince = [];

        foreach ($service->getDefinitions() as $definition) {
            if ($definition instanceof AttributeMappingDefinition || $definition instanceof AttributeTranslationDefinition) {
                continue;
            }

            if ($definition->since() === null) {
                $definitionsWithoutSince[] = $definition->getEntityName();
            }
        }

        static::assertCount(0, $definitionsWithoutSince, \sprintf('Following definitions does not have a since version: %s', implode(',', $definitionsWithoutSince)));
    }
}
