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
    public function validate(NodeTypeSpecification $type, NodeConfiguration $configuration, AdvanceEffect|RequestApprovalEffect|CompleteEffect $effect): void
    {
        $kind = match (true) {
            $effect instanceof AdvanceEffect => NodeEffectType::Advance,
            $effect instanceof RequestApprovalEffect => NodeEffectType::RequestApproval,
            $effect instanceof CompleteEffect => NodeEffectType::Complete,
        };
        if (!\in_array($kind, $type->allowedEffects, true)) {
            throw ApprovalFlowException::invalidNodeEffect($type->type, $type->version, $kind->value, 'Effect is not allowed by the published node declaration.');
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
