<?php declare(strict_types=1);

namespace Shopwell\Core\Content\DependencyInjection;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Content\Cms\Service\CmsFormSlotConfigResolver;
use Shopwell\Core\Content\RevocationRequest\SalesChannel\RevocationRequestRoute;
use Shopwell\Core\Content\RevocationRequest\Validation\RevocationRequestFormValidationFactory;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(RevocationRequestFormValidationFactory::class)
        ->args([
            service('event_dispatcher'),
            service(SystemConfigService::class),
        ]);

    $services->set(RevocationRequestRoute::class)
        ->public()
        ->args([
            service(RevocationRequestFormValidationFactory::class),
            service(DataValidator::class),
            service('request_stack'),
            service('shopware.rate_limiter'),
            service('event_dispatcher'),
            service(ClockInterface::class),
            service(CmsFormSlotConfigResolver::class),
        ]);
};
