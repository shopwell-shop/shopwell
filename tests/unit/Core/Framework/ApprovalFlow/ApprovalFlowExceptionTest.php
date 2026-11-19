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
    public function testMissingExactVersionIsAnExplicitSemanticError(): void
    {
        $exception = ApprovalFlowException::nodeTypeNotFound('finance:review', '1.0.0');

        static::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getStatusCode());
        static::assertSame('APPROVAL_FLOW__NODE_TYPE_NOT_FOUND', $exception->getErrorCode());
        static::assertSame('Approval node "finance:review@1.0.0" is not available.', $exception->getMessage());
    }

    public function testDuplicateDeclarationReportsBothSources(): void
    {
        $exception = ApprovalFlowException::duplicateNodeType('finance:review', '1.0.0', 'core', 'plugin:finance');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('APPROVAL_FLOW__DUPLICATE_NODE_TYPE', $exception->getErrorCode());
        static::assertSame('Approval node "finance:review@1.0.0" is declared by both "core" and "plugin:finance".', $exception->getMessage());
    }

    public function testInvalidDeclarationRetainsItsCauseAndSource(): void
    {
        $cause = new \JsonException('Malformed schema');
        $exception = ApprovalFlowException::invalidNodeType('plugin:finance/review.yaml', 'Invalid JSON', $cause);

        static::assertSame($cause, $exception->getPrevious());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('APPROVAL_FLOW__INVALID_NODE_TYPE', $exception->getErrorCode());
        static::assertSame('Invalid approval node declaration "plugin:finance/review.yaml": Invalid JSON', $exception->getMessage());
    }
}
