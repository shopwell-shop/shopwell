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
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalNodeEntry;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalReleaseLoader;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\NodeEffectValidator;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\ApprovalExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\EndExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\NodeExecutorRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\StartExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStore;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceWriteGuard;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Operation\ApprovalOperationDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalAssigneeResolver;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphCodec;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphSchemaValidator;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Command\NodeTypeListCommand;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeSourceDirectory;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\YamlNodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Registry\NodeTypeRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Mutation\GraphMutator;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\CoreNodeCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeCompilerRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeConfigurationCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Publication\ApprovalReleaseCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Publication\Artifact\ArtifactManifestLoader;
use Shopwell\Core\Framework\ApprovalFlow\Publication\GraphPlanCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Publication\GraphPublicationPreflight;
use Shopwell\Core\Framework\ApprovalFlow\Publication\NodePlanCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseService;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseStore;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseWriteGuard;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ApprovalInstanceDefinition::class)->tag('shopwell.entity.definition');
    $services->set(ApprovalNodeExecutionDefinition::class)->tag('shopwell.entity.definition');
    $services->set(ApprovalOperationDefinition::class)->tag('shopwell.entity.definition');
    $services->set(ApprovalTaskDefinition::class)->tag('shopwell.entity.definition');
    $services->set(ApprovalInstanceWriteGuard::class)->tag('kernel.event_subscriber');
    $services->set(ApprovalAssigneeResolver::class)->args([service(Connection::class)]);
    $services->set(ApprovalInstanceStore::class)
        ->args([
            service(Connection::class),
            service('approval_flow_instance.repository'),
            service('approval_flow_operation.repository'),
            service(ApprovalInstanceWriteGuard::class),
            service('approval_flow_execution.repository'),
            service(ApprovalNodeEntry::class),
            service('approval_flow_task.repository'),
            service(ApprovalAssigneeResolver::class),
        ]);
    $services->set(ApprovalInstanceService::class)
        ->args([service(ApprovalInstanceStore::class)]);

    $services->set(ApprovalReleaseDefinition::class)->tag('shopwell.entity.definition');
    $services->set(ApprovalReleaseWriteGuard::class)->tag('kernel.event_subscriber');
    $services->set(ApprovalReleaseStore::class)
        ->args([service(Connection::class), service('approval_flow_release.repository')]);
    $services->set(ApprovalReleaseService::class)
        ->args([service(ApprovalReleaseStore::class), service(ApprovalReleaseCompiler::class)]);
    $services->set(ApprovalReleaseLoader::class)
        ->args([service(ApprovalReleaseService::class), service(ApprovalReleaseCompiler::class)]);
    $services->set(NodeEffectValidator::class);
    // Only node entry requires deployment artifacts.
    $services->set(ApprovalNodeEntry::class)
        ->lazy()
        ->args([
            service(ApprovalReleaseLoader::class),
            service(NodeConfigurationCompiler::class),
            service(NodeExecutorRegistry::class),
            service(GraphJsonDecoder::class),
            service(NodeTypeRegistry::class),
            service(NodeEffectValidator::class),
        ]);
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
    $services->set(CoreNodeCompiler::class)
        ->args([service(NodeTypeRegistry::class), service(GraphJsonDecoder::class), service(GraphSchemaValidator::class)])
        ->tag('approval_flow.node_compiler');
    $services->set(NodeCompilerRegistry::class)
        ->args([tagged_iterator('approval_flow.node_compiler')]);
    $services->set(StartExecutor::class)->tag('approval_flow.node_executor');
    $services->set(ApprovalExecutor::class)->tag('approval_flow.node_executor');
    $services->set(EndExecutor::class)->tag('approval_flow.node_executor');
    $services->set(NodeExecutorRegistry::class)
        ->args([tagged_iterator('approval_flow.node_executor')]);
    $services->set(NodeConfigurationCompiler::class)
        ->args([service(NodeCompilerRegistry::class), service(GraphJsonDecoder::class)]);
    $services->set(NodePlanCompiler::class)
        ->args([service(GraphPlanCompiler::class), service(NodeConfigurationCompiler::class), service(GraphJsonDecoder::class), service(NodeExecutorRegistry::class)]);
    $artifactSchema = (new Filesystem())->readFile(__DIR__ . '/../ApprovalFlow/Resources/schema/publication/1.0.0/artifact-manifest.schema.json');
    $services->set(ArtifactManifestLoader::class)
        ->args([
            service('filesystem'),
            service(GraphJsonDecoder::class),
            service(GraphSchemaValidator::class),
            $artifactSchema,
            '%env(resolve:APPROVAL_FLOW_ARTIFACT_MANIFEST)%',
        ]);
    $services->set(ApprovalReleaseCompiler::class)
        ->args([
            service(NodePlanCompiler::class),
            service(NodeCompilerRegistry::class),
            service(NodeExecutorRegistry::class),
            service(GraphJsonDecoder::class),
            service(ArtifactManifestLoader::class),
        ]);
    $services->set(GraphMutator::class)
        ->args([service(GraphCodec::class), service(GraphDiagnostics::class), service(GraphJsonDecoder::class)]);
    $services->set(GraphDiagnoseCommand::class)
        ->args([service('filesystem'), service(GraphDiagnostics::class)])
        ->tag('console.command');
};
