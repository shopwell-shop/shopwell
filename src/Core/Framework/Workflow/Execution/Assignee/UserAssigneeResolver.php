<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Assignee;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\System\User\UserCollection;

/**
 * Require all configured users to exist and be active; preserve input order for sequential approval.
 *
 * @internal
 */
#[Package('framework')]
final class UserAssigneeResolver implements AssigneeResolverInterface
{
    /**
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(private readonly EntityRepository $userRepository)
    {
    }

    public function getAssigneeType(): string
    {
        return WorkflowAssigneeTypes::USER;
    }

    public function resolve(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): array
    {
        $value = $node->property('assigneeValue');

        if (!\is_array($value)) {
            return [];
        }

        $userIds = array_values(array_filter($value, static fn (mixed $id): bool => \is_string($id) && $id !== ''));

        if ($userIds === []) {
            return [];
        }

        $criteria = new Criteria($userIds);
        $criteria->addFilter(new EqualsFilter('active', true));

        $criteria->addAssociation('aclRoles');
        $users = $this->userRepository->search($criteria, $context->context())->getEntities();
        $existing = $users->filter(ApprovalUserEligibility::isEligible(...))->getIds();

        if (array_diff($userIds, $existing) !== []) {
            throw WorkflowExecutionException::noAssignee($node->id, $this->getAssigneeType());
        }

        return $this->sortByInputOrder($userIds, array_values($existing));
    }

    /**
     * @param list<string> $order
     * @param list<string> $values
     *
     * @return list<string>
     */
    private function sortByInputOrder(array $order, array $values): array
    {
        $position = array_flip($order);
        $kept = array_values(array_filter($values, static fn (string $id): bool => isset($position[$id])));

        usort($kept, static fn (string $a, string $b): int => $position[$a] <=> $position[$b]);

        return $kept;
    }
}
