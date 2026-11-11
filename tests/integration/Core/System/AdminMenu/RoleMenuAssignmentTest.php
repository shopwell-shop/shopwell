<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\AdminMenu;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopwell\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
class RoleMenuAssignmentTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    protected function tearDown(): void
    {
        $this->resetBrowser();
        parent::tearDown();
    }

    public function testReplacesAndClearsMenusWithoutChangingOtherRolesOrMenuDefinitions(): void
    {
        $context = Context::createDefaultContext();
        $menuId = Uuid::randomHex();
        static::getContainer()->get('admin_menu_item.repository')->create([[
            'id' => $menuId, 'type' => 'link', 'active' => true, 'linkTarget' => 'https://example.com',
            'translations' => [['languageId' => $context->getLanguageId(), 'title' => 'Documentation']],
        ]], $context);
        $roleId = Uuid::randomHex();
        $otherRoleId = Uuid::randomHex();
        static::getContainer()->get('acl_role.repository')->create([
            ['id' => $roleId, 'name' => 'Editor', 'privileges' => []],
            ['id' => $otherRoleId, 'name' => 'Other', 'privileges' => [], 'adminMenuItems' => [['id' => $menuId]]],
        ], $context);

        $client = $this->getBrowser(true, [UserVerifiedScope::IDENTIFIER]);
        $client->jsonRequest('PATCH', '/api/acl-role/' . $roleId, ['menuIds' => [$menuId], 'privileges' => ['product.viewer']]);
        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        static::assertSame([$menuId], $this->menuIds($roleId));
        $client->jsonRequest('POST', '/api/search/acl-role', ['ids' => [$roleId], 'associations' => ['adminMenuItems' => []]]);
        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $read = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame([$menuId], $read['data'][0]['attributes']['menuIds']);
        static::assertArrayNotHasKey('menuAccessMode', $read['data'][0]['attributes']);

        $client->jsonRequest('PATCH', '/api/acl-role/' . $roleId, ['menuIds' => []]);
        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        static::assertSame([], $this->menuIds($roleId));
        static::assertSame([$menuId], $this->menuIds($otherRoleId));
        $role = static::getContainer()->get('acl_role.repository')->search(new Criteria([$roleId]), $context)->first();
        static::assertInstanceOf(AclRoleEntity::class, $role);
        static::assertSame(['product.viewer'], $role->getPrivileges());
    }

    public function testInvalidMenuRollsBackTheRoleAndPreviousSelection(): void
    {
        $id = Uuid::randomHex();
        static::getContainer()->get('acl_role.repository')->create([[
            'id' => $id, 'name' => 'Original', 'privileges' => [],
        ]], Context::createDefaultContext());
        $client = $this->getBrowser(true, [UserVerifiedScope::IDENTIFIER]);
        $client->jsonRequest('PATCH', '/api/acl-role/' . $id, ['name' => 'Changed', 'menuIds' => [Uuid::randomHex()]]);
        static::assertGreaterThanOrEqual(400, $client->getResponse()->getStatusCode());
        $role = static::getContainer()->get('acl_role.repository')->search(new Criteria([$id]), Context::createDefaultContext())->first();
        static::assertInstanceOf(AclRoleEntity::class, $role);
        static::assertSame('Original', $role->getName());
    }

    public function testMenuAssignmentRequiresVerifiedScope(): void
    {
        $id = Uuid::randomHex();
        static::getContainer()->get('acl_role.repository')->create([['id' => $id, 'name' => 'Role', 'privileges' => []]], Context::createDefaultContext());
        $client = $this->getBrowser();
        $client->jsonRequest('PATCH', '/api/acl-role/' . $id, ['menuIds' => []]);
        static::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    /**
     * @return list<string>
     */
    private function menuIds(string $roleId): array
    {
        $criteria = new Criteria([$roleId]);
        $criteria->addAssociation('adminMenuItems');

        $role = static::getContainer()->get('acl_role.repository')->search($criteria, Context::createDefaultContext())->first();
        static::assertInstanceOf(AclRoleEntity::class, $role);
        $menus = $role->getAdminMenuItems();
        static::assertNotNull($menus);

        return array_values($menus->getIds());
    }
}
