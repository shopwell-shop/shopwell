<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Sso\Controller;

use KnpU\OAuth2ClientBundle\DependencyInjection\KnpUOAuth2ClientExtension;
use KnpU\OAuth2ClientBundle\DependencyInjection\ProviderFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Channel\OAuth2Channel;
use Shopwell\Core\Framework\Sso\Channel\OAuth2ChannelRegistry;
use Shopwell\Core\Framework\Sso\Controller\OAuth2ChannelController;
use Shopwell\Core\Framework\Sso\Service\OAuth2ChannelFormSchema;
use Shopwell\Core\Framework\Sso\Service\OAuth2ClientFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OAuth2ChannelController::class)]
class OAuth2ChannelControllerTest extends TestCase
{
    public function testListReturnsEveryChannelOfTheRegistryWithTheFieldsToConfigureIt(): void
    {
        $controller = $this->createController([
            new OAuth2Channel(
                technicalName: 'google',
                bundleType: 'google',
                labelSnippet: 'sso.oauth2.channel.google',
                icon: 'google',
                defaultScopes: ['openid', 'email'],
                providesVerifiedEmail: true,
                supportsAdminLogin: true,
            ),
            new OAuth2Channel(
                technicalName: 'wechat',
                bundleType: 'generic',
                labelSnippet: 'sso.oauth2.channel.wechat',
            ),
        ]);

        $content = $controller->list()->getContent();
        static::assertIsString($content);

        static::assertSame(
            [
                [
                    'technicalName' => 'google',
                    'labelSnippet' => 'sso.oauth2.channel.google',
                    'icon' => 'google',
                    'defaultScopes' => ['openid', 'email'],
                    'providesVerifiedEmail' => true,
                    'supportsAdminLogin' => true,
                    'formFields' => self::credentialFields(),
                ],
                [
                    'technicalName' => 'wechat',
                    'labelSnippet' => 'sso.oauth2.channel.wechat',
                    'icon' => null,
                    'defaultScopes' => [],
                    'providesVerifiedEmail' => false,
                    'supportsAdminLogin' => false,
                    'formFields' => self::credentialFields(),
                ],
            ],
            json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['channels']
        );
    }

    public function testListAnswersTheFieldsAChannelDeclaresItself(): void
    {
        $controller = $this->createController([
            new OAuth2Channel(
                technicalName: 'keycloak',
                bundleType: 'keycloak',
                labelSnippet: 'sso.oauth2.channel.keycloak',
                defaultScopes: ['openid'],
                settings: [
                    [
                        'name' => 'auth_server_url',
                        'type' => 'url',
                        'config' => ['label' => ['en-GB' => 'Auth server'], 'required' => true],
                    ],
                ],
                scopeChoices: [
                    ['value' => 'openid', 'label' => ['en-GB' => 'Identifier']],
                    ['value' => 'profile', 'label' => ['en-GB' => 'Profile']],
                ],
            ),
        ]);

        $content = $controller->list()->getContent();
        static::assertIsString($content);

        $channels = json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['channels'];

        static::assertSame(
            ['clientId', 'clientSecret', 'scopes', 'auth_server_url'],
            array_column($channels[0]['formFields'], 'name')
        );
        static::assertSame(
            ['clientId', 'clientSecret', 'scopes', 'settings'],
            array_column($channels[0]['formFields'], 'target')
        );
    }

    public function testListReturnsNoChannelWhenNoneIsRegistered(): void
    {
        $content = $this->createController([])->list()->getContent();
        static::assertIsString($content);

        static::assertSame([], json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['channels']);
    }

    /**
     * @param list<OAuth2Channel> $channels
     */
    private function createController(array $channels): OAuth2ChannelController
    {
        $registry = new OAuth2ChannelRegistry($channels);

        $factory = new OAuth2ClientFactory(
            new ProviderFactory(static::createStub(UrlGeneratorInterface::class)),
            new KnpUOAuth2ClientExtension(false),
            $registry,
            new RequestStack(),
        );

        return new OAuth2ChannelController($registry, new OAuth2ChannelFormSchema($factory));
    }

    /**
     * @return list<array{name: string, type: string, target: string, config: array<string, mixed>}>
     */
    private static function credentialFields(): array
    {
        return [
            [
                'name' => 'clientId',
                'type' => 'text',
                'target' => 'clientId',
                'config' => [
                    'label' => 'sw-settings-oauth2-login.channel.clientId',
                    'required' => true,
                ],
            ],
            [
                'name' => 'clientSecret',
                'type' => 'password',
                'target' => 'clientSecret',
                'config' => [
                    'label' => 'sw-settings-oauth2-login.channel.clientSecret',
                    'required' => true,
                ],
            ],
        ];
    }
}
