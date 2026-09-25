<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Rule;

use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\FlowRule;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Rule\RuleComparison;
use Shopwell\Core\Framework\Rule\RuleConfig;
use Shopwell\Core\Framework\Rule\RuleConstraints;
use Shopwell\Core\Framework\Rule\RuleScope;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;

/**
 * @final
 */
#[Package('fundamentals@after-sales')]
class OrderTransactionStatusRule extends FlowRule
{
    public const RULE_NAME = 'orderTransactionStatus';

    /**
     * @var array<string>
     */
    protected array $salutationIds = [];

    /**
     * @param list<string> $stateIds
     *
     * @internal
     */
    public function __construct(
        public string $operator = Rule::OPERATOR_EQ,
        public ?array $stateIds = null
    ) {
        parent::__construct();
    }

    public function getConstraints(): array
    {
        return [
            'operator' => RuleConstraints::uuidOperators(false),
            'stateIds' => RuleConstraints::uuids(),
        ];
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof FlowRuleScope || $this->stateIds === null) {
            return false;
        }

        if (!Feature::isActive('v6.8.0.0')) {
            if (!$transactions = $scope->getOrder()->getTransactions()) {
                return false;
            }

            /** @var OrderTransactionEntity $last */
            $last = $transactions->last();
            $paymentMethodId = $last->getStateId();

            foreach ($transactions->getElements() as $transaction) {
                $technicalName = $transaction->getStateMachineState()?->getTechnicalName();
                if ($technicalName !== null
                    && $technicalName !== OrderTransactionStates::STATE_FAILED
                    && $technicalName !== OrderTransactionStates::STATE_CANCELLED
                ) {
                    $paymentMethodId = $transaction->getStateId();

                    break;
                }
            }

            return RuleComparison::stringArray($paymentMethodId, $this->stateIds, $this->operator);
        }

        if (!$scope->getOrder()->getPrimaryOrderTransaction()) {
            return false;
        }

        return RuleComparison::stringArray(
            $scope->getOrder()->getPrimaryOrderTransaction()->getStateId(),
            $this->stateIds,
            $this->operator,
        );
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING)
            ->entitySelectField(
                'stateIds',
                StateMachineStateDefinition::ENTITY_NAME,
                true,
                [
                    'criteria' => [
                        'associations' => [
                            'stateMachine',
                        ],
                        'filters' => [
                            [
                                'type' => 'equals',
                                'field' => 'state_machine_state.stateMachine.technicalName',
                                'value' => 'order_transaction.state',
                            ],
                        ],
                    ],
                ]
            );
    }
}
