<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Store\Command;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Command\StoreLoginCommand;
use Shopwell\Core\Framework\Store\Services\StoreClient;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(StoreLoginCommand::class)]
class StoreLoginCommandTest extends TestCase
{
    #[TestDox('A successful login stores the license host and reports success')]
    public function testLoginSucceeds(): void
    {
        $userId = Uuid::randomHex();

        $storeClient = $this->createMock(StoreClient::class);
        $storeClient
            ->expects($this->once())
            ->method('loginWithShopwellId')
            ->willReturnCallback(function (string $shopwellId, string $password, Context $context) use ($userId): void {
                $this->assertSame('user@example.com', $shopwellId);
                $this->assertSame('secret', $password);
                $source = $context->getSource();
                $this->assertInstanceOf(AdminApiSource::class, $source);
                $this->assertSame($userId, $source->getUserId());
            });

        $configService = $this->createMock(SystemConfigService::class);
        $configService
            ->expects($this->once())
            ->method('set')
            ->with('core.store.licenseHost', 'example.shopwell.store', null, false);

        $commandTester = new CommandTester(new StoreLoginCommand(
            $storeClient,
            $this->createUserRepository([$userId]),
            $configService
        ));

        static::assertSame(Command::SUCCESS, $commandTester->execute([
            '--shopwellId' => 'user@example.com',
            '--password' => 'secret',
            '--user' => 'admin',
            '--host' => 'example.shopwell.store',
        ]));
        static::assertStringContainsString('Successfully logged in.', $commandTester->getDisplay());
    }

    #[TestDox('The login fails when the user is unknown')]
    public function testLoginFailsForUnknownUser(): void
    {
        $storeClient = $this->createMock(StoreClient::class);
        $storeClient->expects($this->never())->method('loginWithShopwellId');

        $commandTester = new CommandTester(new StoreLoginCommand(
            $storeClient,
            $this->createUserRepository([]),
            static::createStub(SystemConfigService::class)
        ));

        static::assertSame(Command::FAILURE, $commandTester->execute([
            '--shopwellId' => 'user@example.com',
            '--password' => 'secret',
            '--user' => 'unknown',
        ]));
        static::assertStringContainsString('User not found', $commandTester->getDisplay());
    }

    #[TestDox('The login fails without a Shopwell ID')]
    public function testLoginFailsWithoutShopwellId(): void
    {
        $storeClient = $this->createMock(StoreClient::class);
        $storeClient->expects($this->never())->method('loginWithShopwellId');

        $commandTester = new CommandTester(new StoreLoginCommand(
            $storeClient,
            $this->createUserRepository([Uuid::randomHex()]),
            static::createStub(SystemConfigService::class)
        ));

        static::assertSame(Command::FAILURE, $commandTester->execute([
            '--password' => 'secret',
            '--user' => 'admin',
        ]));
        static::assertStringContainsString('Shopwell ID and password are required.', $commandTester->getDisplay());
    }

    #[TestDox('A rejected store login is reported as failure')]
    public function testLoginFailsWhenStoreRejectsCredentials(): void
    {
        $storeClient = $this->createMock(StoreClient::class);
        $storeClient
            ->expects($this->once())
            ->method('loginWithShopwellId')
            ->willThrowException(new ClientException(
                'Invalid credentials',
                new Request('POST', '/swplatform/login'),
                new Response(401)
            ));

        $commandTester = new CommandTester(new StoreLoginCommand(
            $storeClient,
            $this->createUserRepository([Uuid::randomHex()]),
            static::createStub(SystemConfigService::class)
        ));

        static::assertSame(Command::FAILURE, $commandTester->execute([
            '--shopwellId' => 'user@example.com',
            '--password' => 'wrong',
            '--user' => 'admin',
        ]));
        static::assertStringContainsString('Store login failed: Invalid credentials', $commandTester->getDisplay());
    }

    /**
     * @param list<string> $userIds
     *
     * @return StaticEntityRepository<UserCollection>
     */
    private function createUserRepository(array $userIds): StaticEntityRepository
    {
        $repository = StaticEntityRepository::of(UserCollection::class, [$userIds]);

        return $repository;
    }
}
