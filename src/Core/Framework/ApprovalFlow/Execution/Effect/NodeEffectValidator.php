<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Effect;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\NodeEffectType;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Specification\NodeTypeSpecification;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\ApprovalNodeConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\EndNodeConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\NodeConfiguration;
use Shopwell\Core\Framework\Log\Package;

/**
 * Checks pure entry output, without authorizing a runtime transition or side effect.
 *
 * @internal
 */
#[Package('framework')]
class NodeEffectValidator
{
    public function validate(NodeTypeSpecification $type, NodeConfiguration $configuration, NodeEffect $effect): void
    {
        $kind = $effect->getType();
        $supported = match ($kind) {
            NodeEffectType::Advance => $effect instanceof AdvanceEffect,
            NodeEffectType::OpenScope => $effect instanceof OpenScopeEffect,
            NodeEffectType::Wait => $effect instanceof WaitEffect,
            NodeEffectType::RequestApproval => $effect instanceof RequestApprovalEffect,
            NodeEffectType::Complete => $effect instanceof CompleteEffect,
        };
        if (!$supported) {
            throw ApprovalFlowException::invalidNodeEffect($type->type, $type->version, $kind->value, 'Unsupported effect implementation ' . $effect::class . '.');
        }
        if (!\in_array($kind, $type->allowedEffects, true)) {
            throw ApprovalFlowException::invalidNodeEffect($type->type, $type->version, $kind->value, 'Effect is not allowed by the published node declaration.');
        }
        if (($effect instanceof AdvanceEffect || $effect instanceof OpenScopeEffect) && $effect->outputPorts !== null) {
            if ($effect->outputPorts === [] || !array_is_list($effect->outputPorts) || \count(array_unique($effect->outputPorts)) !== \count($effect->outputPorts)) {
                throw ApprovalFlowException::invalidNodeEffect($type->type, $type->version, $kind->value, 'Routing requires a nonempty list of distinct output ports.');
            }
            foreach ($effect->outputPorts as $port) {
                if (($type->ports[$port] ?? null) !== 'output') {
                    throw ApprovalFlowException::invalidNodeEffect($type->type, $type->version, $kind->value, 'Routing selected an undeclared output port.');
                }
            }
        }
        if ($effect instanceof RequestApprovalEffect
            && (!$configuration instanceof ApprovalNodeConfiguration || $effect->configuration->jsonSerialize() !== $configuration->jsonSerialize())) {
            throw ApprovalFlowException::invalidNodeEffect($type->type, $type->version, $kind->value, 'Approval rules differ from the compiled node configuration.');
        }
        if ($effect instanceof CompleteEffect && $configuration instanceof EndNodeConfiguration && $effect->outcome !== $configuration->outcome) {
            throw ApprovalFlowException::invalidNodeEffect($type->type, $type->version, $kind->value, 'Completion outcome differs from the compiled node configuration.');
        }
    }
}
