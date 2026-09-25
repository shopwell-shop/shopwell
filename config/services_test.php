<?php declare(strict_types=1);

use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Test\TestNavigationSeoUrlRoute;
use Shopwell\Core\Content\Test\TestProductSeoUrlRoute;
use Shopwell\Core\Framework\Telemetry\Metrics\Config\TransportConfigProvider;
use Shopwell\Core\Framework\Telemetry\Metrics\Transport\TransportCollection;
use Shopwell\Core\Framework\Test\Telemetry\Factory\TraceableTransportFactory;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Tests\Integration\Core\Content\Seo\SalesChannel\FixturesPhp\StoreApiSeoResolverTestRoute;
use Shopwell\Tests\Integration\Core\Framework\Api\EventListener\FixturesPhp\SalesChannelAuthenticationListenerTestRoute;
use Shopwell\Tests\Integration\Core\Framework\App\AppFixture;
use Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntity;
use Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntityAgg;
use Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntityWithHydrator;
use Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntityWithInheritance;
use Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntityWithSearchRanking;
use Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\DummyHydrator;
use Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer\Version\CalculatedPriceFieldTestDefinition;
use Shopwell\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures\TestConstantTaxRateProvider;
use Shopwell\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures\TestEmptyTaxProvider;
use Shopwell\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures\TestGenericExceptionTaxProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\iterator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('shopwell.messenger.enforce_message_size', true);

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autoconfigure();

    $services->set(SalesChannelAuthenticationListenerTestRoute::class)
        ->tag('controller.service_arguments');

    $services->set(StoreApiSeoResolverTestRoute::class)
        ->args([
            service(CategoryRoute::class),
            service(SalesChannelContextFactory::class),
        ])
        ->tag('controller.service_arguments');

    $services->set(CalculatedPriceFieldTestDefinition::class)
        ->tag('shopwell.entity.definition');

    // Payment
    $services->set(TestConstantTaxRateProvider::class)
        ->tag('shopwell.tax.provider');

    $services->set(TestEmptyTaxProvider::class)
        ->tag('shopwell.tax.provider');

    $services->set(TestGenericExceptionTaxProvider::class)
        ->tag('shopwell.tax.provider');

    // Route
    $services->set(TestNavigationSeoUrlRoute::class)
        ->args([
            service(CategoryDefinition::class),
        ])
        ->tag('shopwell.seo_url.route');

    $services->set(TestProductSeoUrlRoute::class)
        ->args([
            service(ProductDefinition::class),
        ])
        ->tag('shopwell.seo_url.route');

    $services->set(AttributeEntity::class)
        ->tag('shopwell.entity');

    $services->set(AttributeEntityAgg::class);

    $services->set(AttributeEntityWithHydrator::class)
        ->tag('shopwell.entity');

    $services->set(AttributeEntityWithInheritance::class)
        ->tag('shopwell.entity');

    $services->set(AttributeEntityWithSearchRanking::class)
        ->tag('shopwell.entity');

    $services->set(DummyHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(AppFixture::class)
        ->public()
        ->args([
            service('app.repository'),
        ]);

    $services->set(TraceableTransportFactory::class)
        ->tag('shopwell.metric_transport_factory');

    $services->set(TransportCollection::class)
        ->lazy()
        ->factory([TransportCollection::class, 'create'])
        ->args([
            iterator([
                service(TraceableTransportFactory::class),
            ]),
            service(TransportConfigProvider::class),
        ]);
};
