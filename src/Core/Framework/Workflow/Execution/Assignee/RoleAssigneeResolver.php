<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Assignee;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\System\User\UserCollection;

/**
 * @internal
 *
 * 角色：`assigneeValue` 是 ACL 角色 id 数组。
 *
 * **复用平台的 `acl_role`，不自建角色表**。理由：
 * 1. 角色成员已经在角色编辑器里维护，再造一份就会有两份真相；
 * 2. 权限与审批范围天然一致——能审这笔单的人，通常就是能看这类数据的人。
 *
 * 走 `user.aclRoles.id` 关联查询（`acl_user_role` 中间表由 DAL 处理），
 * 不要手写 join：手写会漏掉 DAL 的 tenant/version 处理。
 *
 * 结果按用户 id 排序，保证同一次解析的顺序稳定（会签顺序、日志顺序都依赖它）。
 */
#[Package('framework')]
final class RoleAssigneeResolver implements AssigneeResolverInterface
{
    /**
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(private readonly EntityRepository $userRepository)
    {
    }

    public function getAssigneeType(): string
    {
        return WorkflowAssigneeTypes::ROLE;
    }

    public function resolve(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): array
    {
        $value = $node->property('assigneeValue');

        if (!\is_array($value)) {
            return [];
        }

        $roleIds = array_values(array_filter($value, static fn (mixed $id): bool => \is_string($id) && $id !== ''));

        if ($roleIds === []) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsAnyFilter('aclRoles.id', $roleIds));

        $userIds = $this->userRepository->search($criteria, $context->context())->getIds();
        sort($userIds);

        return $userIds;
    }
}
