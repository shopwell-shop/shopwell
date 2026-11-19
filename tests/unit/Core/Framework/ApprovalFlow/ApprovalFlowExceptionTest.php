<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalFlowException::class)]
class ApprovalFlowExceptionTest extends TestCase
{
    public function testInvalidGraphPlanIsAnInternalFault(): void
    {
        $exception = ApprovalFlowException::invalidGraphPlan('Boundary node is missing.');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(ApprovalFlowException::INVALID_GRAPH_PLAN, $exception->getErrorCode());
        static::assertSame(['reason' => 'Boundary node is missing.'], $exception->getParameters());
    }

    public function testPublicationGraphFailureRetainsItsDiagnosticCodeAndPath(): void
    {
        $exception = ApprovalFlowException::publicationGraphInvalid('missing_node_type', '/nodes/1/type', 'Node declaration is unavailable.');

        static::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getStatusCode());
        static::assertSame(ApprovalFlowException::PUBLICATION_GRAPH_INVALID, $exception->getErrorCode());
        static::assertSame([
            'code' => 'missing_node_type',
            'path' => '/nodes/1/type',
            'reason' => 'Node declaration is unavailable.',
        ], $exception->getParameters());
    }

    public function testMissingExactVersionIsAnExplicitSemanticError(): void
    {
        $exception = ApprovalFlowException::nodeTypeNotFound('finance:review', '1.0.0');

        static::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getStatusCode());
        static::assertSame(ApprovalFlowException::NODE_TYPE_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Approval node "finance:review@1.0.0" is not available.', $exception->getMessage());
    }

    public function testDuplicateDeclarationReportsBothSources(): void
    {
        $exception = ApprovalFlowException::duplicateNodeType('finance:review', '1.0.0', 'core', 'plugin:finance');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(ApprovalFlowException::DUPLICATE_NODE_TYPE, $exception->getErrorCode());
        static::assertSame('Approval node "finance:review@1.0.0" is declared by both "core" and "plugin:finance".', $exception->getMessage());
    }

    public function testInvalidDeclarationRetainsItsCauseAndSource(): void
    {
        $cause = new \JsonException('Malformed schema');
        $exception = ApprovalFlowException::invalidNodeType('plugin:finance/review.yaml', 'Invalid JSON', $cause);

        static::assertSame($cause, $exception->getPrevious());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(ApprovalFlowException::INVALID_NODE_TYPE, $exception->getErrorCode());
        static::assertSame('Invalid approval node declaration "plugin:finance/review.yaml": Invalid JSON', $exception->getMessage());
    }
}
