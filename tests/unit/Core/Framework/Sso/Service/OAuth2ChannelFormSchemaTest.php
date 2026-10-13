<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Sso\Service;

use KnpU\OAuth2ClientBundle\DependencyInjection\KnpUOAuth2ClientExtension;
use KnpU\OAuth2ClientBundle\DependencyInjection\ProviderFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Channel\OAuth2Channel;
use Shopwell\Core\Framework\Sso\Channel\OAuth2ChannelRegistry;
use Shopwell\Core\Framework\Sso\Service\OAuth2ChannelFormSchema;
use Shopwell\Core\Framework\Sso\Service\OAuth2ClientFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OAuth2ChannelFormSchema::class)]
class OAuth2ChannelFormSchemaTest extends TestCase
{
    public function testAsksForTheCredentialsAndNothingElseForAChannelWithOneFixedScope(): void
    {
        $fields = $this->createSchema()->build(
            new OAuth2Channel('google', 'google', 'sso.oauth2.channel.google', defaultScopes: ['openid'])
        );

        static::assertSame([
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
        ], $fields);
    }

    public function testDoesNotAskForASecretOfAProviderThatSignsItsOwnRequests(): void
    {
        $fields = $this->createSchema()->build(new OAuth2Channel('apple', 'apple', 'sso.oauth2.channel.apple', settings: [
            [
                'name' => 'team_id',
                'type' => 'text',
                'config' => ['label' => ['en-GB' => 'Team ID'], 'required' => true],
            ],
        ]));

        // Apple generates the client secret out of its key file, only the key of the shop is missing
        static::assertSame(['clientId', 'team_id'], array_column($fields, 'name'));
        static::assertSame('settings', $fields[1]['target']);
    }

    public function testOffersTheScopesOnlyWhenTheChannelHasMoreThanOne(): void
    {
        $schema = $this->createSchema();

        $wechatOpenPlatform = new OAuth2Channel('wechat', 'generic', 'sso.oauth2.channel.wechat', defaultScopes: ['snsapi_login'], scopeChoices: [
            ['value' => 'snsapi_login', 'label' => ['en-GB' => 'Scan to log in']],
        ]);

        static::assertSame(['clientId', 'clientSecret'], array_column($schema->build($wechatOpenPlatform), 'name'));

        $scopeChoice = [
            ['value' => 'snsapi_base', 'label' => ['zh-CN' => '静默授权（仅获取唯一标识）']],
            ['value' => 'snsapi_userinfo', 'label' => ['zh-CN' => '弹窗授权（获取昵称、头像）']],
        ];

        $officialAccount = new OAuth2Channel(
            'wechat-official-account',
            'generic',
            'sso.oauth2.channel.wechat',
            defaultScopes: ['snsapi_base'],
            scopeChoices: $scopeChoice,
        );

        $fields = $schema->build($officialAccount);

        static::assertSame(['clientId', 'clientSecret', 'scopes'], array_column($fields, 'name'));

        static::assertSame('multi-select', $fields[2]['type']);
        static::assertSame('scopes', $fields[2]['target']);
        static::assertSame([
            'label' => 'sw-settings-oauth2-login.channel.scopes',
            'placeholder' => 'snsapi_base',
            'options' => $scopeChoice,
        ], $fields[2]['config']);
    }

    public function testAppendsTheSettingsOfTheChannelInTheOrderItDeclaresThem(): void
    {
        $fields = $this->createSchema()->build(new OAuth2Channel('keycloak', 'keycloak', 'sso.oauth2.channel.keycloak', settings: [
            [
                'name' => 'auth_server_url',
                'type' => 'url',
                'config' => ['label' => ['en-GB' => 'Auth server'], 'required' => true],
            ],
            [
                'name' => 'realm',
                'type' => 'text',
                'config' => ['label' => ['en-GB' => 'Realm'], 'required' => true],
            ],
        ]));

        static::assertSame(
            ['clientId', 'clientSecret', 'auth_server_url', 'realm'],
            array_column($fields, 'name')
        );
        static::assertSame(
            ['clientId', 'clientSecret', 'settings', 'settings'],
            array_column($fields, 'target')
        );
        static::assertSame(
            ['en-GB' => 'Realm'],
            $fields[3]['config']['label']
        );
    }

    private function createSchema(): OAuth2ChannelFormSchema
    {
        return new OAuth2ChannelFormSchema(new OAuth2ClientFactory(
            new ProviderFactory(static::createStub(UrlGeneratorInterface::class)),
            new KnpUOAuth2ClientExtension(false),
            new OAuth2ChannelRegistry([]),
            new RequestStack(),
        ));
    }
}
