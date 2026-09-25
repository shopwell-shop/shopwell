<?php declare(strict_types=1);

namespace Shopwell\Core\Test\Integration\Traits\Promotion;

use Shopwell\Core\Checkout\Cart\Rule\LineItemRule;
use Shopwell\Core\Checkout\Promotion\Aggregate\PromotionSetGroup\PromotionSetGroupEntity;
use Shopwell\Core\Content\Rule\RuleCollection;
use Shopwell\Core\Content\Rule\RuleEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Package('checkout')]
trait PromotionSetGroupTestFixtureBehaviour
{
    /**
     * @param RuleEntity[] $rules
     */
    private function createSetGroup(string $packagerKey, float $value, string $sorterKey, array $rules): PromotionSetGroupEntity
    {
        $group = new PromotionSetGroupEntity();
        $group->setId(Uuid::randomBytes());

        $group->setPackagerKey($packagerKey);
        $group->setValue($value);
        $group->setSorterKey($sorterKey);
        $group->setSetGroupRules(new RuleCollection($rules));

        return $group;
    }

    private function createSetGroupWithRuleFixture(string $groupId, string $packagerKey, float $value, string $sorterKey, string $promotionId, string $ruleId, ContainerInterface $container): string
    {
        $context = $container->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $repository = $container->get('promotion_setgroup.repository');

        $data = [
            'id' => $groupId,
            'promotionId' => $promotionId,
            'packagerKey' => $packagerKey,
            'sorterKey' => $sorterKey,
            'value' => $value,
        ];

        $repository->create([$data], $context->getContext());

        $ruleRepository = $container->get('promotion_setgroup_rule.repository');

        $dataAssoc = [
            'setgroupId' => $groupId,
            'ruleId' => $ruleId,
        ];

        $ruleRepository->create([$dataAssoc], $context->getContext());

        return $groupId;
    }

    /**
     * @param array<string> $lineItemIds
     */
    private function createRule(string $name, array $lineItemIds, ContainerInterface $container): string
    {
        $context = $container->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $ruleRepository = $container->get('rule.repository');
        $conditionRepository = $container->get('rule_condition.repository');

        $ruleId = Uuid::randomHex();
        $ruleRepository->create(
            [['id' => $ruleId, 'name' => $name, 'priority' => 1]],
            $context->getContext()
        );

        $id = Uuid::randomHex();
        $conditionRepository->create([
            [
                'id' => $id,
                'type' => (new LineItemRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'identifiers' => $lineItemIds,
                    'operator' => Rule::OPERATOR_EQ,
                ],
            ],
        ], $context->getContext());

        return $ruleId;
    }
}
