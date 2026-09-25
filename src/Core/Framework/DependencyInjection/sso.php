<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Content\Mail\Service\MailService;
use Shopwell\Core\Framework\Api\OAuth\AuthCodeRepository;
use Shopwell\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Shopwell\Core\Framework\Sso\Config\LoginConfigService;
use Shopwell\Core\Framework\Sso\Controller\SsoController;
use Shopwell\Core\Framework\Sso\LoginResponseService;
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
};
