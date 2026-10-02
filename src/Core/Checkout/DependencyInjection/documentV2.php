<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Customer\Service\GuestAuthenticator;
use Shopwell\Core\Checkout\Document\Service\DocumentGenerator as LegacyDocumentGenerator;
use Shopwell\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopwell\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelDefinition;
use Shopwell\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileDefinition;
use Shopwell\Core\Checkout\DocumentV2\App\DocumentAppFeatureDefinition;
use Shopwell\Core\Checkout\DocumentV2\Config\DocumentConfigLoader;
use Shopwell\Core\Checkout\DocumentV2\Config\DocumentNumberGenerator;
use Shopwell\Core\Checkout\DocumentV2\Controller\DocumentV2Controller;
use Shopwell\Core\Checkout\DocumentV2\DocumentDefinition;
use Shopwell\Core\Checkout\DocumentV2\Generation\DocumentArchiveGenerator;
use Shopwell\Core\Checkout\DocumentV2\Generation\DocumentDependencyResolver;
use Shopwell\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequestResolver;
use Shopwell\Core\Checkout\DocumentV2\Generation\DocumentGenerator;
use Shopwell\Core\Checkout\DocumentV2\Generation\DocumentPersister;
use Shopwell\Core\Checkout\DocumentV2\Generation\ReferencedDocumentResolver;
use Shopwell\Core\Checkout\DocumentV2\Provider\CancellationInvoiceDataProvider;
use Shopwell\Core\Checkout\DocumentV2\Provider\CreditNoteDataProvider;
use Shopwell\Core\Checkout\DocumentV2\Provider\DeliveryNoteDataProvider;
use Shopwell\Core\Checkout\DocumentV2\Provider\DocumentDataProviderRegistry;
use Shopwell\Core\Checkout\DocumentV2\Provider\DocumentMetaProvider;
use Shopwell\Core\Checkout\DocumentV2\Provider\InvoiceDataProvider;
use Shopwell\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopwell\Core\Checkout\DocumentV2\Renderer\HtmlRenderer;
use Shopwell\Core\Checkout\DocumentV2\Renderer\PdfRenderer;
use Shopwell\Core\Checkout\DocumentV2\Renderer\ZugferdEmbeddedPdfRenderer;
use Shopwell\Core\Checkout\DocumentV2\Renderer\ZugferdXmlRenderer;
use Shopwell\Core\Checkout\DocumentV2\SalesChannel\DocumentRoute;
use Shopwell\Core\Checkout\DocumentV2\Service\CreditItemResolver;
use Shopwell\Core\Checkout\DocumentV2\Service\DocumentFileNameBuilder;
use Shopwell\Core\Checkout\DocumentV2\Service\DocumentFileResolver;
use Shopwell\Core\Checkout\DocumentV2\Service\DocumentReader;
use Shopwell\Core\Checkout\DocumentV2\Service\ReferenceInvoiceLoader;
use Shopwell\Core\Checkout\DocumentV2\Subscriber\DocumentBaseConfigSyncSubscriber;
use Shopwell\Core\Checkout\DocumentV2\Subscriber\DocumentTypeNameSyncSubscriber;
use Shopwell\Core\Checkout\DocumentV2\Template\DocumentTemplateRenderer;
use Shopwell\Core\Checkout\DocumentV2\Template\ZugferdTwigExtension;
use Shopwell\Core\Checkout\DocumentV2\Type\CancellationInvoiceDocumentType;
use Shopwell\Core\Checkout\DocumentV2\Type\CreditNoteDocumentType;
use Shopwell\Core\Checkout\DocumentV2\Type\DeliveryNoteDocumentType;
use Shopwell\Core\Checkout\DocumentV2\Type\DocumentTypeRegistry;
use Shopwell\Core\Checkout\DocumentV2\Type\InvoiceDocumentType;
use Shopwell\Core\Checkout\DocumentV2\Xml\XmlFormatter;
use Shopwell\Core\Content\Media\File\FileNameProvider;
use Shopwell\Core\Content\Media\MediaService;
use Shopwell\Core\Framework\Adapter\Translation\Translator;
use Shopwell\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopwell\Core\Framework\App\Feature\AppFeatureStorage;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Script\Execution\ScriptExecutor;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(DocumentDefinition::class)
        ->tag('shopwell.entity.definition')
        ->tag('shopwell.entity.hookable');

    $services->set(DocumentBaseConfigDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(DocumentBaseConfigSalesChannelDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(DocumentFileDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(DocumentNumberGenerator::class)
        ->args([
            service(NumberRangeValueGeneratorInterface::class),
        ]);

    $services->set(DocumentFileResolver::class);

    $services->set(DocumentConfigLoader::class)
        ->args([
            service('document_base_config.repository'),
            service('country.repository'),
            service('media.repository'),
            service(SystemConfigService::class),
            service(DocumentTypeRegistry::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(DocumentBaseConfigSyncSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('shopwell.inactiveFeature', ['flag' => 'v6.9.0.0']);

    $services->set(DocumentTypeNameSyncSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('shopwell.inactiveFeature', ['flag' => 'v6.9.0.0']);

    $services->set(DocumentMetaProvider::class)
        ->args([
            service(DocumentConfigLoader::class),
        ])
        ->tag('shopwell.document_v2.provider');

    $services->set(InvoiceDataProvider::class)
        ->public()
        ->args([
            service(DocumentConfigLoader::class),
            service(DocumentTypeRegistry::class),
            service('validator'),
        ])
        ->tag('shopwell.document_v2.provider');

    $services->set(DeliveryNoteDataProvider::class)
        ->public()
        ->tag('shopwell.document_v2.provider');

    $services->set(CreditItemResolver::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(CancellationInvoiceDataProvider::class)
        ->public()
        ->args([
            service(InvoiceDataProvider::class),
        ])
        ->tag('shopwell.document_v2.provider');

    $services->set(CreditNoteDataProvider::class)
        ->public()
        ->args([
            service(InvoiceDataProvider::class),
            service(CreditItemResolver::class),
        ])
        ->tag('shopwell.document_v2.provider');

    $services->set(DocumentDataProviderRegistry::class)
        ->args([
            tagged_iterator('shopwell.document_v2.provider'),
        ]);

    $services->set(InvoiceDocumentType::class)
        ->tag('shopwell.document_v2.type');

    $services->set(CancellationInvoiceDocumentType::class)
        ->tag('shopwell.document_v2.type');

    $services->set(DeliveryNoteDocumentType::class)
        ->tag('shopwell.document_v2.type');

    $services->set(CreditNoteDocumentType::class)
        ->tag('shopwell.document_v2.type');

    $services->set(DocumentAppFeatureDefinition::class)
        ->args([
            service(Connection::class),
            service('number_range_type.repository'),
            service('number_range.repository'),
        ])
        ->tag('shopwell.app_feature.definition');

    $services->set(DocumentTypeRegistry::class)
        ->args([
            tagged_iterator('shopwell.document_v2.type'),
            service(AppFeatureStorage::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(DocumentTemplateRenderer::class)
        ->public()
        ->args([
            service(TemplateFinder::class),
            service('twig'),
            service(Translator::class),
            service(SalesChannelContextFactory::class),
            service('event_dispatcher'),
            param('kernel.project_dir'),
        ]);

    $services->set(ZugferdTwigExtension::class)
        ->tag('twig.extension');

    $services->set(HtmlRenderer::class)
        ->public()
        ->args([
            service(DocumentTemplateRenderer::class),
        ])
        ->tag('shopwell.document_v2.renderer');

    $services->set(XmlFormatter::class);

    $services->set(ZugferdXmlRenderer::class)
        ->public()
        ->args([
            service(DocumentTemplateRenderer::class),
            service(XmlFormatter::class),
        ])
        ->tag('shopwell.document_v2.renderer');

    $services->set(PdfRenderer::class)
        ->public()
        ->args([
            param('shopwell.dompdf.options'),
        ])
        ->tag('shopwell.document_v2.renderer');

    $services->set(ZugferdEmbeddedPdfRenderer::class)
        ->public()
        ->args([
            param('kernel.shopwell_version'),
        ])
        ->tag('shopwell.document_v2.renderer');

    $services->set(DocumentRendererRegistry::class)
        ->args([
            tagged_iterator('shopwell.document_v2.renderer'),
        ]);

    $services->set(DocumentDependencyResolver::class)
        ->args([
            service(DocumentRendererRegistry::class),
        ]);

    $services->set(DocumentArchiveGenerator::class)
        ->args([
            service(MediaService::class),
            service(Filesystem::class),
            service(DocumentRendererRegistry::class),
            service(DocumentFileNameBuilder::class),
        ]);

    $services->set(DocumentFileNameBuilder::class)
        ->args([
            service(ClockInterface::class),
        ]);

    $services->set(DocumentPersister::class)
        ->args([
            service('document.repository'),
            service('document_file.repository'),
            service('document_type.repository'),
            service(MediaService::class),
            service(DocumentTypeRegistry::class),
            service(FileNameProvider::class),
            service('event_dispatcher'),
        ]);

    $services->set(DocumentReader::class)
        ->args([
            service('document.repository'),
            service(MediaService::class),
            service(DocumentRendererRegistry::class),
            service(DocumentFileResolver::class),
        ]);

    $services->set(ReferenceInvoiceLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(ReferencedDocumentResolver::class)
        ->args([
            service(ReferenceInvoiceLoader::class),
            service(Connection::class),
        ]);

    $services->set(DocumentGenerator::class)
        ->public()
        ->args([
            service(DocumentDataProviderRegistry::class),
            service(DocumentRendererRegistry::class),
            service(DocumentNumberGenerator::class),
            service(DocumentPersister::class),
            service(DocumentDependencyResolver::class),
            service(ReferencedDocumentResolver::class),
            service('order.repository'),
            service(ScriptExecutor::class),
        ]);

    $services->set(DocumentRoute::class)
        ->public()
        ->args([
            service(LegacyDocumentGenerator::class),
            service(DocumentReader::class),
            service('document.repository'),
            service('shopwell.rate_limiter'),
            service(GuestAuthenticator::class),
            tagged_iterator('document_type.renderer', 'key'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(DocumentGenerationRequestResolver::class)
        ->args([
            service(DataValidator::class),
            service(DocumentTypeRegistry::class),
        ])
        ->tag('controller.argument_value_resolver');

    $services->set(DocumentV2Controller::class)
        ->public()
        ->args([
            service(DocumentGenerator::class),
            service(DocumentReader::class),
            service(DocumentTypeRegistry::class),
            service(DocumentArchiveGenerator::class),
            service('document.repository'),
            service(DocumentPersister::class),
            service(MediaService::class),
            service(FileNameProvider::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);
};
