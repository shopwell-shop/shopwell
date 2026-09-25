<?php declare(strict_types=1);

/**
 * @codeCoverageIgnore - DI wiring only
 */

use Shopwell\Core\Content\ProductExport\Tracking\Extension\CustomerSalesChannelTrackingExtension;
use Shopwell\Core\Content\ProductExport\Tracking\Extension\OrderSalesChannelTrackingExtension;
use Shopwell\Core\Content\ProductExport\Tracking\Extension\SalesChannelProductExportTrackingExtension;
use Shopwell\Core\Content\ProductExport\Tracking\SalesChannelTrackingCustomerDefinition;
use Shopwell\Core\Content\ProductExport\Tracking\SalesChannelTrackingListener;
use Shopwell\Core\Content\ProductExport\Tracking\SalesChannelTrackingOrderDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(SalesChannelTrackingOrderDefinition::class)
        ->tag('shopwell.entity.definition', ['entity' => 'sales_channel_tracking_order']);

    $services->set(SalesChannelTrackingCustomerDefinition::class)
        ->tag('shopwell.entity.definition', ['entity' => 'sales_channel_tracking_customer']);

    $services->set(OrderSalesChannelTrackingExtension::class)
        ->tag('shopwell.entity.extension');

    $services->set(CustomerSalesChannelTrackingExtension::class)
        ->tag('shopwell.entity.extension');

    $services->set(SalesChannelProductExportTrackingExtension::class)
        ->tag('shopwell.entity.extension');

    $services->set(SalesChannelTrackingListener::class)
        ->args([
            service('sales_channel.repository'),
            service('sales_channel_tracking_order.repository'),
            service('sales_channel_tracking_customer.repository'),
            service('logger'),
            service('request_stack'),
            service('cache.object'),
        ])
        ->tag('kernel.event_subscriber');
};
