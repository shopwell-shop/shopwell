<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowException::class)]
class WorkflowExceptionTest extends TestCase
{
    public function testInvalidDocumentIncludesFieldPath(): void
    {
        $exception = WorkflowException::invalidDocument('/nodes/0/type', 'Expected a namespaced type.');

        static::assertSame(400, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__WORKFLOW_INVALID_DOCUMENT', $exception->getErrorCode());
        static::assertStringContainsString('/nodes/0/type', $exception->getMessage());
    }

    public function testDuplicateRegistrationIsAConfigurationError(): void
    {
        $exception = WorkflowException::duplicateNodeType('example:task', 2);

        static::assertSame(500, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__WORKFLOW_DUPLICATE_NODE_TYPE', $exception->getErrorCode());
        static::assertStringContainsString('version 2', $exception->getMessage());
    }

    public function testInvalidTypeReportsItsName(): void
    {
        $exception = WorkflowException::invalidNodeType('invalid');

        static::assertSame(400, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__WORKFLOW_INVALID_NODE_TYPE', $exception->getErrorCode());
        static::assertStringContainsString('invalid', $exception->getMessage());
    }
}
