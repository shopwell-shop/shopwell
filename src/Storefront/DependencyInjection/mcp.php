<?php declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Mcp\Context\McpContextProvider;
use Shopwell\Storefront\Mcp\Tool\ThemeConfigTool;
use Shopwell\Storefront\Theme\ThemeService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ThemeConfigTool::class)
        ->args([
            service(ThemeService::class),
            service(McpContextProvider::class),
            service(Connection::class),
        ])
        ->tag('mcp.tool');
};
