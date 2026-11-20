<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Transfer\Approval;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingValidationEvent;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeSpecification;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeSourceDirectory;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\YamlNodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\NodeEffectType;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Registry\NodeTypeRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Specification\NodeTypeSpecification;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalBindingSubscriber;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalException;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @phpstan-import-type GraphPayload from GraphDiagnostics
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentTransferApprovalBindingSubscriber::class)]
class PaymentTransferApprovalBindingSubscriberTest extends TestCase
{
    private PaymentTransferApprovalBindingSubscriber $subscriber;

    protected function setUp(): void
    {
        $core = new YamlNodeTypeLoader(new Filesystem(), [new NodeTypeSourceDirectory('core', \dirname(__DIR__, 7) . '/src/Core/Framework/ApprovalFlow/Resources/node-types')]);
        $plugin = static::createStub(NodeTypeLoader::class);
        $plugin->method('load')->willReturn([
            new NodeTypeSpecification('test:route', '1.0.0', 'route', 'test', '{"$id":"urn:test:route","type":"object"}', ['in' => 'input', 'out' => 'output'], [NodeEffectType::Advance]),
            new NodeTypeSpecification('test:review', '1.0.0', 'review', 'test', '{"$id":"urn:test:review","type":"object"}', ['in' => 'input', 'out' => 'output'], [NodeEffectType::RequestApproval]),
            new NodeTypeSpecification('test:complete', '1.0.0', 'complete', 'test', '{"$id":"urn:test:complete","type":"object"}', ['in' => 'input', 'out' => 'output'], [NodeEffectType::Advance, NodeEffectType::Complete]),
        ]);
        $this->subscriber = new PaymentTransferApprovalBindingSubscriber(new NodeTypeRegistry([$core, $plugin]));
    }

    public function testAcceptsMandatoryApprovalWithReadOnlyTransferFields(): void
    {
        $graph = $this->graph(GraphFixture::json());
        $graph->nodes[1]->config->fields = [(object) ['field' => 'form.amount', 'access' => 'readOnly', 'required' => true]];
        $event = $this->event(json_encode($graph, \JSON_THROW_ON_ERROR));

        $this->subscriber->validate($event);

        static::assertSame(
            [ApprovalBindingValidationEvent::class => 'validate'],
            PaymentTransferApprovalBindingSubscriber::getSubscribedEvents(),
        );
        static::assertSame('payment:transfer', $event->businessType->type);
    }

    public function testRejectsAFlowThatEndsWithoutApproval(): void
    {
        $graph = $this->graph(GraphFixture::json());
        array_splice($graph->nodes, 1, 1);
        $graph->connections = [$graph->connections[0]];
        $graph->connections[0]->target = 'end';
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('approval_bypass'));

        $this->subscriber->validate($this->event(json_encode($graph, \JSON_THROW_ON_ERROR)));
    }

    public function testRejectsSkippingAnApprovalWithoutAssignees(): void
    {
        $graph = $this->graph(GraphFixture::json());
        $graph->nodes[1]->config->emptyAssignees = 'skip';
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('approval_must_not_skip'));

        $this->subscriber->validate($this->event(json_encode($graph, \JSON_THROW_ON_ERROR)));
    }

    public function testRejectsEditableTransferFields(): void
    {
        $graph = $this->graph(GraphFixture::json());
        $graph->nodes[1]->config->fields = [(object) ['field' => 'form.recipient', 'access' => 'editable', 'required' => true]];
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('transfer_form_must_be_read_only'));

        $this->subscriber->validate($this->event(json_encode($graph, \JSON_THROW_ON_ERROR)));
    }

    public function testDoesNotApplyFinancialRulesToAnotherBusiness(): void
    {
        $event = $this->event('{}', type: 'purchase:order');

        $this->subscriber->validate($event);

        static::assertSame('{}', $event->graphJson);
    }

    public function testAllowsAnExtensionThatOnlyAdvancesBeforeMandatoryApproval(): void
    {
        $this->expectNotToPerformAssertions();

        $this->subscriber->validate($this->event($this->withPreapprovalNode('test:route')));
    }

    public function testRejectsAnExtensionThatCanCompleteBeforeMandatoryApproval(): void
    {
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('approval_bypass'));

        $this->subscriber->validate($this->event($this->withPreapprovalNode('test:complete')));
    }

    public function testChecksEveryBranchOfAnExtensionBeforeMandatoryApproval(): void
    {
        $graph = $this->graph($this->withPreapprovalNode('test:route'));
        $bypass = clone $graph->connections[0];
        $bypass->id = 'bypass';
        $bypass->source = 'precheck';
        $bypass->target = 'end';
        $graph->connections[] = $bypass;
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('approval_bypass'));

        $this->subscriber->validate($this->event(json_encode($graph, \JSON_THROW_ON_ERROR)));
    }

    #[DataProvider('customApprovalPositions')]
    public function testRejectsCustomApprovalNodesWhoseRulesCannotBeChecked(bool $beforeMandatoryApproval): void
    {
        $graph = $this->graph($this->withPreapprovalNode('test:review'));
        if (!$beforeMandatoryApproval) {
            $graph->connections[0]->target = 'review';
            $graph->connections[1]->target = 'precheck';
            $graph->connections[2]->target = 'end';
        }
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('unsupported_approval_node'));

        $this->subscriber->validate($this->event(json_encode($graph, \JSON_THROW_ON_ERROR)));
    }

    public static function customApprovalPositions(): iterable
    {
        yield 'before mandatory approval' => [true];
        yield 'after mandatory approval' => [false];
    }

    private function withPreapprovalNode(string $type): string
    {
        $graph = $this->graph(GraphFixture::json());
        $node = clone $graph->nodes[1];
        $node->id = 'precheck';
        $node->type = $type;
        $node->config = new \stdClass();
        $node->ports[1]->cardinality = 'many';
        $graph->nodes[] = $node;
        $graph->connections[0]->target = 'precheck';
        $edge = clone $graph->connections[0];
        $edge->id = 'precheck-review';
        $edge->source = 'precheck';
        $edge->target = 'review';
        $graph->connections[] = $edge;

        return json_encode($graph, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return GraphPayload
     */
    private function graph(string $json): \stdClass
    {
        /** @var GraphPayload $graph */
        $graph = json_decode($json, flags: \JSON_THROW_ON_ERROR);

        return $graph;
    }

    private function event(string $graph, string $type = 'payment:transfer'): ApprovalBindingValidationEvent
    {
        return new ApprovalBindingValidationEvent(
            new ApprovalBusinessTypeSpecification($type, '1.0.0', 'transfer', 'test', '{"$id":"payment:transfer","type":"object"}'),
            $graph,
            Context::createDefaultContext(),
        );
    }
}
