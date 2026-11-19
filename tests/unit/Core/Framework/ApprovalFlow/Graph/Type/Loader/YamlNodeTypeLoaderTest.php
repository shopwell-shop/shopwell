<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\Type\Loader;

use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeSourceDirectory;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\YamlNodeTypeLoader;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(YamlNodeTypeLoader::class)]
class YamlNodeTypeLoaderTest extends TestCase
{
    public function testLoadsBuiltInDeclarationsAndRetainsTheirSchemaArtifacts(): void
    {
        $directory = \dirname(__DIR__, 8) . '/src/Core/Framework/ApprovalFlow/Resources/node-types';
        $loader = new YamlNodeTypeLoader(new Filesystem(), [new NodeTypeSourceDirectory('core', $directory)]);

        $types = $loader->load();

        static::assertSame(['core:approval', 'core:end', 'core:start'], array_column($types, 'type'));
        foreach ($types as $type) {
            static::assertSame('1.0.0', $type->version);
            static::assertStringStartsWith('core:', $type->source);
            static::assertSame(hash('sha256', $type->configurationSchema), $type->configurationSchemaHash);
            $schema = json_decode($type->configurationSchema, flags: \JSON_THROW_ON_ERROR);
            static::assertInstanceOf(\stdClass::class, $schema);
            static::assertSame('object', $schema->type);
        }
    }

    public function testBuiltInSchemasAcceptSupportedConfigurationsAndRejectMissingRequiredFields(): void
    {
        $directory = \dirname(__DIR__, 8) . '/src/Core/Framework/ApprovalFlow/Resources/node-types';
        $loader = new YamlNodeTypeLoader(new Filesystem(), [new NodeTypeSourceDirectory('core', $directory)]);
        $examples = [
            'core:start' => '{"requestType":"payment:transfer"}',
            'core:end' => '{"outcome":"approved"}',
            'core:approval' => '{"assignees":[{"kind":"core:users","userIds":["reviewer"]}],"policy":{"kind":"core:all","reject":"veto"},"fields":[],"emptyAssignees":"incident"}',
        ];
        $validator = new Validator();

        foreach ($loader->load() as $type) {
            $schema = json_decode($type->configurationSchema, flags: \JSON_THROW_ON_ERROR);
            $configuration = json_decode($examples[$type->type], flags: \JSON_THROW_ON_ERROR);

            static::assertTrue($validator->validate($configuration, $schema)->isValid(), $type->type);
            static::assertFalse($validator->validate(new \stdClass(), $schema)->isValid(), $type->type);
        }
    }

    public function testMissingOptionalDirectoriesContributeNoTypes(): void
    {
        $loader = new YamlNodeTypeLoader(new Filesystem(), [new NodeTypeSourceDirectory('plugin:absent', __DIR__ . '/not-installed')]);

        static::assertSame([], $loader->load());
    }

    public function testRejectsUnknownDeclarationFieldsInsteadOfIgnoringTypos(): void
    {
        $loader = new YamlNodeTypeLoader(new Filesystem(), [new NodeTypeSourceDirectory('test', __DIR__ . '/_fixtures/unknown-field')]);

        $this->expectExceptionObject(ApprovalFlowException::invalidNodeType('test:review.yaml', 'Unknown declaration field'));
        $loader->load();
    }

    public function testRejectsSchemaPathsOutsideTheDeclarationDirectory(): void
    {
        $loader = new YamlNodeTypeLoader(new Filesystem(), [new NodeTypeSourceDirectory('test', __DIR__ . '/_fixtures/outside-schema')]);

        $this->expectExceptionObject(ApprovalFlowException::invalidNodeType('test:review.yaml', 'configurationSchema must name a JSON file beside the declaration'));
        $loader->load();
    }
}
