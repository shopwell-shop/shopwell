<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Assignee;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\ApprovalUserEligibility;
use Shopwell\Core\System\User\UserEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalUserEligibility::class)]
class ApprovalUserEligibilityTest extends TestCase
{
    /**
     * @param list<list<string>> $privileges
     */
    #[DataProvider('users')]
    public function testRequiresAnActiveAccountAndBothDecisionPrivileges(bool $active, bool $admin, array $privileges, bool $expected): void
    {
        $user = new UserEntity();
        $user->setActive($active);
        $user->setAdmin($admin);
        $roles = [];
        foreach ($privileges as $index => $grants) {
            $role = new AclRoleEntity();
            $role->setId((string) $index);
            $role->setPrivileges($grants);
            $roles[] = $role;
        }
        $user->setAclRoles(new AclRoleCollection($roles));

        static::assertSame($expected, ApprovalUserEligibility::isEligible($user));
    }

    public static function users(): iterable
    {
        yield 'active admin' => [true, true, [], true];
        yield 'disabled admin' => [false, true, [], false];
        yield 'no grants' => [true, false, [], false];
        yield 'missing decision grant' => [true, false, [['workflow_instance:access']], false];
        yield 'grants combined across roles' => [true, false, [['workflow_instance:access'], ['workflow_task:decide']], true];
    }
}
