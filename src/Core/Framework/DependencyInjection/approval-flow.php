<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Command\NodeTypeListCommand;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeSourceDirectory;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\YamlNodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Registry\NodeTypeRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->set(YamlNodeTypeLoader::class)
        ->args([
            service('filesystem'),
            [inline_service(NodeTypeSourceDirectory::class)->args(['core', __DIR__ . '/../ApprovalFlow/Resources/node-types'])],
        ])
        ->tag('approval_flow.node_type_loader');

    $services->set(NodeTypeRegistry::class)
        ->args([tagged_iterator('approval_flow.node_type_loader')]);

    $services->set(NodeTypeListCommand::class)
        ->args([service(NodeTypeRegistry::class)])
        ->tag('console.command');
};
