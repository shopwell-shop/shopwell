<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Api;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\System\User\UserEntity;

/**
 * @internal
 *
 * 批量把用户 id 补成姓名。
 *
 * 存在的理由只有一个：**不要在处理列表时逐条查用户**。20 条待办 = 20 次
 * 用户查询，再加上每条的用户角色，列表接口会在 1 秒以上。这里一次 `Criteria($ids)` 查完。
 *
 * 姓名取 `name`，没有时退回 `username`：
 * 后台账号可能只填了用户名，显示空白会让人以为数据丢了。
 */
#[Package('framework')]
final class WorkflowUserLookup
{
    /**
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(private readonly EntityRepository $userRepository)
    {
    }

    /**
     * @param list<string> $userIds
     *
     * @return array<string, string> id => 显示名
     */
    public function names(array $userIds, Context $context): array
    {
        $userIds = array_values(array_unique(array_filter($userIds, static fn (string $id): bool => $id !== '')));

        if ($userIds === []) {
            return [];
        }

        $users = $this->userRepository->search(new Criteria($userIds), $context)->getEntities();

        $names = [];

        foreach ($users as $user) {
            $names[$user->getId()] = $this->displayName($user);
        }

        return $names;
    }

    private function displayName(UserEntity $user): string
    {
        $full = trim($user->getName());

        if ($full !== '') {
            return $full;
        }

        return $user->getUsername() !== '' ? $user->getUsername() : $user->getId();
    }
}
