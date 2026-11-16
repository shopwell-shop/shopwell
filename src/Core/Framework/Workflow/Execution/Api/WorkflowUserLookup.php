<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Api;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\System\User\UserEntity;

/**
 * Load user display names in a single query, falling back to the username when the name is empty.
 *
 * @internal
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
     * @return array<string, string> User ID to display name
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
