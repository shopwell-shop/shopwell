<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Permission;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\WriteTypeIntendException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\AdminMenu\AdminMenuItemCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class WorkflowDefaultRoleInstaller
{
    private const ROLES = [
        WorkflowDefaultRolesEvent::MANAGER => [
            'name' => '流程管理员',
            'description' => '管理流程定义、查看草稿并发布流程。',
        ],
        WorkflowDefaultRolesEvent::PARTICIPANT => [
            'name' => '流程参与者',
            'description' => '发起流程、处理分配给自己的审批任务并查看参与记录。',
        ],
    ];

    /**
     * @param EntityRepository<AclRoleCollection> $roleRepository
     * @param EntityRepository<AdminMenuItemCollection> $menuRepository
     */
    public function __construct(
        private readonly EntityRepository $roleRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EntityRepository $menuRepository,
    ) {
    }

    public function install(Context $context): int
    {
        $event = new WorkflowDefaultRolesEvent();
        $this->eventDispatcher->dispatch($event);

        $created = 0;
        foreach (self::ROLES as $key => $role) {
            $id = Uuid::fromStringToHex('shopwell.workflow.role.' . $key);
            if ($this->exists($id, $context)) {
                continue;
            }

            try {
                $context->scope(Context::SYSTEM_SCOPE, fn () => $this->roleRepository->create([[
                    'id' => $id,
                    ...$role,
                    'privileges' => $event->getPrivileges($key),
                    'adminMenuItems' => $this->menuSelection($event->getMenuKeys($key), $context),
                ]], $context));
                ++$created;
            } catch (UniqueConstraintViolationException|WriteTypeIntendException $exception) {
                // Another installer may have created the same role after our read.
                if (!$this->exists($id, $context)) {
                    throw $exception;
                }
            }
        }

        return $created;
    }

    /**
     * @param list<string> $menuKeys
     *
     * @return list<array{id: string}>
     */
    private function menuSelection(array $menuKeys, Context $context): array
    {
        $criteria = (new Criteria())->addFilter(new EqualsAnyFilter('internalKey', $menuKeys));
        $ids = $this->menuRepository->searchIds($criteria, $context)->getIds();

        return array_values(array_map(static fn (string $id): array => ['id' => $id], $ids));
    }

    /**
     * @phpstan-impure Roles can be created concurrently between repository reads.
     */
    private function exists(string $id, Context $context): bool
    {
        return $this->roleRepository->searchIds(new Criteria([$id]), $context)->getTotal() > 0;
    }
}
