<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Approval;

use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingValidationEvent;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\CoreNodeType;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\NodeEffectType;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Registry\NodeTypeRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\CoreNodeCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\ApprovalFieldConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\ApprovalNodeConfiguration;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @phpstan-import-type GraphPayload from GraphDiagnostics
 * @phpstan-import-type ApprovalConfiguration from CoreNodeCompiler
 *
 * @internal
 */
#[Package('framework')]
class PaymentTransferApprovalBindingSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly NodeTypeRegistry $nodeTypes)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [ApprovalBindingValidationEvent::class => 'validate'];
    }

    public function validate(ApprovalBindingValidationEvent $event): void
    {
        if ($event->businessType->type !== 'payment:transfer') {
            return;
        }

        // The binding service has already verified the release and compiled every node configuration.
        /** @var GraphPayload $graph */
        $graph = json_decode($event->graphJson, flags: \JSON_THROW_ON_ERROR);
        $nodes = [];
        $effects = [];
        $pending = [];
        foreach ($graph->nodes as $node) {
            $nodes[$node->id] = $node;
            $effects[$node->id] = $this->nodeTypes->get($node->type, $node->typeVersion)->allowedEffects;
            if ($node->type === CoreNodeType::START) {
                $pending[] = $node->id;
            }
            if ($node->type === CoreNodeType::APPROVAL) {
                /** @var ApprovalConfiguration $configuration */
                $configuration = $node->config;
                $this->validateApproval($configuration);
            } elseif (\in_array(NodeEffectType::RequestApproval, $effects[$node->id], true)) {
                // A plugin's raw configuration need not share the core approval schema.
                throw PaymentTransferApprovalException::blocked('unsupported_approval_node');
            }
        }
        $successors = [];
        foreach ($graph->connections as $connection) {
            $successors[$connection->source][] = $connection->target;
        }

        // Stop each path at its first mandatory approval; no earlier node may complete the transfer's flow.
        $visited = [];
        while ($pending !== []) {
            $id = array_pop($pending);
            $node = $nodes[$id];
            if ($node->type === CoreNodeType::APPROVAL || isset($visited[$id])) {
                continue;
            }
            if (\in_array(NodeEffectType::Complete, $effects[$id], true)) {
                throw PaymentTransferApprovalException::blocked('approval_bypass');
            }
            $visited[$id] = true;
            array_push($pending, ...$successors[$id]);
        }
    }

    /**
     * @param ApprovalConfiguration $configuration
     */
    private function validateApproval(\stdClass $configuration): void
    {
        if ($configuration->emptyAssignees !== ApprovalNodeConfiguration::EMPTY_INCIDENT) {
            throw PaymentTransferApprovalException::blocked('approval_must_not_skip');
        }
        foreach ($configuration->fields as $field) {
            if ($field->access === ApprovalFieldConfiguration::EDITABLE) {
                throw PaymentTransferApprovalException::blocked('transfer_form_must_be_read_only');
            }
        }
    }
}
