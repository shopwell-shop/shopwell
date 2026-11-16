<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Publication\Approval\Node;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowViolation;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeCompilationContext;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalNodeCompilationContext::class)]
class ApprovalNodeCompilationContextTest extends TestCase
{
    public function testReadsJsonObjectsWithoutChangingTheConfiguration(): void
    {
        $configuration = new \stdClass();
        $configuration->label = 'Finance';
        $context = new ApprovalNodeCompilationContext('review', $configuration, '/nodes/1/config');

        static::assertSame(['label' => 'Finance'], $context->configuration(['label']));
        static::assertSame('Finance', $configuration->label);
    }

    public function testRejectsAnUnknownConfigurationKeyAtItsDesignerLocation(): void
    {
        $context = new ApprovalNodeCompilationContext('review', ['unknown' => true], '/nodes/1/config');

        try {
            $context->configuration(['label']);
            static::fail('Unknown configuration must not be discarded.');
        } catch (WorkflowException $exception) {
            $violations = $exception->getParameters()['violations'];
            static::assertIsArray($violations);
            static::assertInstanceOf(WorkflowViolation::class, $violations[0]);
            static::assertSame('unknown_configuration', $violations[0]->code);
            static::assertSame('/nodes/1/config/unknown', $violations[0]->path);
            static::assertSame('review', $violations[0]->nodeId);
        }
    }

    public function testRejectsAListInsteadOfAnObject(): void
    {
        $context = new ApprovalNodeCompilationContext('review', ['value'], '/nodes/1/config');
        $this->expectException(WorkflowException::class);

        $context->configuration([]);
    }
}
