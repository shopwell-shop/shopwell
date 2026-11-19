<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\ApprovalFlow\Definition\ApprovalFlowDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\Command\GraphDiagnoseCommand;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftService;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftStore;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftWriteGuard;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphCodec;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphSchemaValidator;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Command\NodeTypeListCommand;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeSourceDirectory;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\YamlNodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Registry\NodeTypeRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Mutation\GraphMutator;
use Shopwell\Core\Framework\ApprovalFlow\Publication\GraphPlanCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Publication\GraphPublicationPreflight;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ApprovalFlowDefinition::class)
        ->tag('shopwell.entity.definition');
    $services->set(ApprovalDraftDefinition::class)
        ->tag('shopwell.entity.definition');
    $services->set(ApprovalDraftWriteGuard::class)
        ->tag('kernel.event_subscriber');
    $services->set(ApprovalDraftStore::class)
        ->args([
            service(Connection::class),
            service('approval_flow_definition.repository'),
            service('approval_flow_draft.repository'),
        ]);
    $services->set(ApprovalDraftService::class)
        ->args([
            service(ApprovalDraftStore::class),
            service(GraphCodec::class),
            service(GraphDiagnostics::class),
            service(GraphMutator::class),
        ]);

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

    $graphSchema = (new Filesystem())->readFile(__DIR__ . '/../ApprovalFlow/Resources/schema/graph/1.0.0/approval-graph.schema.json');
    $services->set(GraphSchemaValidator::class);
    $services->set(GraphJsonDecoder::class);
    $services->set(GraphDiagnostics::class)
        ->args([service(NodeTypeRegistry::class), service(GraphSchemaValidator::class), $graphSchema, service(GraphJsonDecoder::class)]);
    $services->set(GraphCodec::class)
        ->args([service(GraphDiagnostics::class), service(GraphJsonDecoder::class)]);
    $services->set(GraphPublicationPreflight::class)
        ->args([
            service(GraphCodec::class),
            service(GraphDiagnostics::class),
            service(NodeTypeRegistry::class),
            service(GraphJsonDecoder::class),
            $graphSchema,
        ]);
    $services->set(GraphPlanCompiler::class)
        ->args([service(GraphPublicationPreflight::class), service(GraphJsonDecoder::class)]);
    $services->set(GraphMutator::class)
        ->args([service(GraphCodec::class), service(GraphDiagnostics::class), service(GraphJsonDecoder::class)]);
    $services->set(GraphDiagnoseCommand::class)
        ->args([service('filesystem'), service(GraphDiagnostics::class)])
        ->tag('console.command');
};
