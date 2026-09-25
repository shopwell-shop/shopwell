<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Administration\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Administration\Controller\UserConfigController;
use Shopwell\Core\Framework\Api\ApiException;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopwell\Core\Framework\Api\Context\SystemSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\User\Aggregate\UserConfig\UserConfigCollection;
use Shopwell\Core\System\User\Aggregate\UserConfig\UserConfigDefinition;
use Shopwell\Core\System\User\Aggregate\UserConfig\UserConfigEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(UserConfigController::class)]
class UserConfigControllerTest extends TestCase
{
    /**
     * @var StaticEntityRepository<UserConfigCollection>
     */
    private StaticEntityRepository $userConfigRepository;

    private UserConfigController $userConfigController;

    private Context $context;

    protected function setUp(): void
    {
        $this->userConfigRepository = new StaticEntityRepository([], new UserConfigDefinition());
        $this->userConfigController = new UserConfigController(
            $this->userConfigRepository,
            static::createStub(Connection::class),
            new NativeClock()
        );
        $this->context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()));
    }

    public function testGetConfigMeReturnsEmptyData(): void
    {
        $this->userConfigRepository->addSearch(new UserConfigCollection());

        $response = $this->userConfigController->getConfigMe($this->context, new Request());

        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"data":[]}', $response->getContent());
    }

    public function testGetConfigMeThrowsApiExceptionWhenNoUserId(): void
    {
        $this->expectExceptionObject(ApiException::userNotLoggedIn());

        $this->userConfigController->getConfigMe(Context::createDefaultContext(new AdminApiSource(null)), new Request());
    }

    public function testGetConfigMeThrowsInvalidContextSourceExceptionWhenWrongSource(): void
    {
        $this->expectExceptionObject(new InvalidContextSourceException(AdminApiSource::class, SystemSource::class));

        $this->userConfigController->getConfigMe(Context::createDefaultContext(), new Request());
    }

    public function testGetConfigMeReturnsDataWithKeys(): void
    {
        $userConfigEntity = new UserConfigEntity();
        $userConfigEntity->setUniqueIdentifier(Uuid::randomHex());
        $userConfigEntity->setKey('testKey');
        $this->userConfigRepository->addSearch(new UserConfigCollection([$userConfigEntity]));

        $response = $this->userConfigController->getConfigMe($this->context, new Request(['keys' => ['testKey']]));

        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"data":{"testKey": null}}', $response->getContent());
    }

    public function testUpdateConfigMeReturnsEmptyDataWhenNoPostUpdateConfigs(): void
    {
        $response = $this->userConfigController->updateConfigMe($this->context, new Request([], []));

        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{}', $response->getContent());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testUpdateConfigPerformsMassUpsertEmptyWhenPostUpdateConfigs(): void
    {
        $userConfigEntity = new UserConfigEntity();
        $userConfigEntity->setId(Uuid::randomHex());
        $userConfigEntity->setUniqueIdentifier(Uuid::randomHex());
        $userConfigEntity->setKey('testKey');

        $this->userConfigRepository->addSearch(new UserConfigCollection([$userConfigEntity]));

        $response = $this->userConfigController->updateConfigMe($this->context, new Request([], ['product' => true, 'testKey' => true]));

        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{}', $response->getContent());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }
}
