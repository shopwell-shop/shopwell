<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Diagnostics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Connection\WorkflowConnection;
use Shopwell\Core\Framework\Workflow\Definition\Node\WorkflowNode;
use Shopwell\Core\Framework\Workflow\Definition\Type\Loader\BuiltInWorkflowNodeTypeLoader;
use Shopwell\Core\Framework\Workflow\Definition\Type\Loader\WorkflowNodeTypeLoader;
use Shopwell\Core\Framework\Workflow\Definition\Type\Registry\WorkflowNodeTypeRegistry;
use Shopwell\Core\Framework\Workflow\Definition\Type\Resolver\WorkflowNodeTypeResolver;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeKind;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeTypeSpecification;
use Shopwell\Core\Framework\Workflow\Definition\WorkflowDocument;
use Shopwell\Core\Framework\Workflow\Diagnostics\Policy\StructuredWorkflowGraphPolicy;
use Shopwell\Core\Framework\Workflow\Diagnostics\Policy\WorkflowGraphPolicy;
use Shopwell\Core\Framework\Workflow\Diagnostics\Policy\WorkflowGraphPolicyRegistry;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowGraphDiagnostics;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowViolation;
use Shopwell\Core\Framework\Workflow\Event\WorkflowValidationEvent;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowGraphDiagnostics::class)]
#[CoversClass(WorkflowGraphPolicyRegistry::class)]
#[CoversClass(WorkflowValidationEvent::class)]
class WorkflowExtensionTest extends TestCase
{
    public function testCustomPolicyAndBusinessValidationDoNotChangeTheCore(): void
    {
        $loader = static::createStub(WorkflowNodeTypeLoader::class);
        $loader->method('load')->willReturn([new WorkflowNodeTypeSpecification('example:review', 1, WorkflowNodeKind::ACTIVITY, ['in'], ['out'])]);
        $types = new WorkflowNodeTypeRegistry([new BuiltInWorkflowNodeTypeLoader(), $loader]);
        $cyclicPolicy = static::createStub(WorkflowGraphPolicy::class);
        $cyclicPolicy->method('getName')->willReturn('example:cyclic');
        $cyclicPolicy->method('analyze')->willReturn([]);
        $events = new EventDispatcher();
        $events->addListener(WorkflowValidationEvent::class, static function (WorkflowValidationEvent $event): void {
            foreach ($event->document->nodes as $index => $node) {
                if ($node->type === 'example:review' && \is_array($node->config) && ($node->config['assignee'] ?? '') === '') {
                    $event->addViolation(new WorkflowViolation('example_assignee_required', '/nodes/' . $index . '/config/assignee', $node->id));
                }
            }
        });
        $diagnostics = new WorkflowGraphDiagnostics(new WorkflowNodeTypeResolver($types, new EventDispatcher()), new WorkflowGraphPolicyRegistry([new StructuredWorkflowGraphPolicy(), $cyclicPolicy]), $events);
        $document = new WorkflowDocument([
            new WorkflowNode('start', 'core:start', 1),
            new WorkflowNode('review', 'example:review', 1),
            new WorkflowNode('end', 'core:end', 1),
        ], [
            new WorkflowConnection('entry', 'start', 'out', 'review', 'in'),
            new WorkflowConnection('loop', 'review', 'out', 'review', 'in'),
            new WorkflowConnection('exit', 'review', 'out', 'end', 'in'),
        ], 'example:cyclic');

        static::assertSame(['example_assignee_required'], array_column($diagnostics->analyze($document), 'code'));
        $structured = new WorkflowDocument($document->nodes, $document->connections, 'core:structured');
        static::assertContains('cycle', array_column($diagnostics->analyze($structured), 'code'));
    }

    public function testAPluginCannotBypassGraphIntegrity(): void
    {
        $policy = static::createMock(WorkflowGraphPolicy::class);
        $policy->method('getName')->willReturn('example:custom');
        $policy->expects($this->never())->method('analyze');
        $types = new WorkflowNodeTypeRegistry([new BuiltInWorkflowNodeTypeLoader()]);
        $diagnostics = new WorkflowGraphDiagnostics(new WorkflowNodeTypeResolver($types, new EventDispatcher()), new WorkflowGraphPolicyRegistry([$policy]), new EventDispatcher());
        $document = new WorkflowDocument([], [new WorkflowConnection('edge', 'missing', 'out', 'missing', 'in')], 'example:custom');
        static::assertSame(['unknown_source', 'unknown_target'], array_column($diagnostics->analyze($document), 'code'));
    }

    public function testUnknownPolicyIsRejected(): void
    {
        $this->expectException(WorkflowException::class);
        (new WorkflowGraphPolicyRegistry([]))->get('example:missing');
    }

    public function testDuplicatePoliciesAreRejected(): void
    {
        $policy = static::createStub(WorkflowGraphPolicy::class);
        $policy->method('getName')->willReturn('example:duplicate');
        $this->expectException(WorkflowException::class);
        new WorkflowGraphPolicyRegistry([$policy, $policy]);
    }
}
