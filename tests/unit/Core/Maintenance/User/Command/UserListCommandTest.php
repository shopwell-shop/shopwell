<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Maintenance\User\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Maintenance\MaintenanceException;
use Shopwell\Core\Maintenance\User\Command\UserListCommand;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\System\User\UserEntity;
use Shopwell\Core\Test\Annotation\DisabledFeatures;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UserListCommand::class)]
class UserListCommandTest extends TestCase
{
    public function testWithNoUsers(): void
    {
        $repo = new StaticEntityRepository([new UserCollection()]);

        $command = new UserListCommand($repo);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();

        static::assertStringContainsString('There are no users', $output);
    }

    public function testWithUsers(): void
    {
        $commandTester = $this->prepareCommandTester();
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();

        static::assertStringContainsString('Guy Marbello', $output);
        static::assertStringContainsString('Jen Dalimil', $output);
    }

    public function testAclRolesNotLoadedException(): void
    {
        $userName = 'guy';
        $userId = Uuid::randomHex();
        $repo = new StaticEntityRepository([
            new UserCollection([
                $this->createUser('guy@shopwell.cn', $userName, 'Guy', 'Marbello', id: $userId),
            ]),
        ]);

        $command = new UserListCommand($repo);
        $commandTester = new CommandTester($command);

        $this->expectExceptionObject(MaintenanceException::aclRolesNotLoaded($userId, $userName));
        $commandTester->execute([]);
    }

    public function testWithFormatJson(): void
    {
        $commandTester = $this->prepareCommandTester();
        $commandTester->execute(['--format' => 'json']);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();

        static::assertTrue(json_validate($output));
        static::assertStringContainsString('Guy Marbello', $output);
        static::assertStringContainsString('Jen Dalimil', $output);
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with `--json` option
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testWithJson(): void
    {
        $commandTester = $this->prepareCommandTester();
        $commandTester->execute(['--json' => true]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();

        static::assertTrue(json_validate($output));
        static::assertStringContainsString('Guy Marbello', $output);
        static::assertStringContainsString('Jen Dalimil', $output);
    }

    public function testInvalidFormatReturnsError(): void
    {
        $commandTester = $this->prepareCommandTester();
        $commandTester->execute(['--format' => 'xml']);

        static::assertSame(2, $commandTester->getStatusCode());
        static::assertStringContainsString('Invalid format "xml"', $commandTester->getDisplay());
    }

    private function prepareCommandTester(): CommandTester
    {
        $repo = new StaticEntityRepository([
            new UserCollection([
                $this->createUser('guy@shopwell.cn', 'guy', 'Guy', 'Marbello', true),
                $this->createUser('jen@shopwell.cn', 'jen', 'Jen', 'Dalimil', false, ['Moderator', 'CS']),
            ]),
        ]);

        $command = new UserListCommand($repo);

        return new CommandTester($command);
    }

    /**
     * @param array<string> $roles
     */
    private function createUser(
        string $email,
        string $username,
        string $firstName,
        string $secondName,
        bool $isAdmin = false,
        ?array $roles = null,
        ?string $id = null,
    ): UserEntity {
        $user = new UserEntity();
        $user->setId($id ?? Uuid::randomHex());
        $user->setEmail($email);
        $user->setActive(true);
        $user->setUsername($username);
        $user->setFirstName($firstName);
        $user->setLastName($secondName);
        $user->setAdmin($isAdmin);
        $user->setCreatedAt(new \DateTime());

        if ($roles) {
            $user->setAclRoles(new AclRoleCollection(array_map(static function (string $role): AclRoleEntity {
                $aclRole = new AclRoleEntity();
                $aclRole->setId(Uuid::randomHex());
                $aclRole->setName($role);

                return $aclRole;
            }, $roles)));
        }

        return $user;
    }
}
