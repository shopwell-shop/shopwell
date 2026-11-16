<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Workflow\Api\WorkflowController;
use Shopwell\Core\Framework\Workflow\Api\WorkflowDraftController;
use Shopwell\Core\Framework\Workflow\Api\WorkflowPublicationController;
use Shopwell\Core\Framework\Workflow\Definition\Codec\WorkflowDocumentCodec;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Definition\Type\Loader\BuiltInWorkflowNodeTypeLoader;
use Shopwell\Core\Framework\Workflow\Definition\Type\Registry\WorkflowNodeTypeRegistry;
use Shopwell\Core\Framework\Workflow\Definition\Type\Resolver\WorkflowNodeTypeResolver;
use Shopwell\Core\Framework\Workflow\Diagnostics\Policy\StructuredWorkflowGraphPolicy;
use Shopwell\Core\Framework\Workflow\Diagnostics\Policy\WorkflowGraphPolicyRegistry;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowGraphDiagnostics;
use Shopwell\Core\Framework\Workflow\Draft\Entity\WorkflowDraftDefinition;
use Shopwell\Core\Framework\Workflow\Draft\WorkflowDraftStore;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowSubscriber;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalModeRegistry;
use Shopwell\Core\Framework\Workflow\Node\Condition\ConditionWorkflowNodeLoader;
use Shopwell\Core\Framework\Workflow\Node\Condition\ConditionWorkflowSubscriber;
use Shopwell\Core\Framework\Workflow\Permission\WorkflowDefaultRoleInstaller;
use Shopwell\Core\Framework\Workflow\Permission\WorkflowDefaultRolesInstallCommand;
use Shopwell\Core\Framework\Workflow\Permission\WorkflowDefaultRolesSubscriber;
use Shopwell\Core\Framework\Workflow\Publication\Entity\WorkflowReleaseDefinition;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowPublisherRegistry;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowReleaseStore;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimeRegistry;
use Shopwell\Core\System\AdminMenu\Service\CoreMenuSynchronizer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->import('workflow_execution.php');
    $services = $containerConfigurator->services();
    $services->set(WorkflowDefaultRoleInstaller::class)->args([
        service('acl_role.repository'),
        service('event_dispatcher'),
        service('admin_menu_item.repository'),
        service('translator'),
    ]);
    $services->set(WorkflowDefaultRolesInstallCommand::class)->args([service(WorkflowDefaultRoleInstaller::class), service(CoreMenuSynchronizer::class)])->tag('console.command');
    $services->set(WorkflowDefaultRolesSubscriber::class)->args([service(WorkflowDefaultRoleInstaller::class)])->tag('kernel.event_subscriber');
    $services->set(WorkflowReleaseDefinition::class)->tag('shopwell.entity.definition', ['entity' => 'workflow_release']);
    $services->set(WorkflowPublisherRegistry::class)->public()->args([tagged_iterator('shopwell.workflow.publisher')]);
    $services->set(WorkflowRuntimeRegistry::class)->public()->args([tagged_iterator('shopwell.workflow.runtime')]);
    $services->set(WorkflowReleaseStore::class)->public()->args([service(Connection::class), service(WorkflowDraftStore::class), service('workflow_release.repository'), service(WorkflowDocumentCodec::class), service(WorkflowGraphDiagnostics::class), service(WorkflowPublisherRegistry::class), service('event_dispatcher')]);
    $services->set(WorkflowPublicationController::class)->public()->args([service(WorkflowReleaseStore::class), service(WorkflowPublisherRegistry::class), service(WorkflowInputSchema::class)])->tag('controller.service_arguments');
    $services->set(WorkflowDraftDefinition::class)->tag('shopwell.entity.definition', ['entity' => 'workflow_draft']);
    $services->set(WorkflowDraftStore::class)->args([service(Connection::class), service('workflow_draft.repository'), service(WorkflowDocumentCodec::class)]);
    $services->set(WorkflowDraftController::class)->public()->args([service(WorkflowDraftStore::class)])->tag('controller.service_arguments');

    $services->set(ConditionWorkflowNodeLoader::class)->tag('workflow.node_type_loader');
    $services->set(ConditionWorkflowSubscriber::class)->tag('kernel.event_subscriber');
    $services->set(ApprovalWorkflowSubscriber::class)->args([service(ApprovalModeRegistry::class)])->tag('kernel.event_subscriber');

    $services->set(BuiltInWorkflowNodeTypeLoader::class)
        ->tag('workflow.node_type_loader');

    $services->set(WorkflowNodeTypeRegistry::class)
        ->args([tagged_iterator('workflow.node_type_loader')]);

    $services->set(WorkflowDocumentCodec::class)
        ->args([service('validator')]);

    $services->set(WorkflowNodeTypeResolver::class)
        ->args([service(WorkflowNodeTypeRegistry::class), service('event_dispatcher')]);

    $services->set(WorkflowGraphDiagnostics::class)
        ->args([service(WorkflowNodeTypeResolver::class), service(WorkflowGraphPolicyRegistry::class), service('event_dispatcher')]);

    $services->set(StructuredWorkflowGraphPolicy::class)
        ->tag('workflow.graph_policy');

    $services->set(WorkflowGraphPolicyRegistry::class)
        ->args([tagged_iterator('workflow.graph_policy')]);

    $services->set(WorkflowController::class)
        ->public()
        ->args([service(WorkflowNodeTypeRegistry::class), service(WorkflowDocumentCodec::class), service(WorkflowGraphDiagnostics::class)])
        ->tag('controller.service_arguments');
};
