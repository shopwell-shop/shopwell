<?php declare(strict_types=1);

namespace Shopwell\Core\Content\DependencyInjection;

use Shopwell\Core\Content\LegalGuaranteeNotice\LegalGuaranteeNoticeRenderer;
use Shopwell\Core\Content\LegalGuaranteeNotice\LegalGuaranteeNoticeTwigFilter;
use Shopwell\Core\Content\LegalGuaranteeNotice\SalesChannel\AbstractLegalGuaranteeNoticeRoute;
use Shopwell\Core\Content\LegalGuaranteeNotice\SalesChannel\LegalGuaranteeNoticeRoute;
use Shopwell\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(LegalGuaranteeNoticeRenderer::class)
        ->args([
            service('twig'),
            service(LanguageLocaleCodeProvider::class),
        ]);

    $services->set(LegalGuaranteeNoticeTwigFilter::class)
        ->args([
            service(LegalGuaranteeNoticeRenderer::class),
        ])
        ->tag('twig.extension');

    $services->set(LegalGuaranteeNoticeRoute::class)
        ->public()
        ->args([
            service(SystemConfigService::class),
            service(LegalGuaranteeNoticeRenderer::class),
        ]);

    $services->alias(AbstractLegalGuaranteeNoticeRoute::class, LegalGuaranteeNoticeRoute::class);
};
