<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\Type\Specification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\NodeEffectType;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Specification\NodeTypeSpecification;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NodeTypeSpecification::class)]
class NodeTypeSpecificationTest extends TestCase
{
    public function testDisplayMetadataDoesNotControlNodeAdmission(): void
    {
        $type = new NodeTypeSpecification('test:review', '1.0.0', '', '', '{"$id":"urn:test:review","type":"object"}', [], [NodeEffectType::Advance]);

        static::assertSame('', $type->labelSnippet);
        static::assertSame('', $type->source);
        static::assertSame('urn:test:review', $type->configurationSchemaId);
    }

    public function testDeclarationKeepsItsOwnEffectValues(): void
    {
        $effect = NodeEffectType::Advance;
        $type = new NodeTypeSpecification('test:review', '1.0.0', 'test.review', 'test', '{"$id":"urn:test:review","type":"object"}', [], [&$effect]);

        $effect = NodeEffectType::Complete;

        static::assertSame([NodeEffectType::Advance], $type->allowedEffects);
    }

    public function testPreservesSchemaObjectAndArrayShapesAndExactBytes(): void
    {
        $schema = '{"$id":"urn:test:review:1","type":"object","properties":{},"required":[]}';
        $type = new NodeTypeSpecification('test:review', '1.0.0', 'test.review', 'test', $schema, ['in' => 'input'], [NodeEffectType::Advance]);

        static::assertSame($schema, $type->configurationSchema);
        static::assertSame('urn:test:review:1', $type->configurationSchemaId);
        static::assertSame(hash('sha256', $schema), $type->configurationSchemaHash);
        static::assertSame($schema, $type->draftConfigurationSchema);
    }

    public function testDraftSchemaIsExplicitAndKeepsItsOwnIdentity(): void
    {
        $full = '{"$id":"urn:test:review:1","type":"object","required":["kind"]}';
        $draft = '{"$id":"urn:test:review:1:draft","type":"object"}';
        $type = new NodeTypeSpecification('test:review', '1.0.0', 'test.review', 'test', $full, ['in' => 'input'], [NodeEffectType::Advance], $draft);

        static::assertSame($draft, $type->draftConfigurationSchema);
        static::assertSame($full, $type->configurationSchema);
    }

    public function testRejectsMalformedDraftSchema(): void
    {
        $this->expectExceptionObject(ApprovalFlowException::invalidNodeType('test', 'draftConfigurationSchema must declare an object and a nonempty $id'));

        new NodeTypeSpecification('test:review', '1.0.0', 'test.review', 'test', '{"$id":"urn:test:full","type":"object"}', ['in' => 'input'], [NodeEffectType::Advance], '[]');
    }

    #[DataProvider('invalidDeclarationProvider')]
    public function testRejectsInvalidDeclarations(string $type, string $version, string $schema, string $reason): void
    {
        $this->expectExceptionObject(ApprovalFlowException::invalidNodeType('test', $reason));

        new NodeTypeSpecification($type, $version, 'test.review', 'test', $schema, ['in' => 'input'], [NodeEffectType::Advance]);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function invalidDeclarationProvider(): iterable
    {
        $schema = '{"$id":"urn:test:review:1","type":"object"}';
        yield 'type requires namespace' => ['review', '1.0.0', $schema, 'type must be a namespaced identifier'];
        yield 'latest is not a version' => ['test:review', 'latest', $schema, 'version must be an exact major.minor.patch version'];
        yield 'version has no leading zeroes' => ['test:review', '01.0.0', $schema, 'version must be an exact major.minor.patch version'];
        yield 'schema must be an object' => ['test:review', '1.0.0', '[]', 'configurationSchema must declare an object and a nonempty $id'];
        yield 'schema needs stable identity' => ['test:review', '1.0.0', '{"type":"object"}', 'configurationSchema must declare an object and a nonempty $id'];
    }

    /**
     * @param array<NodeEffectType> $effects
     */
    #[DataProvider('invalidAllowedEffectsProvider')]
    public function testRejectsInvalidEffectDeclarations(array $effects): void
    {
        $this->expectExceptionObject(ApprovalFlowException::invalidNodeType('test', 'allowedEffects must be a nonempty list of unique effect types'));

        new NodeTypeSpecification('test:review', '1.0.0', 'test.review', 'test', '{"$id":"urn:test:review","type":"object"}', [], $effects);
    }

    /**
     * @return iterable<string, array{array<NodeEffectType>}>
     */
    public static function invalidAllowedEffectsProvider(): iterable
    {
        yield 'effects cannot be absent' => [[]];
        yield 'effects must form a list' => [['entry' => NodeEffectType::Advance]];
        yield 'a permission cannot repeat' => [[NodeEffectType::Advance, NodeEffectType::Advance]];
    }

    public function testPreservesJsonParsingFailureAsTheCause(): void
    {
        try {
            new NodeTypeSpecification('test:review', '1.0.0', 'test.review', 'test', '{broken', ['in' => 'input'], [NodeEffectType::Advance]);
            static::fail('Invalid JSON must be rejected.');
        } catch (ApprovalFlowException $exception) {
            static::assertInstanceOf(\JsonException::class, $exception->getPrevious());
            static::assertSame(ApprovalFlowException::INVALID_NODE_TYPE, $exception->getErrorCode());
        }
    }
}
