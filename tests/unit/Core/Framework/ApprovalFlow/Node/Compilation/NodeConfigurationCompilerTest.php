<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Node\Compilation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeCompilerRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeConfigurationCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\NodeConfiguration;
use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\FinanceNodeCompiler;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\FinanceNodeConfiguration;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NodeConfigurationCompiler::class)]
class NodeConfigurationCompilerTest extends TestCase
{
    public function testCompilesTheExactBusinessConfigurationAndPreservesItsJsonShapes(): void
    {
        $compiler = new NodeConfigurationCompiler(new NodeCompilerRegistry([new FinanceNodeCompiler()]), new GraphJsonDecoder());

        $configuration = $compiler->compile('finance:review', '1.0.0', '{"limit":0,"enabled":false}', '/nodes/1/config');
        $frozen = $compiler->encode('finance:review', '1.0.0', $configuration);

        static::assertInstanceOf(FinanceNodeConfiguration::class, $configuration);
        static::assertSame(0, $frozen->limit);
        static::assertFalse($frozen->enabled);
        static::assertNull($frozen->note);
        static::assertInstanceOf(\stdClass::class, $frozen->options);
        static::assertSame([], $frozen->groups);
    }

    public function testConfigurationFailureAddsTheNodePathAndRetainsTheDomainCause(): void
    {
        $cause = new class(Response::HTTP_UNPROCESSABLE_ENTITY, 'FINANCE__INVALID_RULE', 'Invalid limit.', ['path' => '/limit']) extends HttpException {};
        $registered = $this->nodeCompiler();
        $registered->method('compile')->willThrowException($cause);
        $compiler = new NodeConfigurationCompiler(new NodeCompilerRegistry([$registered]), new GraphJsonDecoder());

        try {
            $compiler->compile('finance:review', '1.0.0', '{}', '/nodes/1/config');
            static::fail('Invalid business configuration must abort compilation.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_NODE_CONFIGURATION, $exception->getErrorCode());
            static::assertSame('/nodes/1/config/limit', $exception->getParameters()['path']);
            static::assertSame($cause, $exception->getPrevious());
        }
    }

    public function testOperationalFailurePropagatesUnchanged(): void
    {
        $cause = new \RuntimeException('Compiler unavailable.');
        $registered = $this->nodeCompiler();
        $registered->method('compile')->willThrowException($cause);
        $compiler = new NodeConfigurationCompiler(new NodeCompilerRegistry([$registered]), new GraphJsonDecoder());
        $this->expectExceptionObject($cause);

        $compiler->compile('finance:review', '1.0.0', '{}', '/nodes/1/config');
    }

    public function testMissingExactVersionDoesNotSelectAnotherCompiler(): void
    {
        $compiler = new NodeConfigurationCompiler(new NodeCompilerRegistry([new FinanceNodeCompiler()]), new GraphJsonDecoder());
        $this->expectExceptionObject(ApprovalFlowException::nodeCompilerNotFound('finance:review', '2.0.0'));

        $compiler->compile('finance:review', '2.0.0', '{}', '/nodes/1/config');
    }

    public function testEncodingRejectsAListInsteadOfAConfigurationObject(): void
    {
        $registered = new FinanceNodeCompiler();
        $compiler = new NodeConfigurationCompiler(new NodeCompilerRegistry([$registered]), new GraphJsonDecoder());
        $configuration = static::createStub(NodeConfiguration::class);
        $configuration->method('jsonSerialize')->willReturn([]);
        $this->expectExceptionObject(ApprovalFlowException::invalidNodeCompiler(FinanceNodeCompiler::class, 'Compiled configuration must be a JSON object.'));

        $compiler->encode('finance:review', '1.0.0', $configuration);
    }

    public function testEncodingFailuresAreCompilerDefectsEvenForHttpValidationExceptions(): void
    {
        $cause = new class(Response::HTTP_BAD_REQUEST, 'FINANCE__SERIALIZATION_FAILURE', 'Cannot encode configuration.') extends HttpException {};
        $compiler = new NodeConfigurationCompiler(new NodeCompilerRegistry([new FinanceNodeCompiler()]), new GraphJsonDecoder());
        $configuration = static::createStub(NodeConfiguration::class);
        $configuration->method('jsonSerialize')->willThrowException($cause);

        try {
            $compiler->encode('finance:review', '1.0.0', $configuration);
            static::fail('An encoding failure must not become a client validation success.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_NODE_COMPILER, $exception->getErrorCode());
            static::assertSame($cause, $exception->getPrevious());
        }
    }

    private function nodeCompiler(): NodeCompiler&Stub
    {
        $compiler = static::createStub(NodeCompiler::class);
        $compiler->method('getTypes')->willReturn(['finance:review' => ['1.0.0']]);

        return $compiler;
    }
}
