<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Store\Api;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Api\StoreController;
use Shopwell\Core\Framework\Store\Exception\StoreApiException;
use Shopwell\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopwell\Core\Framework\Store\Services\StoreClient;
use Shopwell\Core\Framework\Store\StoreException;
use Shopwell\Core\Framework\Store\Struct\PluginDownloadDataStruct;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
class StoreControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use KernelTestBehaviour;

    private Context $defaultContext;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    protected function setUp(): void
    {
        $this->defaultContext = Context::createDefaultContext();
        $this->userRepository = static::getContainer()->get('user.repository');
    }

    public function testCheckLoginWithoutStoreToken(): void
    {
        /** @var UserEntity $adminUser */
        $adminUser = $this->userRepository->search(new Criteria(), $this->defaultContext)->getEntities()->first();

        $storeController = $this->getStoreController();
        $context = new Context(new AdminApiSource($adminUser->getId()));

        $response = $storeController->checkLogin($context)->getContent();
        static::assertIsString($response);

        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);
        static::assertNull($response['userInfo']);
    }

    public function testLoginWithCorrectCredentials(): void
    {
        $request = new Request([], [
            'shopwellId' => 'j.doe@shopwell.cn',
            'password' => 'v3rys3cr3t',
        ]);

        /** @var UserEntity $adminUser */
        $adminUser = $this->userRepository->search(new Criteria(), $this->defaultContext)->getEntities()->first();

        $context = new Context(new AdminApiSource($adminUser->getId()));

        $storeClientMock = $this->createMock(StoreClient::class);
        $storeClientMock->expects($this->once())
            ->method('loginWithShopwellId')
            ->with('j.doe@shopwell.cn', 'v3rys3cr3t');

        $storeController = $this->getStoreController($storeClientMock);

        $response = $storeController->login($request, $context);

        static::assertSame(200, $response->getStatusCode());
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $request = new Request([], [
            'shopwellId' => 'j.doe@shopwell.cn',
            'password' => 'v3rys3cr3t',
        ]);

        /** @var UserEntity $adminUser */
        $adminUser = $this->userRepository->search(new Criteria(), $this->defaultContext)->getEntities()->first();

        $context = new Context(new AdminApiSource($adminUser->getId()));

        $clientExceptionMock = static::createStub(ClientException::class);
        $clientExceptionMock->method('getResponse')
            ->willReturn(new Response());

        $storeClientMock = $this->createMock(StoreClient::class);
        $storeClientMock->expects($this->once())
            ->method('loginWithShopwellId')
            ->willThrowException($clientExceptionMock);

        $storeController = $this->getStoreController($storeClientMock);

        static::expectException(StoreApiException::class);
        $storeController->login($request, $context);
    }

    public function testLoginWithInvalidCredentialsInput(): void
    {
        $request = new Request([], [
            'shopwellId' => null,
            'password' => null,
        ]);

        /** @var UserEntity $adminUser */
        $adminUser = $this->userRepository->search(new Criteria(), $this->defaultContext)->getEntities()->first();

        $context = new Context(new AdminApiSource($adminUser->getId()));

        $storeClientMock = $this->createMock(StoreClient::class);
        $storeClientMock->expects($this->never())
            ->method('loginWithShopwellId');

        $storeController = $this->getStoreController($storeClientMock);

        static::expectExceptionObject(StoreException::invalidCredentials());
        $storeController->login($request, $context);
    }

    public function testCheckLoginWithStoreToken(): void
    {
        /** @var UserEntity $adminUser */
        $adminUser = $this->userRepository->search(new Criteria(), $this->defaultContext)->getEntities()->first();

        $this->userRepository->update([[
            'id' => $adminUser->getId(),
            'storeToken' => 'store-token',
        ]], $this->defaultContext);

        $storeController = $this->getStoreController();
        $context = new Context(new AdminApiSource($adminUser->getId()));

        $response = $storeController->checkLogin($context)->getContent();
        static::assertIsString($response);

        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($response['userInfo'], [
            'name' => 'John Doe',
            'email' => 'john.doe@shopwell.cn',
        ]);
    }

    public function testCheckLoginWithMultipleStoreTokens(): void
    {
        /** @var UserEntity $adminUser */
        $adminUser = $this->userRepository->search(new Criteria(), $this->defaultContext)->getEntities()->first();

        $this->userRepository->update([[
            'id' => $adminUser->getId(),
            'storeToken' => 'store-token',
            'name' => 'John',
        ]], $this->defaultContext);

        $this->userRepository->create([[
            'id' => Uuid::randomHex(),
            'name' => 'Jane Doe',
            'storeToken' => 'store-token-two',
            'localeId' => $adminUser->getLocaleId(),
            'username' => 'admin-two',
            'password' => 's3cr3t12345',
            'email' => 'jane.doe@shopwell.cn',
        ]], $this->defaultContext);

        $storeController = $this->getStoreController();
        $context = new Context(new AdminApiSource($adminUser->getId()));

        $response = $storeController->checkLogin($context)->getContent();
        static::assertIsString($response);

        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($response['userInfo'], [
            'name' => 'John Doe',
            'email' => 'john.doe@shopwell.cn',
        ]);
    }

    private function getStoreController(
        ?StoreClient $storeClient = null,
    ): StoreController {
        return new StoreController(
            $storeClient ?? $this->getStoreClientMock(),
            $this->userRepository,
            static::getContainer()->get(AbstractExtensionDataProvider::class)
        );
    }

    /**
     * @return StoreClient&Stub
     */
    private function getStoreClientMock(): StoreClient
    {
        $storeClient = static::createStub(StoreClient::class);

        $storeClient->method('getDownloadDataForPlugin')
            ->willReturn($this->getPluginDownloadDataStub());

        $storeClient->method('userInfo')
            ->willReturn([
                'name' => 'John Doe',
                'email' => 'john.doe@shopwell.cn',
            ]);

        return $storeClient;
    }

    private function getPluginDownloadDataStub(): PluginDownloadDataStruct
    {
        return (new PluginDownloadDataStruct())
            ->assign([
                'location' => 'not-null',
            ]);
    }
}
