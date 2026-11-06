<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Api\Acl;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Acl\AclRoles;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AclRoles::class)]
class AclRolesTest extends TestCase
{
    public function testIntersectKeepsOnlyTheRolesInBothLists(): void
    {
        $roles = (new AclRoles(false, ['role-a', 'role-b']))->intersect(['role-a', 'role-c']);

        static::assertEquals(new AclRoles(false, ['role-a']), $roles);
    }

    public function testUnrestrictedRolesBecomeExactlyTheGivenOnes(): void
    {
        $roles = (new AclRoles(true, []))->intersect(['role-a', 'role-c']);

        static::assertEquals(new AclRoles(false, ['role-a', 'role-c']), $roles);
    }
}
