<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\Type\Loader;

use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\CoreNodeType;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeSourceDirectory;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\YamlNodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\NodeEffectType;
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

        static::assertSame([CoreNodeType::APPROVAL, CoreNodeType::END, CoreNodeType::START], array_column($types, 'type'));
        static::assertSame([[NodeEffectType::RequestApproval], [NodeEffectType::Complete], [NodeEffectType::Advance]], array_column($types, 'allowedEffects'));
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

    #[DataProvider('invalidEffectsProvider')]
    public function testRejectsInvalidAllowedEffects(string $declaration, string $reason): void
    {
        $filesystem = new Filesystem();
        $directory = sys_get_temp_dir() . '/approval-effects-' . bin2hex(random_bytes(8));
        $filesystem->mkdir($directory);
        $filesystem->dumpFile($directory . '/node.json', '{"$id":"urn:test:node","type":"object"}');
        $filesystem->dumpFile($directory . '/node.yaml', "type: test:node\nversion: 1.0.0\nlabelSnippet: test.node\nconfigurationSchema: node.json\nports: {in: input}\n" . $declaration);
        $loader = new YamlNodeTypeLoader($filesystem, [new NodeTypeSourceDirectory('test', $directory)]);
        $this->expectExceptionObject(ApprovalFlowException::invalidNodeType('test:node.yaml', $reason));

        try {
            $loader->load();
        } finally {
            $filesystem->remove($directory);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidEffectsProvider(): iterable
    {
        yield 'no implicit runtime permission' => ['', 'allowedEffects must be a nonempty list of effect types'];
        yield 'null is not permission' => ['allowedEffects: null', 'allowedEffects must be a nonempty list of effect types'];
        yield 'empty has no executable meaning' => ['allowedEffects: []', 'allowedEffects must be a nonempty list of effect types'];
        yield 'scalar is not a list' => ['allowedEffects: advance', 'allowedEffects must be a nonempty list of effect types'];
        yield 'numeric map is not a list' => ['allowedEffects: {0: advance}', 'allowedEffects must be a nonempty list of effect types'];
        yield 'named map is not a list' => ['allowedEffects: {entry: advance}', 'allowedEffects must be a nonempty list of effect types'];
        yield 'unknown operation is rejected' => ['allowedEffects: [send-money]', 'allowedEffects contains an unknown effect type'];
        yield 'wrong item type is rejected' => ['allowedEffects: [true]', 'allowedEffects contains an unknown effect type'];
        yield 'duplicates are rejected' => ['allowedEffects: [advance, advance]', 'allowedEffects must be a nonempty list of unique effect types'];
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

    public function testRejectsDraftSchemaPathsOutsideTheDeclarationDirectory(): void
    {
        $loader = new YamlNodeTypeLoader(new Filesystem(), [new NodeTypeSourceDirectory('test', __DIR__ . '/_fixtures/draft-outside')]);

        $this->expectExceptionObject(ApprovalFlowException::invalidNodeType('test:review.yaml', 'draftConfigurationSchema must name a JSON file beside the declaration'));
        $loader->load();
    }
}
