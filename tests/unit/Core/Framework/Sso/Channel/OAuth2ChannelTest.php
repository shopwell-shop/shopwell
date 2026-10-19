<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Sso\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Channel\OAuth2Channel;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OAuth2Channel::class)]
class OAuth2ChannelTest extends TestCase
{
    public function testDefaultsOfAChannelTheBundleShips(): void
    {
        $channel = new OAuth2Channel('google', 'google', 'sso.oauth2.channel.google');

        static::assertSame('google', $channel->technicalName);
        static::assertSame('google', $channel->bundleType);
        static::assertSame('sso.oauth2.channel.google', $channel->labelSnippet);
        static::assertNull($channel->icon);
        static::assertSame([], $channel->defaultScopes);
        static::assertFalse($channel->providesVerifiedEmail);
        static::assertFalse($channel->supportsAdminLogin);
        static::assertSame([], $channel->bundleConfiguration);
        static::assertSame([], $channel->settings);
        static::assertSame([], $channel->scopeChoices);
    }

    public function testCarriesWhatTheChannelsOfTheGenericTypeNeed(): void
    {
        $channel = new OAuth2Channel(
            technicalName: 'wechat',
            bundleType: 'generic',
            labelSnippet: 'sso.oauth2.channel.wechat',
            icon: 'wechat',
            defaultScopes: ['snsapi_login'],
            providesVerifiedEmail: false,
            supportsAdminLogin: true,
            bundleConfiguration: [
                'provider_class' => \stdClass::class,
                'provider_options' => ['urlAuthorize' => 'https://example.com/authorize'],
            ],
        );

        static::assertSame('generic', $channel->bundleType);
        static::assertSame('wechat', $channel->icon);
        static::assertSame(['snsapi_login'], $channel->defaultScopes);
        static::assertTrue($channel->supportsAdminLogin);
        static::assertSame(\stdClass::class, $channel->bundleConfiguration['provider_class']);
    }

    public function testCarriesWhatTheAdminHasToFillInForTheChannel(): void
    {
        $channel = new OAuth2Channel(
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
        );

        static::assertSame('auth_server_url', $channel->settings[0]['name']);
        static::assertSame('url', $channel->settings[0]['type']);
        static::assertSame(['en-GB' => 'Auth server'], $channel->settings[0]['config']['label']);
        static::assertSame('openid', $channel->scopeChoices[0]['value']);
    }

    public function testOffersAScopeChoiceOnlyWhenThereIsMoreThanOne(): void
    {
        static::assertFalse((new OAuth2Channel('google', 'google', 'sso.oauth2.channel.google', defaultScopes: ['openid']))->offersScopeChoice());

        // a channel that takes exactly one value has nothing to choose, the admin may only get it
        // wrong - the WeChat Open Platform is the example: `snsapi_login` or nothing
        static::assertFalse((new OAuth2Channel('wechat', 'generic', 'sso.oauth2.channel.wechat', scopeChoices: [
            ['value' => 'snsapi_login', 'label' => ['en-GB' => 'Log in']],
        ]))->offersScopeChoice());

        // the WeChat official account is: silent for the openid alone, or with a consent screen for
        // nickname and avatar
        static::assertTrue((new OAuth2Channel('wechat-official-account', 'generic', 'sso.oauth2.channel.wechat', scopeChoices: [
            ['value' => 'snsapi_base', 'label' => ['en-GB' => 'Silent']],
            ['value' => 'snsapi_userinfo', 'label' => ['en-GB' => 'With profile']],
        ]))->offersScopeChoice());
    }
}
