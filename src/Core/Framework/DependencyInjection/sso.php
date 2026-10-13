<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use KnpU\OAuth2ClientBundle\DependencyInjection\KnpUOAuth2ClientExtension;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Content\Mail\Service\MailService;
use Shopwell\Core\Framework\Api\OAuth\AuthCodeRepository;
use Shopwell\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Shopwell\Core\Framework\Sso\Channel\OAuth2Channel;
use Shopwell\Core\Framework\Sso\Channel\OAuth2ChannelRegistry;
use Shopwell\Core\Framework\Sso\Config\LoginConfigService;
use Shopwell\Core\Framework\Sso\Controller\OAuth2AdminLoginController;
use Shopwell\Core\Framework\Sso\Controller\OAuth2ChannelController;
use Shopwell\Core\Framework\Sso\Controller\OAuth2IdentityController;
use Shopwell\Core\Framework\Sso\Controller\SsoController;
use Shopwell\Core\Framework\Sso\DataAbstractionLayer\OAuth2ClientEntity;
use Shopwell\Core\Framework\Sso\DataAbstractionLayer\OAuth2IdentityEntity;
use Shopwell\Core\Framework\Sso\LoginResponseService;
use Shopwell\Core\Framework\Sso\Provider\WeChat\WeChatOpenPlatformProvider;
use Shopwell\Core\Framework\Sso\Service\OAuth2ChannelFormSchema;
use Shopwell\Core\Framework\Sso\Service\OAuth2ClientFactory;
use Shopwell\Core\Framework\Sso\Service\OAuth2IdentityFactory;
use Shopwell\Core\Framework\Sso\Service\OAuth2LoginCodeService;
use Shopwell\Core\Framework\Sso\Service\OAuth2LoginService;
use Shopwell\Core\Framework\Sso\SsoService;
use Shopwell\Core\Framework\Sso\SsoUser\SsoUserInvitationMailService;
use Shopwell\Core\Framework\Sso\SsoUser\SsoUserService;
use Shopwell\Core\Framework\Sso\StateValidator;
use Shopwell\Core\Framework\Sso\TokenService\ExternalTokenService;
use Shopwell\Core\Framework\Sso\TokenService\IdTokenParser;
use Shopwell\Core\Framework\Sso\TokenService\PublicKeyLoader;
use Shopwell\Core\Framework\Sso\UserService\UserService;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Clock\ClockInterface as SymfonyClockInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\RouterInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SsoController::class)
        ->public()
        ->args([
            service('shopwell.api.authorization_server'),
            service(PsrHttpFactory::class),
            service(LoginConfigService::class),
            service(LoginResponseService::class),
            service(StateValidator::class),
            service(SsoUserService::class),
            service(SsoUserInvitationMailService::class),
            service(SsoService::class),
            service(RouterInterface::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(OAuth2ChannelController::class)
        ->public()
        ->args([
            service(OAuth2ChannelRegistry::class),
            service(OAuth2ChannelFormSchema::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(OAuth2AdminLoginController::class)
        ->public()
        ->args([
            service(OAuth2LoginService::class),
            service(OAuth2LoginCodeService::class),
            service('shopwell.api.authorization_server'),
            service(PsrHttpFactory::class),
            service(LoginResponseService::class),
            service(RouterInterface::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(OAuth2IdentityController::class)
        ->public()
        ->args([
            service(OAuth2LoginService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(LoginConfigService::class)
        ->args([
            param('shopwell.admin_login'),
            service(RouterInterface::class),
        ]);

    $services->set(ExternalTokenService::class)
        ->args([
            service('http_client'),
            service(LoginConfigService::class),
        ]);

    $services->set(IdTokenParser::class)
        ->args([
            service(PublicKeyLoader::class),
            service(LoginConfigService::class),
            service(SymfonyClockInterface::class),
        ]);

    $services->set(PublicKeyLoader::class)
        ->args([
            service('http_client'),
            service(LoginConfigService::class),
            service('cache.object'),
        ]);

    $services->set(UserService::class)
        ->args([
            service(Connection::class),
            service(IdTokenParser::class),
            service('user.repository'),
            service(ExternalTokenService::class),
            service(ClockInterface::class),
        ]);

    $services->set(SsoUserService::class)
        ->args([
            service('user.repository'),
        ]);

    $services->set(LoginResponseService::class)
        ->args([
            service(RouterInterface::class),
            service(ClockInterface::class),
        ]);

    $services->set(SsoService::class)
        ->args([
            service(LoginConfigService::class),
            service(RefreshTokenRepository::class),
            service(AuthCodeRepository::class),
        ]);

    $services->set(StateValidator::class);

    $services->set(SsoUserInvitationMailService::class)
        ->args([
            service(MailService::class),
            service(SystemConfigService::class),
            service('mail_template.repository'),
            service('mail_template_type.repository'),
            service('user.repository'),
            service('language.repository'),
            service(RouterInterface::class),
            param('APP_URL'),
        ]);

    $services->set(OAuth2ClientEntity::class)
        ->tag('shopwell.entity');

    $services->set(OAuth2IdentityEntity::class)
        ->tag('shopwell.entity');

    // the provider configurators of oauth2-client-bundle are plain objects selected by the channel
    // type, the extension of the bundle is used as the registry for them
    $services->set(KnpUOAuth2ClientExtension::class)
        ->args([false]);

    $services->set(OAuth2IdentityFactory::class);

    $services->set(OAuth2LoginCodeService::class)
        ->args([
            service('cache.object'),
            service(ClockInterface::class),
        ]);

    $services->set(OAuth2LoginService::class)
        ->args([
            service('oauth2_client.repository'),
            service('oauth2_identity.repository'),
            service('customer.repository'),
            service('user.repository'),
            service(OAuth2ChannelRegistry::class),
            service(OAuth2ClientFactory::class),
            service(OAuth2IdentityFactory::class),
            service('event_dispatcher'),
        ]);

    $services->set(OAuth2ClientFactory::class)
        ->args([
            service('knpu.oauth2.provider_factory'),
            service(KnpUOAuth2ClientExtension::class),
            service(OAuth2ChannelRegistry::class),
            service('request_stack'),
        ]);

    $services->set(OAuth2ChannelRegistry::class)
        ->args([
            tagged_iterator('shopwell.sso.oauth2_channel'),
        ]);

    // the fields the administration shows for a channel: the credentials every channel has plus what
    // the channel itself declares, so the admin renders a channel an extension added
    $services->set(OAuth2ChannelFormSchema::class)
        ->args([
            service(OAuth2ClientFactory::class),
        ]);

    // The channels the platform ships. A channel is data, so adding another one of the types
    // oauth2-client-bundle ships is one more entry here (or one in an extension), no new class.
    $services->set('shopwell.sso.oauth2_channel.google', OAuth2Channel::class)
        ->args([
            '$technicalName' => 'google',
            '$bundleType' => 'google',
            '$labelSnippet' => 'sso.oauth2.channel.google',
            '$icon' => 'google',
            '$defaultScopes' => ['openid', 'email', 'profile'],
            '$providesVerifiedEmail' => true,
            '$supportsAdminLogin' => true,
        ])
        ->tag('shopwell.sso.oauth2_channel');

    // WeChat Open Platform is not shipped by oauth2-client-bundle, so it goes through the `generic`
    // type with its own league provider class, exactly like a static knpu_oauth2_client entry.
    $services->set('shopwell.sso.oauth2_channel.wechat', OAuth2Channel::class)
        ->args([
            '$technicalName' => 'wechat',
            '$bundleType' => 'generic',
            '$labelSnippet' => 'sso.oauth2.channel.wechat',
            '$icon' => 'wechat',
            '$defaultScopes' => ['snsapi_login'],
            '$providesVerifiedEmail' => false,
            '$supportsAdminLogin' => true,
            '$bundleConfiguration' => [
                'provider_class' => WeChatOpenPlatformProvider::class,
                'provider_options' => [
                    'urlAuthorize' => 'https://open.weixin.qq.com/connect/qrconnect',
                    'urlAccessToken' => 'https://api.weixin.qq.com/sns/oauth2/access_token',
                    'urlResourceOwnerDetails' => 'https://api.weixin.qq.com/sns/userinfo',
                    // WeChat answers the token endpoint with a query API and reports errors as
                    // errcode/errmsg with HTTP status 200
                    'accessTokenMethod' => 'GET',
                    'accessTokenResourceOwnerId' => 'openid',
                    'responseError' => 'errmsg',
                    'responseCode' => 'errcode',
                ],
            ],
        ])
        ->tag('shopwell.sso.oauth2_channel');
};
