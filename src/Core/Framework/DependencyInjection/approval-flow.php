<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\ApprovalFlow\Api\ApprovalDesignController;
use Shopwell\Core\Framework\ApprovalFlow\Api\ApprovalReleaseController;
use Shopwell\Core\Framework\ApprovalFlow\Api\ApprovalRequestController;
use Shopwell\Core\Framework\ApprovalFlow\Api\ApprovalTaskController;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingHeadDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingService;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingStore;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingWriteGuard;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Binding\Command\ApprovalBusinessTypeListCommand;
use Shopwell\Core\Framework\ApprovalFlow\Definition\ApprovalFlowDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\Command\GraphDiagnoseCommand;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftService;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftStore;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftWriteGuard;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalExecutionPreparation;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalExecutionStore;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalFlowExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalNodeEntry;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalProgressionRunner;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalReleaseLoader;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalTransitionPlanner;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Branch\ApprovalExecutionBranchDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Command\AdvanceApprovalHandler;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Command\EnterApprovalNodeHandler;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Command\EnterEndNodeHandler;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Command\EnterStartNodeHandler;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Command\ReturnApprovalHandler;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Command\StartInstanceHandler;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Command\WithdrawInstanceHandler;
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
use Shopwell\Core\Framework\ApprovalFlow\Execution\Policy\AllApprovalPolicy;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Policy\AnyApprovalPolicy;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Policy\ApprovalPolicyRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Policy\SingleApprovalPolicy;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Scope\ApprovalExecutionScopeDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalAssigneeResolver;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalDecisionEvaluator;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskDecisionStore;
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
use Shopwell\Core\Framework\ApprovalFlow\Query\ApprovalDesignCatalog;
use Shopwell\Core\Framework\ApprovalFlow\Query\ApprovalFormReader;
use Shopwell\Core\Framework\ApprovalFlow\Query\ApprovalRequestQuery;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseService;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseStore;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseWriteGuard;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestService;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestStore;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultDefinition;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultDispatcher;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultHandlerRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultOutbox;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultStore;
use Shopwell\Core\Framework\ApprovalFlow\ScheduledTask\DeliverApprovalResultsTask;
use Shopwell\Core\Framework\ApprovalFlow\ScheduledTask\DeliverApprovalResultsTaskHandler;
use Shopwell\Core\Framework\ApprovalFlow\ScheduledTask\ProgressApprovalFlowsTask;
use Shopwell\Core\Framework\ApprovalFlow\ScheduledTask\ProgressApprovalFlowsTaskHandler;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $graphSchema = (new Filesystem())->readFile(__DIR__ . '/../ApprovalFlow/Resources/schema/graph/1.0.0/approval-graph.schema.json');
    $services->set(ApprovalDesignCatalog::class)->args([service(NodeTypeRegistry::class), service(ApprovalBusinessTypeRegistry::class), $graphSchema]);
    $services->set(ApprovalFormReader::class);
    $services->set(ApprovalRequestQuery::class)->args([
        service('approval_flow_request.repository'), service('approval_flow_execution.repository'), service('approval_flow_operation.repository'), service(ApprovalFormReader::class),
        service('user.repository'), service(Connection::class),
    ]);
    $services->set(ApprovalDesignController::class)->args([service(ApprovalDesignCatalog::class), service(GraphDiagnostics::class), service(ApprovalDraftService::class)])->tag('controller.service_arguments');
    $services->set(ApprovalReleaseController::class)->args([
        service(ApprovalDraftService::class), service(ApprovalReleaseService::class), service(ApprovalBindingService::class),
    ])->tag('controller.service_arguments');
    $services->set(ApprovalTaskController::class)->args([service(ApprovalInstanceService::class)])->tag('controller.service_arguments');
    $services->set(ApprovalRequestController::class)->args([service(ApprovalRequestQuery::class)])->tag('controller.service_arguments');

    $services->set(ApprovalProgressionRunner::class)->args([
        service(ApprovalInstanceStore::class), service(ApprovalInstanceService::class), service('logger'),
    ]);
    $services->set(ProgressApprovalFlowsTask::class)->tag('shopwell.scheduled.task');
    $services->set(ProgressApprovalFlowsTaskHandler::class)
        ->args([service('scheduled_task.repository'), service('logger'), service(ApprovalProgressionRunner::class)])
        ->tag('messenger.message_handler');

    $services->set(ApprovalRequestDefinition::class)->tag('shopwell.entity.definition');
    $services->set(ApprovalRequestStore::class)->args([
        service(Connection::class), service('approval_flow_request.repository'), service(ApprovalInstanceWriteGuard::class), service(ClockInterface::class),
    ]);
    $services->set(ApprovalRequestService::class)->args([
        service(ApprovalRequestStore::class), service(ApprovalBindingService::class), service(GraphSchemaValidator::class), service(ApprovalInstanceStore::class),
    ]);

    $services->set(ApprovalBindingDefinition::class)->tag('shopwell.entity.definition');
    $services->set(ApprovalBindingHeadDefinition::class)->tag('shopwell.entity.definition');
    $services->set(ApprovalBindingWriteGuard::class)->tag('kernel.event_subscriber');
    $services->set(ApprovalBindingStore::class)->args([
        service(Connection::class), service('approval_flow_binding.repository'), service('approval_flow_binding_head.repository'), service(ApprovalBindingWriteGuard::class), service(ClockInterface::class),
    ]);
    $services->set(ApprovalBindingService::class)->args([
        service(ApprovalBindingStore::class), service(ApprovalBusinessTypeRegistry::class), service(ApprovalReleaseLoader::class), service('event_dispatcher'),
    ]);

    $services->set(ApprovalBusinessTypeRegistry::class)
        ->args([tagged_iterator('approval_flow.business_type_loader')]);
    $services->set(ApprovalBusinessTypeListCommand::class)
        ->args([service(ApprovalBusinessTypeRegistry::class)])
        ->tag('console.command');

    $services->set(ApprovalInstanceDefinition::class)->tag('shopwell.entity.definition');
    $services->set(ApprovalNodeExecutionDefinition::class)->tag('shopwell.entity.definition');
    $services->set(ApprovalExecutionScopeDefinition::class)->tag('shopwell.entity.definition');
    $services->set(ApprovalExecutionBranchDefinition::class)->tag('shopwell.entity.definition');
    $services->set(ApprovalOperationDefinition::class)->tag('shopwell.entity.definition');
    $services->set(ApprovalTaskDefinition::class)->tag('shopwell.entity.definition');
    $services->set(DeliverApprovalResultsTask::class)->tag('shopwell.scheduled.task');
    $services->set(DeliverApprovalResultsTaskHandler::class)
        ->args([service('scheduled_task.repository'), service('logger'), service(ApprovalResultDispatcher::class)])
        ->tag('messenger.message_handler');
    $services->set(ApprovalResultHandlerRegistry::class)->args([tagged_iterator('approval_flow.result_handler')]);
    $services->set(ApprovalResultDispatcher::class)->args([
        service(ApprovalResultOutbox::class), service('approval_flow_request.repository'), service('approval_flow_binding.repository'),
        service(ApprovalResultHandlerRegistry::class), service('logger'),
    ]);
    $services->set(ApprovalResultDefinition::class)->tag('shopwell.entity.definition');
    $services->set(ApprovalResultStore::class)->args([
        service(Connection::class), service('approval_flow_result.repository'), service(ApprovalInstanceWriteGuard::class), service(ClockInterface::class),
    ]);
    $services->set(ApprovalResultOutbox::class)->args([service(Connection::class), service(ClockInterface::class)]);
    $services->set(ApprovalInstanceWriteGuard::class)->tag('kernel.event_subscriber');
    $services->set(ApprovalAssigneeResolver::class)->args([service(Connection::class)]);
    $services->set(SingleApprovalPolicy::class)->tag('approval_flow.approval_policy');
    $services->set(AllApprovalPolicy::class)->tag('approval_flow.approval_policy');
    $services->set(AnyApprovalPolicy::class)->tag('approval_flow.approval_policy');
    $services->set(ApprovalPolicyRegistry::class)->args([tagged_iterator('approval_flow.approval_policy')]);
    $services->set(ApprovalDecisionEvaluator::class)->lazy()->args([service(ApprovalPolicyRegistry::class), service(ApprovalReleaseLoader::class)]);
    $services->set(ApprovalTaskDecisionStore::class)->args([
        service(Connection::class),
        service('approval_flow_task.repository'),
        service('approval_flow_execution.repository'),
        service(ApprovalInstanceWriteGuard::class),
        service(ApprovalDecisionEvaluator::class),
        service(ApprovalExecutionStore::class),
    ]);
    $services->set(ApprovalExecutionStore::class)->args([
        service(Connection::class), service('approval_flow_execution.repository'),
        service('approval_flow_execution_scope.repository'), service('approval_flow_execution_branch.repository'),
        service('approval_flow_task.repository'), service(ApprovalInstanceWriteGuard::class),
    ]);
    $services->set(ApprovalTransitionPlanner::class);
    $services->set(ApprovalExecutionPreparation::class)->args([
        service(ApprovalExecutionStore::class),
        service(ApprovalNodeEntry::class),
        service(ApprovalTransitionPlanner::class),
        service(ApprovalAssigneeResolver::class),
    ]);
    $services->set(ApprovalFlowExecutor::class)->args([service(ApprovalExecutionStore::class), service(ApprovalExecutionPreparation::class)]);
    $services->set(StartInstanceHandler::class)->args([
        service(ApprovalExecutionStore::class), service(ApprovalFlowExecutor::class), service(ApprovalReleaseLoader::class),
    ]);
    $services->set(WithdrawInstanceHandler::class)->args([service(ApprovalExecutionStore::class)]);
    foreach ([EnterStartNodeHandler::class, EnterApprovalNodeHandler::class, EnterEndNodeHandler::class] as $handler) {
        $services->set($handler)->args([
            service(ApprovalExecutionStore::class), service(ApprovalFlowExecutor::class),
            service(ApprovalNodeEntry::class), service(ApprovalReleaseLoader::class), service(ApprovalExecutionPreparation::class),
        ]);
    }
    $advanceApprovalArgs = [
        service(ApprovalExecutionStore::class),
        service(ApprovalFlowExecutor::class),
        service(ApprovalReleaseLoader::class),
        service(ApprovalTransitionPlanner::class),
    ];
    $services->set(AdvanceApprovalHandler::class)->args([...$advanceApprovalArgs, false]);
    $services->set('approval_flow.progress_handler', AdvanceApprovalHandler::class)->args([...$advanceApprovalArgs, true]);
    $services->set(ReturnApprovalHandler::class)->args([
        service(ApprovalTaskDecisionStore::class),
        service(ApprovalExecutionStore::class),
        service(ApprovalReleaseLoader::class),
        service(ApprovalExecutionPreparation::class),
        service(ApprovalFlowExecutor::class),
        service('event_dispatcher'),
    ]);
    $services->set(ApprovalInstanceStore::class)
        ->args([
            service(Connection::class),
            service('approval_flow_instance.repository'),
            service('approval_flow_operation.repository'),
            service(ApprovalInstanceWriteGuard::class),
            [
                ApprovalInstanceStore::START => service(StartInstanceHandler::class),
                ApprovalInstanceStore::WITHDRAW => service(WithdrawInstanceHandler::class),
                ApprovalInstanceStore::ENTER_START => service(EnterStartNodeHandler::class),
                ApprovalInstanceStore::ENTER_APPROVAL => service(EnterApprovalNodeHandler::class),
                ApprovalInstanceStore::ADVANCE_APPROVAL => service(AdvanceApprovalHandler::class),
                ApprovalInstanceStore::PROGRESS => service('approval_flow.progress_handler'),
                ApprovalInstanceStore::ENTER_END => service(EnterEndNodeHandler::class),
            ],
            service(ApprovalTaskDecisionStore::class),
            service(ApprovalResultStore::class),
            service(ReturnApprovalHandler::class),
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
        ->lazy()
        ->args([service(ApprovalReleaseStore::class), service(ApprovalReleaseCompiler::class), service(GraphJsonDecoder::class)]);
    $services->set(NodeEffectValidator::class);
    // Resolve deployment artifacts only when a command actually loads a release.
    $services->set(ApprovalNodeEntry::class)
        ->lazy()
        ->args([
            service(NodeConfigurationCompiler::class),
            service(NodeExecutorRegistry::class),
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

    $services->set(GraphSchemaValidator::class);
    $services->set(GraphJsonDecoder::class);
    $services->set(GraphDiagnostics::class)
        ->args([service(NodeTypeRegistry::class), service(GraphSchemaValidator::class), $graphSchema, service(GraphJsonDecoder::class)]);
    $services->set(GraphCodec::class)
        ->args([service(GraphDiagnostics::class), service(GraphJsonDecoder::class)]);
    $services->set(GraphPublicationPreflight::class)
        ->args([
            service(GraphCodec::class),
            service(NodeTypeRegistry::class),
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
            service(ApprovalPolicyRegistry::class),
        ]);
    $services->set(GraphMutator::class)
        ->args([service(GraphCodec::class), service(GraphJsonDecoder::class)]);
    $services->set(GraphDiagnoseCommand::class)
        ->args([service('filesystem'), service(GraphDiagnostics::class)])
        ->tag('console.command');
};
