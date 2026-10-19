<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Sso\Controller;

use League\OAuth2\Server\AuthorizationServer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Channel\OAuth2Channel;
use Shopwell\Core\Framework\Sso\Controller\OAuth2AdminLoginController;
use Shopwell\Core\Framework\Sso\LoginResponseService;
use Shopwell\Core\Framework\Sso\Service\OAuth2LoginCodeService;
use Shopwell\Core\Framework\Sso\Service\OAuth2LoginService;
use Shopwell\Core\Framework\Sso\SsoException;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OAuth2AdminLoginController::class)]
class OAuth2AdminLoginControllerTest extends TestCase
{
    public function testChannelsReturnsTheEnabledAdminChannelsWithTheirStartUrl(): void
    {
        $channel = new OAuth2Channel(
            technicalName: 'google',
            bundleType: 'google',
            labelSnippet: 'sso.oauth2.channel.google',
            icon: 'google',
            supportsAdminLogin: true,
        );

        $loginService = $this->createMock(OAuth2LoginService::class);
        $loginService->expects($this->once())
            ->method('getEnabledAdminChannels')
            ->willReturn(['google' => $channel]);

        $controller = $this->createController($loginService, '/api/oauth2-login/admin/google');

        $content = $controller->channels()->getContent();
        static::assertIsString($content);

        static::assertSame(
            [
                [
                    'technicalName' => 'google',
                    'labelSnippet' => 'sso.oauth2.channel.google',
                    'icon' => 'google',
                    'startUrl' => '/api/oauth2-login/admin/google',
                ],
            ],
            json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['channels']
        );
    }

    public function testChannelsReturnsNoChannelWhenTheAdminEnabledNone(): void
    {
        $loginService = $this->createMock(OAuth2LoginService::class);
        $loginService->expects($this->once())->method('getEnabledAdminChannels')->willReturn([]);

        $content = $this->createController($loginService, '/api/oauth2-login/admin/google')->channels()->getContent();
        static::assertIsString($content);

        static::assertSame([], json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['channels']);
    }

    public function testStartSendsTheBrowserBackToTheLoginPageForAChannelThatIsNotAvailable(): void
    {
        $loginService = $this->createMock(OAuth2LoginService::class);
        $loginService->expects($this->once())
            ->method('createAuthorizationRedirect')
            ->willThrowException(SsoException::oauth2ProviderNotAvailable('github'));

        $response = $this->createController($loginService, '/admin')->start('github');

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/admin/#/login', $response->getTargetUrl());

        $cookie = $this->getErrorCookie($response);
        static::assertSame(SsoException::OAUTH2_PROVIDER_NOT_AVAILABLE, $cookie->getValue());
    }

    public function testCallbackSendsTheBrowserBackToTheLoginPageWhenTheAuthorizationFailed(): void
    {
        $loginService = $this->createMock(OAuth2LoginService::class);
        $loginService->expects($this->once())
            ->method('fetchIdentity')
            ->willThrowException(SsoException::oauth2IdentityNotBound('google'));

        $response = $this->createController($loginService, '/admin')->callback('google');

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/admin/#/login', $response->getTargetUrl());

        $cookie = $this->getErrorCookie($response);
        static::assertSame(SsoException::OAUTH2_IDENTITY_NOT_BOUND, $cookie->getValue());
    }

    private function createController(OAuth2LoginService $loginService, string $generatedUrl): OAuth2AdminLoginController
    {
        $urlGenerator = static::createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn($generatedUrl);

        return new OAuth2AdminLoginController(
            $loginService,
            static::createStub(OAuth2LoginCodeService::class),
            static::createStub(AuthorizationServer::class),
            static::createStub(PsrHttpFactory::class),
            new LoginResponseService($urlGenerator, new NativeClock()),
            $urlGenerator,
        );
    }

    private function getErrorCookie(RedirectResponse $response): Cookie
    {
        $cookies = $response->headers->getCookies();
        static::assertCount(1, $cookies);
        static::assertSame('oauth2LoginError', $cookies[0]->getName());

        return $cookies[0];
    }
}
