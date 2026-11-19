<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Execution\Effect;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\AdvanceEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\CompleteEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\NodeEffectValidator;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\RequestApprovalEffect;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\NodeEffectType;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Specification\NodeTypeSpecification;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\ApprovalFieldConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\ApprovalNodeConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\EndNodeConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\NodeConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\StartNodeConfiguration;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\FinanceNodeConfiguration;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NodeEffectValidator::class)]
class NodeEffectValidatorTest extends TestCase
{
    #[DataProvider('validEffectProvider')]
    public function testAcceptsDeclaredEffects(NodeConfiguration $configuration, AdvanceEffect|RequestApprovalEffect|CompleteEffect $effect, NodeEffectType $allowed): void
    {
        $this->expectNotToPerformAssertions();
        $validator = new NodeEffectValidator();

        $validator->validate($this->specification([$allowed]), $configuration, $effect);
    }

    /**
     * @return iterable<string, array{NodeConfiguration, AdvanceEffect|RequestApprovalEffect|CompleteEffect, NodeEffectType}>
     */
    public static function validEffectProvider(): iterable
    {
        yield 'start advances without choosing an edge' => [new StartNodeConfiguration('payment:transfer'), new AdvanceEffect(), NodeEffectType::Advance];
        yield 'approval preserves value-equivalent compiled rules' => [self::approval(), new RequestApprovalEffect(self::approval()), NodeEffectType::RequestApproval];
        yield 'approved end preserves outcome' => [new EndNodeConfiguration(ApprovalOutcome::Approved), new CompleteEffect(ApprovalOutcome::Approved), NodeEffectType::Complete];
        yield 'rejected end preserves outcome' => [new EndNodeConfiguration(ApprovalOutcome::Rejected), new CompleteEffect(ApprovalOutcome::Rejected), NodeEffectType::Complete];
        yield 'business configuration may compute a declared outcome' => [new FinanceNodeConfiguration(0, false), new CompleteEffect(ApprovalOutcome::Rejected), NodeEffectType::Complete];
    }

    public function testADeclarationMayPermitDifferentEntryIntents(): void
    {
        $this->expectNotToPerformAssertions();
        $type = $this->specification([NodeEffectType::Advance, NodeEffectType::Complete]);
        $configuration = new FinanceNodeConfiguration(0, false);
        $validator = new NodeEffectValidator();

        $validator->validate($type, $configuration, new AdvanceEffect());
        $validator->validate($type, $configuration, new CompleteEffect(ApprovalOutcome::Rejected));
    }

    #[DataProvider('undeclaredEffectProvider')]
    public function testRejectsAnUndeclaredEffect(AdvanceEffect|RequestApprovalEffect|CompleteEffect $effect, NodeEffectType $allowed, NodeEffectType $actual): void
    {
        $this->expectExceptionObject(ApprovalFlowException::invalidNodeEffect('test:node', '1.0.0', $actual->value, 'Effect is not allowed by the published node declaration.'));

        (new NodeEffectValidator())->validate($this->specification([$allowed]), self::approval(), $effect);
    }

    /**
     * @return iterable<string, array{AdvanceEffect|RequestApprovalEffect|CompleteEffect, NodeEffectType, NodeEffectType}>
     */
    public static function undeclaredEffectProvider(): iterable
    {
        yield 'approval cannot advance without tasks' => [new AdvanceEffect(), NodeEffectType::RequestApproval, NodeEffectType::Advance];
        yield 'approval cannot finish the process' => [new CompleteEffect(ApprovalOutcome::Approved), NodeEffectType::RequestApproval, NodeEffectType::Complete];
        yield 'start cannot invent approval rules' => [new RequestApprovalEffect(self::approval()), NodeEffectType::Advance, NodeEffectType::RequestApproval];
    }

    #[DataProvider('changedApprovalProvider')]
    public function testRejectsChangesToFrozenApprovalRules(ApprovalNodeConfiguration $changed): void
    {
        $this->expectExceptionObject(ApprovalFlowException::invalidNodeEffect('test:node', '1.0.0', NodeEffectType::RequestApproval->value, 'Approval rules differ from the compiled node configuration.'));

        (new NodeEffectValidator())->validate($this->specification([NodeEffectType::RequestApproval]), self::approval(), new RequestApprovalEffect($changed));
    }

    /**
     * @return iterable<string, array{ApprovalNodeConfiguration}>
     */
    public static function changedApprovalProvider(): iterable
    {
        $configuration = self::approval();
        yield 'candidate identity cannot change' => [new ApprovalNodeConfiguration(['another-user'], $configuration->policy, $configuration->fields, $configuration->emptyAssignees)];
        yield 'aggregation policy cannot change' => [new ApprovalNodeConfiguration($configuration->userIds, ApprovalNodeConfiguration::ANY, $configuration->fields, $configuration->emptyAssignees)];
        yield 'field permissions cannot disappear' => [new ApprovalNodeConfiguration($configuration->userIds, $configuration->policy, [], $configuration->emptyAssignees)];
        yield 'field required rule cannot change' => [new ApprovalNodeConfiguration($configuration->userIds, $configuration->policy, [new ApprovalFieldConfiguration('amount', ApprovalFieldConfiguration::EDITABLE, false)], $configuration->emptyAssignees)];
        yield 'empty candidate policy cannot change' => [new ApprovalNodeConfiguration($configuration->userIds, $configuration->policy, $configuration->fields, ApprovalNodeConfiguration::EMPTY_SKIP)];
    }

    public function testBusinessConfigurationCannotSupplyUnboundApprovalRules(): void
    {
        $this->expectExceptionObject(ApprovalFlowException::invalidNodeEffect('test:node', '1.0.0', NodeEffectType::RequestApproval->value, 'Approval rules differ from the compiled node configuration.'));

        (new NodeEffectValidator())->validate($this->specification([NodeEffectType::RequestApproval]), new FinanceNodeConfiguration(0, false), new RequestApprovalEffect(self::approval()));
    }

    public function testEndCannotReverseTheCompiledOutcome(): void
    {
        $this->expectExceptionObject(ApprovalFlowException::invalidNodeEffect('test:node', '1.0.0', NodeEffectType::Complete->value, 'Completion outcome differs from the compiled node configuration.'));

        (new NodeEffectValidator())->validate($this->specification([NodeEffectType::Complete]), new EndNodeConfiguration(ApprovalOutcome::Approved), new CompleteEffect(ApprovalOutcome::Rejected));
    }

    /**
     * @param non-empty-list<NodeEffectType> $allowedEffects
     */
    private function specification(array $allowedEffects): NodeTypeSpecification
    {
        return new NodeTypeSpecification('test:node', '1.0.0', 'test.node', 'test', '{"$id":"urn:test:node","type":"object"}', [], $allowedEffects);
    }

    private static function approval(): ApprovalNodeConfiguration
    {
        return new ApprovalNodeConfiguration(['finance-user'], ApprovalNodeConfiguration::ALL, [new ApprovalFieldConfiguration('amount', ApprovalFieldConfiguration::EDITABLE, true)], ApprovalNodeConfiguration::EMPTY_INCIDENT);
    }
}
