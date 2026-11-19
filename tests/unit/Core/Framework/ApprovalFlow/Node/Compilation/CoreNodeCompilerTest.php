<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Node\Compilation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphSchemaValidator;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\CoreNodeType;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeSourceDirectory;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\YamlNodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Registry\NodeTypeRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\CoreNodeCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\ApprovalNodeConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\EndNodeConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\StartNodeConfiguration;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CoreNodeCompiler::class)]
class CoreNodeCompilerTest extends TestCase
{
    private CoreNodeCompiler $compiler;

    protected function setUp(): void
    {
        $loader = new YamlNodeTypeLoader(new Filesystem(), [new NodeTypeSourceDirectory('core', \dirname(__DIR__, 7) . '/src/Core/Framework/ApprovalFlow/Resources/node-types')]);
        $this->compiler = new CoreNodeCompiler(new NodeTypeRegistry([$loader]), new GraphJsonDecoder(), new GraphSchemaValidator());
    }

    public function testCompilesStartAndEndWithoutResolvingBusinessServices(): void
    {
        $start = $this->compiler->compile(CoreNodeType::START, '1.0.0', '{"requestType":"payment:transfer"}');
        $end = $this->compiler->compile(CoreNodeType::END, '1.0.0', '{"outcome":"approved"}');

        static::assertInstanceOf(StartNodeConfiguration::class, $start);
        static::assertSame('payment:transfer', $start->requestType);
        static::assertInstanceOf(EndNodeConfiguration::class, $end);
        static::assertSame(EndNodeConfiguration::APPROVED, $end->outcome);
    }

    public function testCompilesApprovalWithStableAssigneeDeduplicationAndTypedFieldRules(): void
    {
        $configuration = $this->compiler->compile(CoreNodeType::APPROVAL, '1.0.0', <<<'JSON'
            {"assignees":[{"kind":"core:users","userIds":["second","first"]},{"kind":"core:users","userIds":["first","third"]}],"policy":{"kind":"core:all","reject":"veto"},"fields":[{"field":"form.amount","access":"editable","required":true}],"emptyAssignees":"incident"}
            JSON);

        static::assertInstanceOf(ApprovalNodeConfiguration::class, $configuration);
        static::assertSame(['second', 'first', 'third'], $configuration->userIds);
        static::assertSame(ApprovalNodeConfiguration::ALL, $configuration->policy);
        static::assertSame('form.amount', $configuration->fields[0]->field);
        static::assertTrue($configuration->fields[0]->required);
        static::assertSame(ApprovalNodeConfiguration::EMPTY_INCIDENT, $configuration->emptyAssignees);
    }

    public function testSinglePolicyCountsDistinctUsers(): void
    {
        $configuration = $this->compiler->compile(CoreNodeType::APPROVAL, '1.0.0', <<<'JSON'
            {"assignees":[{"kind":"core:users","userIds":["one"]},{"kind":"core:users","userIds":["one"]}],"policy":{"kind":"core:single","reject":"veto"},"fields":[],"emptyAssignees":"skip"}
            JSON);

        static::assertInstanceOf(ApprovalNodeConfiguration::class, $configuration);
        static::assertSame(['one'], $configuration->userIds);
        static::assertSame(ApprovalNodeConfiguration::SINGLE, $configuration->policy);
        static::assertSame(ApprovalNodeConfiguration::EMPTY_SKIP, $configuration->emptyAssignees);
    }

    #[DataProvider('invalidConfigurationProvider')]
    public function testRejectsMalformedIncompleteOrUnsupportedConfiguration(string $type, string $json, string $path): void
    {
        try {
            $this->compiler->compile($type, '1.0.0', $json);
            static::fail('Invalid configuration must not produce a semantic plan.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_NODE_CONFIGURATION, $exception->getErrorCode());
            static::assertSame($path, $exception->getParameters()['path']);
        }
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'list is not an object configuration' => [CoreNodeType::START, '[]', ''];
        yield 'null is not an object configuration' => [CoreNodeType::START, 'null', ''];
        yield 'malformed JSON' => [CoreNodeType::START, '{', ''];
        yield 'duplicate raw keys' => [CoreNodeType::START, '{"requestType":"payment:transfer","requestType":"payment:other"}', '/requestType'];
        yield 'missing request type' => [CoreNodeType::START, '{}', ''];
        yield 'present wrong type is not coerced' => [CoreNodeType::START, '{"requestType":17}', '/requestType'];
        yield 'unknown field is not discarded' => [CoreNodeType::END, '{"outcome":"approved","ignored":true}', ''];
        yield 'single policy cannot contain two users' => [CoreNodeType::APPROVAL, '{"assignees":[{"kind":"core:users","userIds":["one","two"]}],"policy":{"kind":"core:single","reject":"veto"},"fields":[],"emptyAssignees":"incident"}', '/policy/kind'];
        yield 'required field must be editable' => [CoreNodeType::APPROVAL, '{"assignees":[{"kind":"core:users","userIds":["one"]}],"policy":{"kind":"core:any","reject":"veto"},"fields":[{"field":"form.amount","access":"readOnly","required":true}],"emptyAssignees":"incident"}', '/fields/0/required'];
        yield 'duplicate field rules are ambiguous' => [CoreNodeType::APPROVAL, '{"assignees":[{"kind":"core:users","userIds":["one"]}],"policy":{"kind":"core:any","reject":"veto"},"fields":[{"field":"form.amount","access":"editable","required":true},{"field":"form.amount","access":"hidden","required":false}],"emptyAssignees":"incident"}', '/fields/1/field'];
        yield 'unsupported conditional rule is not made unconditional' => [CoreNodeType::APPROVAL, '{"assignees":[{"kind":"core:users","userIds":["one"]}],"policy":{"kind":"core:all","reject":"veto"},"fields":[{"field":"form.amount","access":"editable","required":false,"when":{"language":"core:expr","languageVersion":"1.0.0","text":"true","fieldRefs":[],"context":"requestSnapshot"}}],"emptyAssignees":"incident"}', '/fields/0/when'];
    }

    public function testCompilesRejectedOutcomeWithoutChangingItsMeaning(): void
    {
        $configuration = $this->compiler->compile(CoreNodeType::END, '1.0.0', '{"outcome":"rejected"}');

        static::assertInstanceOf(EndNodeConfiguration::class, $configuration);
        static::assertSame(EndNodeConfiguration::REJECTED, $configuration->outcome);
    }

    public function testDoesNotSubstituteAnotherNodeVersion(): void
    {
        $this->expectExceptionObject(ApprovalFlowException::nodeCompilerNotFound(CoreNodeType::START, '2.0.0'));

        $this->compiler->compile(CoreNodeType::START, '2.0.0', '{"requestType":"payment:transfer"}');
    }
}
