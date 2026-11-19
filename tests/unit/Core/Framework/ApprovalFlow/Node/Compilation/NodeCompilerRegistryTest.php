<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Node\Compilation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeCompilerRegistry;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NodeCompilerRegistry::class)]
class NodeCompilerRegistryTest extends TestCase
{
    public function testResolvesExactVersionsWithoutPriorityReplacement(): void
    {
        $first = $this->compiler(['finance:review' => ['1.0.0']]);
        $second = $this->compiler(['finance:review' => ['2.0.0']]);
        $registry = new NodeCompilerRegistry([$first, $second]);

        static::assertSame($first, $registry->get('finance:review', '1.0.0'));
        static::assertSame($second, $registry->get('finance:review', '2.0.0'));
    }

    public function testMissingVersionDoesNotFallBackToAnotherCompiler(): void
    {
        $registry = new NodeCompilerRegistry([$this->compiler(['finance:review' => ['2.0.0']])]);
        $this->expectExceptionObject(ApprovalFlowException::nodeCompilerNotFound('finance:review', '1.0.0'));

        $registry->get('finance:review', '1.0.0');
    }

    public function testConflictDoesNotExposeAPartiallyLoadedCatalogue(): void
    {
        $first = $this->compiler(['finance:review' => ['1.0.0']]);
        $second = $this->compiler(['finance:review' => ['1.0.0']]);
        $registry = new NodeCompilerRegistry([$first, $second]);
        $expected = ApprovalFlowException::duplicateNodeCompiler('finance:review', '1.0.0', $first::class, $second::class);
        try {
            $registry->get('finance:review', '1.0.0');
            static::fail('Conflicting compilers must fail the whole catalogue.');
        } catch (ApprovalFlowException $exception) {
            static::assertEquals($expected, $exception);
        }
        $this->expectExceptionObject($expected);

        $registry->get('finance:review', '1.0.0');
    }

    /**
     * @param array<mixed> $types
     */
    #[DataProvider('invalidTypesProvider')]
    public function testRejectsInvalidCompilerDeclarations(array $types): void
    {
        $compiler = $this->compiler($types);
        $registry = new NodeCompilerRegistry([$compiler]);

        try {
            $registry->get('finance:review', '1.0.0');
            static::fail('Invalid compiler identities must not enter the catalogue.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_NODE_COMPILER, $exception->getErrorCode());
            static::assertSame($compiler::class, $exception->getParameters()['compiler']);
        }
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function invalidTypesProvider(): iterable
    {
        yield 'numeric-looking key is converted to integer by PHP' => [['123' => ['1.0.0']]];
        yield 'null version collection is not silently skipped' => [['finance:review' => null]];
        yield 'scalar version collection is invalid' => [['finance:review' => '1.0.0']];
        yield 'version entries must be strings' => [['finance:review' => [1]]];
        yield 'version collection must be a list' => [['finance:review' => ['current' => '1.0.0']]];
        yield 'type must be namespaced' => [['review' => ['1.0.0']]];
        yield 'version must be exact' => [['finance:review' => ['latest']]];
        yield 'empty version list is not a registration' => [['finance:review' => []]];
        yield 'empty type catalogue is not a compiler' => [[]];
    }

    /**
     * @param array<mixed> $types
     */
    private function compiler(array $types): NodeCompiler
    {
        $compiler = static::createStub(NodeCompiler::class);
        $compiler->method('getTypes')->willReturn($types);

        return $compiler;
    }
}
