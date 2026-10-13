<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Sso\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\SystemSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Channel\OAuth2Channel;
use Shopwell\Core\Framework\Sso\Controller\OAuth2IdentityController;
use Shopwell\Core\Framework\Sso\DataAbstractionLayer\OAuth2IdentityEntity;
use Shopwell\Core\Framework\Sso\Service\OAuth2LoginService;
use Shopwell\Core\Framework\Sso\SsoException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OAuth2IdentityController::class)]
class OAuth2IdentityControllerTest extends TestCase
{
    private const USER_ID = '0192f5b2b3c47a8e9f0d1e2f3a4b5c6d';

    public function testListMarksTheChannelsTheAdministratorAlreadyLinked(): void
    {
        $loginService = $this->createMock(OAuth2LoginService::class);
        $loginService->expects($this->once())
            ->method('getEnabledAdminChannels')
            ->willReturn([
                'google' => new OAuth2Channel(
                    technicalName: 'google',
                    bundleType: 'google',
                    labelSnippet: 'sso.oauth2.channel.google',
                    icon: 'google',
                    supportsAdminLogin: true,
                ),
                'wechat' => new OAuth2Channel(
                    technicalName: 'wechat',
                    bundleType: 'generic',
                    labelSnippet: 'sso.oauth2.channel.wechat',
                    supportsAdminLogin: true,
                ),
            ]);
        $loginService->expects($this->once())
            ->method('getUserIdentities')
            ->with(self::USER_ID, static::isInstanceOf(Context::class))
            ->willReturn(['google' => new OAuth2IdentityEntity()]);

        $content = $this->createController($loginService)->list($this->createAdminContext())->getContent();
        static::assertIsString($content);

        static::assertSame(
            [
                [
                    'technicalName' => 'google',
                    'labelSnippet' => 'sso.oauth2.channel.google',
                    'icon' => 'google',
                    'linked' => true,
                ],
                [
                    'technicalName' => 'wechat',
                    'labelSnippet' => 'sso.oauth2.channel.wechat',
                    'icon' => null,
                    'linked' => false,
                ],
            ],
            json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['bindings']
        );
    }

    public function testListReturnsNoBindingWhenTheShopOffersNoChannel(): void
    {
        $loginService = $this->createMock(OAuth2LoginService::class);
        $loginService->expects($this->once())->method('getEnabledAdminChannels')->willReturn([]);
        $loginService->expects($this->once())->method('getUserIdentities')->willReturn([]);

        $content = $this->createController($loginService)->list($this->createAdminContext())->getContent();
        static::assertIsString($content);

        static::assertSame([], json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['bindings']);
    }

    public function testRemoveUnbindsTheChannelOnlyForTheSignedInAdministrator(): void
    {
        $loginService = $this->createMock(OAuth2LoginService::class);
        $loginService->expects($this->once())
            ->method('unbindFromUser')
            ->with('google', self::USER_ID, static::isInstanceOf(Context::class));

        $response = $this->createController($loginService)->remove('google', $this->createAdminContext());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testListRejectsARequestThatIsNotMadeByAnAdministrator(): void
    {
        $this->expectExceptionObject(SsoException::invalidAdminSource(SystemSource::class));

        $this->createController(static::createStub(OAuth2LoginService::class))
            ->list(Context::createDefaultContext());
    }

    public function testRemoveRejectsATokenWithoutAUser(): void
    {
        $this->expectExceptionObject(SsoException::userNotLoggedIn());

        $this->createController(static::createStub(OAuth2LoginService::class))
            ->remove('google', Context::createDefaultContext(new AdminApiSource(null)));
    }

    private function createController(OAuth2LoginService $loginService): OAuth2IdentityController
    {
        return new OAuth2IdentityController($loginService);
    }

    private function createAdminContext(): Context
    {
        return Context::createDefaultContext(new AdminApiSource(self::USER_ID));
    }
}
