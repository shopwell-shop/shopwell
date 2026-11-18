<?php declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Workflow\Definition\Codec\WorkflowDocumentCodec;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowEntityFields;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowGraphDiagnostics;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowCopyController;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowCopyPresenter;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowExecutionDefinitionController;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowExecutionDefinitionPresenter;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowInstanceController;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowInstancePresenter;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowPayloadProtection;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowTaskController;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowTaskPresenter;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowUserLookup;
use Shopwell\Core\Framework\Workflow\Execution\ApprovalWorkflowPreviewSubscriber;
use Shopwell\Core\Framework\Workflow\Execution\ApprovalWorkflowRuntime;
use Shopwell\Core\Framework\Workflow\Execution\ApprovalWorkflowSummarySubscriber;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\ApprovalAssigneeResolver;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverInterface;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverRegistry;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\DepartmentLeaderAssigneeResolver;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\FormFieldAssigneeResolver;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\InitiatorAssigneeResolver;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\OrganizationAssigneeContext;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\OrganizationAssigneeLookup;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\PositionAssigneeResolver;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\RoleAssigneeResolver;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\SupervisorAssigneeResolver;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\UserAssigneeResolver;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ComparisonConditionEvaluator;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ConditionEvaluatorInterface;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ConditionExpressionParser;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ExprNormalizer;
use Shopwell\Core\Framework\Workflow\Execution\Condition\WorkflowConditionEvaluator;
use Shopwell\Core\Framework\Workflow\Execution\Copy\WorkflowCopyEntity;
use Shopwell\Core\Framework\Workflow\Execution\Copy\WorkflowCopyService;
use Shopwell\Core\Framework\Workflow\Execution\CounterSignCalculator;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionEntity;
use Shopwell\Core\Framework\Workflow\Execution\Executor\AutomationActionExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Executor\ConditionExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Executor\CopyExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Executor\EndExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Executor\JoinExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Executor\NodeExecutorInterface;
use Shopwell\Core\Framework\Workflow\Execution\Executor\NodeExecutorRegistry;
use Shopwell\Core\Framework\Workflow\Execution\Executor\ParallelExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Executor\ScriptExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Executor\StartExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Executor\TaskExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Log\WorkflowLogEntity;
use Shopwell\Core\Framework\Workflow\Execution\Log\WorkflowRecorder;
use Shopwell\Core\Framework\Workflow\Execution\Node\WorkflowNodeExecutionEntity;
use Shopwell\Core\Framework\Workflow\Execution\Node\WorkflowNodeExecutionStore;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\BranchMergeRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\CycleRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\EdgeConditionRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\GraphRuleInterface;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\NodePropertiesRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\ReachabilityRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\ShapeRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\SingleEndRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\SingleStartRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\WorkflowGraphValidator;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowCancellationStore;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowEngine;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowEventQueue;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNumberGenerator;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowRoute;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowTransactionRunner;
use Shopwell\Core\Framework\Workflow\Execution\Write\WorkflowDefinitionWriteProtection;
use Shopwell\Core\Framework\Workflow\Execution\Write\WorkflowRuntimeWriteProtection;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceStateHandler;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalCompletionSubscriber;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalConfigurationCompiler;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalInstancePresentationSubscriber;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalResubmission;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalTaskCompletion;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalTaskParticipation;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalTaskPolicy;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\ApprovalAssigneeRegistry;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\ApprovalAssigneeSource;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\DepartmentLeaderApprovalAssigneeSource;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\FormFieldApprovalAssigneeSource;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\FormFieldApprovalValidationSubscriber;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\InitiatorApprovalAssigneeSource;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\PositionApprovalAssigneeSource;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\PositionApprovalValidationSubscriber;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\RoleApprovalAssigneeSource;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\RoleApprovalValidationSubscriber;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\SupervisorApprovalAssigneeSource;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\UserApprovalAssigneeSource;
use Shopwell\Core\Framework\Workflow\Node\Approval\Automation\AutomaticApprovalResolver;
use Shopwell\Core\Framework\Workflow\Node\Approval\Form\ApprovalFormPolicy;
use Shopwell\Core\Framework\Workflow\Node\Approval\Form\ApprovalFormPresenter;
use Shopwell\Core\Framework\Workflow\Node\Approval\Form\ApprovalFormService;
use Shopwell\Core\Framework\Workflow\Node\Approval\Form\ApprovalFormStore;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\AllApprovalMode;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\AnyApprovalMode;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalMode;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalModeRegistry;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\FirstDecisionApprovalMode;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\SequentialApprovalMode;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\SingleApprovalMode;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderController;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderEntity;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderPolicy;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderPresenter;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderProtection;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderService;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderStore;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderSubscriber;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ScheduledTask\ApprovalReminderTask;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ScheduledTask\ApprovalReminderTaskHandler;
use Shopwell\Core\Framework\Workflow\Node\Copy\CopyWorkflowSubscriber;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalCondition;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalRoute;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowCompiler;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowPublisher;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeCompilerRegistry;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ConditionApprovalNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\CopyApprovalNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\EndApprovalNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\IntegrationApprovalNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ParallelApprovalNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ScriptApprovalNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\StartApprovalNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\TaskApprovalNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\EndWorkflowNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\StartWorkflowNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowCompiler;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowExecutionPublisher;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowNodeCompilerRegistry;
use Shopwell\Core\Framework\Workflow\Subscriber\WorkflowUnusedMediaSubscriber;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskHistoryEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStateHandler;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->instanceof(WorkflowNodeCompiler::class)->tag('shopwell.workflow.node_compiler');
    $services->instanceof(GraphRuleInterface::class)->tag('shopwell.workflow.execution.graph_rule');
    $services->instanceof(NodeExecutorInterface::class)->tag('shopwell.workflow.execution.node_executor');
    $services->instanceof(AssigneeResolverInterface::class)->tag('shopwell.workflow.execution.assignee_resolver');

    $services->instanceof(ApprovalNodeCompiler::class)->tag('shopwell.workflow.approval.node_compiler');
    $services->instanceof(ApprovalMode::class)->tag('shopwell.workflow.approval.mode');
    $services->instanceof(ApprovalAssigneeSource::class)->tag('shopwell.workflow.approval.assignee_source');

    $services->set(ApprovalFormPolicy::class);
    $services->set(ApprovalFormStore::class)->args([service(Connection::class), service(ClockInterface::class)]);
    $services->set(ApprovalFormService::class)->args([
        service(ApprovalFormPolicy::class), service(WorkflowInputSchema::class), service(ApprovalFormStore::class),
        service('workflow_instance.repository'), service('event_dispatcher'),
    ])->public();
    $services->set(ApprovalFormPresenter::class)->args([
        service(ApprovalFormService::class), service(ApprovalFormStore::class), service(WorkflowTaskFinder::class),
        service('workflow_copy.repository'), service('request_stack'), service(WorkflowUserLookup::class),
    ])->tag('kernel.event_subscriber');

    $services->set(ApprovalReminderEntity::class)->tag('shopwell.entity');
    $services->set(ApprovalReminderProtection::class)->tag('shopwell.entity.extension');
    $services->set(ApprovalReminderStore::class)->args([
        service('workflow_approval_reminder.repository'),
        service('workflow_task.repository'),
        service('workflow_instance.repository'),
    ]);
    $services->set(ApprovalReminderPolicy::class)->args([
        service(ClockInterface::class),
        service('event_dispatcher'),
    ]);
    $services->set(ApprovalReminderPresenter::class)->args([
        service(ApprovalReminderStore::class),
        service(ApprovalReminderPolicy::class),
        service(ClockInterface::class),
    ]);
    $services->set(ApprovalReminderService::class)->args([
        service('workflow_approval_reminder.repository'),
        service(ApprovalReminderStore::class),
        service(WorkflowService::class),
        service(WorkflowTaskFinder::class),
        service(WorkflowTransactionRunner::class),
        service(WorkflowRecorder::class),
        service(ClockInterface::class),
        service(ApprovalReminderPolicy::class),
        service('event_dispatcher'),
        service('logger'),
        service(Connection::class),
    ])->public();
    $services->set(ApprovalReminderSubscriber::class)->args([
        service(ApprovalReminderStore::class),
        service(WorkflowTransactionRunner::class),
    ])->tag('kernel.event_subscriber');
    $services->set(ApprovalReminderController::class)
        ->args([service(ApprovalReminderService::class)])
        ->public()
        ->tag('controller.service_arguments');
    $services->set(ApprovalReminderTask::class)->tag('shopwell.scheduled.task');
    $services->set(ApprovalReminderTaskHandler::class)->args([
        service('scheduled_task.repository'),
        service('logger'),
        service(ApprovalReminderService::class),
    ])->tag('messenger.message_handler');

    $services->set(SingleApprovalMode::class);
    $services->set(AllApprovalMode::class);
    $services->set(FirstDecisionApprovalMode::class);
    $services->set(AnyApprovalMode::class);
    $services->set(SequentialApprovalMode::class);
    $services->set(ApprovalModeRegistry::class)->args([tagged_iterator('shopwell.workflow.approval.mode')]);
    $services->set(UserApprovalAssigneeSource::class . '.single')->class(UserApprovalAssigneeSource::class)->args([false]);
    $services->set(UserApprovalAssigneeSource::class . '.multiple')->class(UserApprovalAssigneeSource::class)->args([true]);
    $services->set(DepartmentLeaderApprovalAssigneeSource::class);
    $services->set(InitiatorApprovalAssigneeSource::class);
    $services->set(FormFieldApprovalAssigneeSource::class);
    $services->set(FormFieldApprovalValidationSubscriber::class)->tag('kernel.event_subscriber');
    $services->set(RoleApprovalAssigneeSource::class);
    $services->set(RoleApprovalValidationSubscriber::class)->args([service('acl_role.repository')])->tag('kernel.event_subscriber');
    $services->set(SupervisorApprovalAssigneeSource::class);
    $services->set(PositionApprovalAssigneeSource::class);
    $services->set(PositionApprovalValidationSubscriber::class)->args([service('position.repository')])->tag('kernel.event_subscriber');
    $services->set(ApprovalAssigneeRegistry::class)->args([tagged_iterator('shopwell.workflow.approval.assignee_source')]);
    $services->set(ApprovalConfigurationCompiler::class)->args([
        service(ApprovalAssigneeRegistry::class),
        service(ApprovalModeRegistry::class),
        service(ExtensionDispatcher::class),
    ]);
    $services->set(StartApprovalNodeCompiler::class)->args([service(WorkflowInputSchema::class), service('translator')]);
    $services->set(ApprovalTaskPolicy::class)->args([service('event_dispatcher')]);
    $services->set(TaskApprovalNodeCompiler::class)->args([service(ApprovalConfigurationCompiler::class), service('translator'), service(ApprovalTaskPolicy::class)]);
    $services->set(ScriptApprovalNodeCompiler::class)->args([service('translator')]);
    $services->set(IntegrationApprovalNodeCompiler::class . '.action')->class(IntegrationApprovalNodeCompiler::class)->args(['core:action', 'snaker:action', service('translator')]);
    $services->set(IntegrationApprovalNodeCompiler::class . '.notification')->class(IntegrationApprovalNodeCompiler::class)->args(['core:notification', 'snaker:notification', service('translator')]);
    $services->set(IntegrationApprovalNodeCompiler::class . '.subprocess')->class(IntegrationApprovalNodeCompiler::class)->args(['core:subprocess', 'snaker:subprocess', service('translator')]);
    $services->set(ParallelApprovalNodeCompiler::class . '.parallel')->class(ParallelApprovalNodeCompiler::class)->args(['core:parallel', 'snaker:parallel', service('translator')]);
    $services->set(ParallelApprovalNodeCompiler::class . '.join')->class(ParallelApprovalNodeCompiler::class)->args(['core:join', 'snaker:join', service('translator')]);
    $services->set(ConditionApprovalNodeCompiler::class)->args([service(ApprovalCondition::class), service('translator')]);
    $services->set(CopyApprovalNodeCompiler::class)->args([service('translator')]);
    $services->set(EndApprovalNodeCompiler::class);
    $services->set(ApprovalNodeCompilerRegistry::class)->args([tagged_iterator('shopwell.workflow.approval.node_compiler')]);
    $services->set(ApprovalWorkflowCompiler::class)->args([
        service(ApprovalNodeCompilerRegistry::class),
        service(ExtensionDispatcher::class),
    ]);
    $services->set(ApprovalWorkflowRuntime::class)->args([
        service(ApprovalWorkflowService::class),
        service(WorkflowTransactionRunner::class),
        service(WorkflowEventQueue::class),
        service(Connection::class),
        service(WorkflowService::class),
    ])->public()->tag('shopwell.workflow.runtime');
    $services->set(ApprovalWorkflowSummarySubscriber::class)->args([
        service('workflow_instance.repository'),
        service('workflow_task.repository'),
        service('workflow_copy.repository'),
        service(WorkflowUserLookup::class),
    ])->tag('kernel.event_subscriber');
    $services->set(ApprovalWorkflowPreviewSubscriber::class)->args([
        service('workflow_execution_definition.repository'),
        service(ApprovalRoute::class),
        service(WorkflowInputSchema::class),
        service(WorkflowUserLookup::class),
        service(ApprovalAssigneeResolver::class),
        service(ApprovalModeRegistry::class),
        service('event_dispatcher'),
    ])->tag('kernel.event_subscriber');
    $services->set(WorkflowEntityFields::class)->args([service('user.repository'), service('organization_unit.repository'), service('media.repository')->nullOnInvalid()]);
    $services->set(WorkflowInputSchema::class)->args([service('event_dispatcher'), service(WorkflowEntityFields::class)]);
    $services->set(ApprovalCondition::class)->args([service(WorkflowInputSchema::class)]);
    $services->set(ApprovalRoute::class)->args([service(ConditionEvaluatorInterface::class)]);
    $services->set(ApprovalWorkflowPublisher::class)
        ->args([
            service(WorkflowDocumentCodec::class),
            service(ApprovalWorkflowCompiler::class),
            service('workflow_execution_definition.repository'),
            service('user.repository'),
            service(WorkflowGraphValidator::class),
            service('event_dispatcher'),
        ])
        ->tag('shopwell.workflow.publisher')->tag('kernel.event_subscriber');
    $services->set(WorkflowDefinitionWriteProtection::class)
        ->args([service(Connection::class)])->tag('kernel.event_subscriber');
    $services->set(WorkflowRuntimeWriteProtection::class)->args([service(WorkflowTransactionRunner::class)])->tag('kernel.event_subscriber');

    $services->set(WorkflowExecutionDefinitionEntity::class)->tag('shopwell.entity');
    $services->set(WorkflowInstanceEntity::class)->tag('shopwell.entity');
    $services->set(WorkflowNodeExecutionEntity::class)->tag('shopwell.entity');
    $services->set(WorkflowTaskEntity::class)->tag('shopwell.entity');
    $services->set(WorkflowTaskHistoryEntity::class)->tag('shopwell.entity');
    $services->set(WorkflowLogEntity::class)->tag('shopwell.entity');
    $services->set(WorkflowCopyEntity::class)->tag('shopwell.entity');

    foreach ([
        'workflow_instance' => ['formData', 'graphSnapshot', 'ext'],
        'workflow_node_execution' => ['output'],
        'workflow_task' => ['ext'],
        'workflow_task_history' => ['ext'],
        'workflow_log' => ['payload'],
        'workflow_copy' => ['ext'],
    ] as $entityName => $fields) {
        $services->set(WorkflowPayloadProtection::class . '.' . $entityName, WorkflowPayloadProtection::class)
            ->args([$entityName, $fields])
            ->tag('shopwell.entity.extension');
    }

    $services->set(ConditionExpressionParser::class);
    $services->set(ExprNormalizer::class)->args([service(ConditionExpressionParser::class)]);
    $services->set(ComparisonConditionEvaluator::class)->args([service(ExprNormalizer::class)]);
    $services->set(WorkflowConditionEvaluator::class)->args([service(ComparisonConditionEvaluator::class), service(ApprovalCondition::class)]);
    $services->alias(ConditionEvaluatorInterface::class, WorkflowConditionEvaluator::class);

    $services->set(ShapeRule::class)->args([service(NodeExecutorRegistry::class)]);
    $services->set(SingleStartRule::class);
    $services->set(SingleEndRule::class);
    $services->set(CycleRule::class);
    $services->set(ReachabilityRule::class);
    $services->set(NodePropertiesRule::class)->args([service(AssigneeResolverRegistry::class), service(ApprovalModeRegistry::class)]);
    $services->set(EdgeConditionRule::class)->args([service(ExprNormalizer::class), service(ApprovalCondition::class)]);
    $services->set(BranchMergeRule::class);

    $services->set(WorkflowGraphValidator::class)
        ->args([tagged_iterator('shopwell.workflow.execution.graph_rule')]);

    $services->set(StartExecutor::class);
    $services->set(ConditionExecutor::class);
    $services->set(EndExecutor::class);
    $services->set(ScriptExecutor::class);
    $services->set(AutomationActionExecutor::class . '.action')->class(AutomationActionExecutor::class)->args(['snaker:action', service('event_dispatcher')]);
    $services->set(AutomationActionExecutor::class . '.notification')->class(AutomationActionExecutor::class)->args(['snaker:notification', service('event_dispatcher')]);
    $services->set(AutomationActionExecutor::class . '.subprocess')->class(AutomationActionExecutor::class)->args(['snaker:subprocess', service('event_dispatcher')]);
    $services->set(JoinExecutor::class)->args([service('workflow_node_execution.repository')]);
    $services->set(ParallelExecutor::class);
    $services->set(WorkflowUnusedMediaSubscriber::class)->args([service(Connection::class)])->tag('kernel.event_subscriber');

    $services->set(AutomaticApprovalResolver::class)->args([service('workflow_task_history.repository'), service('workflow_node_execution.repository')]);
    $services->set(TaskExecutor::class)->args([
        service(ApprovalAssigneeResolver::class),
        service('workflow_task.repository'),
        service(WorkflowEventQueue::class),
        service(WorkflowTaskStateHandler::class),
        service(ApprovalModeRegistry::class),
        service(AutomaticApprovalResolver::class),
        service(WorkflowTaskFinder::class),
        service(CounterSignCalculator::class),
        service(WorkflowRecorder::class),
    ]);

    $services->set(CopyExecutor::class)->args([
        service('workflow_copy.repository'),
    ]);

    $services->set(NodeExecutorRegistry::class)
        ->args([tagged_iterator('shopwell.workflow.execution.node_executor')]);

    $services->set(OrganizationAssigneeLookup::class)->args([service('user_organization_unit.repository'), service('organization_unit.repository')]);
    $services->set(SupervisorAssigneeResolver::class)->args([service(OrganizationAssigneeLookup::class), service(OrganizationAssigneeContext::class)]);
    $services->set(ApprovalAssigneeResolver::class)->args([service(AssigneeResolverRegistry::class)]);
    $services->set(OrganizationAssigneeContext::class)->args([service(OrganizationAssigneeLookup::class)])->tag('kernel.event_subscriber');
    $services->set(DepartmentLeaderAssigneeResolver::class)->args([service(OrganizationAssigneeLookup::class), service(OrganizationAssigneeContext::class)]);
    $services->set(PositionAssigneeResolver::class)->args([service('user_position.repository'), service(OrganizationAssigneeLookup::class), service(OrganizationAssigneeContext::class)]);

    $services->set(UserAssigneeResolver::class)->args([service('user.repository')]);
    $services->set(RoleAssigneeResolver::class)->args([service('user.repository')]);
    $services->set(FormFieldAssigneeResolver::class)->args([service(UserAssigneeResolver::class)]);
    $services->set(InitiatorAssigneeResolver::class)->args([service(UserAssigneeResolver::class)]);

    $services->set(AssigneeResolverRegistry::class)
        ->args([tagged_iterator('shopwell.workflow.execution.assignee_resolver')]);

    $services->set(WorkflowTaskStateHandler::class)->args([
        service(StateMachineRegistry::class),
        service('workflow_task.repository'),
        service(ClockInterface::class),
    ]);

    $services->set(WorkflowInstanceStateHandler::class)->args([
        service(StateMachineRegistry::class),
        service('workflow_instance.repository'),
        service(ClockInterface::class),
    ]);

    // The engine and lifecycle service share pending events for the current transaction.
    $services->set(WorkflowEventQueue::class)->args([
        service('event_dispatcher'),
    ]);

    $services->set(WorkflowTransactionRunner::class)->args([service(Connection::class), service(WorkflowEventQueue::class)]);
    $services->set(WorkflowCancellationStore::class)->args([service(Connection::class), service(ClockInterface::class)]);

    $services->set(WorkflowRecorder::class)->args([
        service('workflow_task_history.repository'),
        service('workflow_log.repository'),
        service(ClockInterface::class),
    ]);

    $services->set(WorkflowTaskFinder::class)->args([
        service('workflow_task.repository'),
        service('workflow_task_history.repository'),
        service('workflow_log.repository'),
    ]);

    $services->set(CounterSignCalculator::class)->args([service(ApprovalModeRegistry::class)]);
    $services->set(WorkflowNumberGenerator::class);

    $services->set(WorkflowRoute::class)->args([service(ConditionEvaluatorInterface::class)]);
    $services->set(WorkflowNodeExecutionStore::class)->args([service('workflow_node_execution.repository'), service(ClockInterface::class)])->public();
    $services->set(ApprovalTaskCompletion::class)->args([
        service(CounterSignCalculator::class),
        service(WorkflowTaskStateHandler::class),
        service(WorkflowTaskFinder::class),
        service(WorkflowRecorder::class),
        service(WorkflowEventQueue::class),
        service(WorkflowEngine::class),
    ]);
    $services->set(ApprovalCompletionSubscriber::class)->args([
        service(WorkflowTaskFinder::class),
        service(WorkflowTaskStateHandler::class),
        service(WorkflowInstanceStateHandler::class),
    ])->tag('kernel.event_subscriber');
    $services->set(WorkflowNodeCompilerRegistry::class)->args([tagged_iterator('shopwell.workflow.node_compiler')]);
    $services->set(StartWorkflowNodeCompiler::class)->args([service(WorkflowInputSchema::class)]);
    $services->set(EndWorkflowNodeCompiler::class);
    $services->set(WorkflowCompiler::class)->args([
        service(WorkflowNodeCompilerRegistry::class),
        service(WorkflowGraphDiagnostics::class),
        service(WorkflowGraphValidator::class),
    ])->public();
    $services->set(WorkflowExecutionPublisher::class)->args([
        service(WorkflowDocumentCodec::class),
        service(WorkflowCompiler::class),
        service('workflow_execution_definition.repository'),
    ])->tag('shopwell.workflow.publisher')->tag('kernel.event_subscriber');

    $services->set(WorkflowEngine::class)->args([
        service(NodeExecutorRegistry::class),
        service(WorkflowRoute::class),
        service(WorkflowNodeExecutionStore::class),
        service(WorkflowInstanceStateHandler::class),
        service(WorkflowEventQueue::class),
        service('event_dispatcher'),
        service('workflow_instance.repository'),
    ])->public();

    $services->set(WorkflowService::class)->args([
        service('workflow_execution_definition.repository'),
        service('workflow_instance.repository'),
        service(WorkflowEngine::class),
        service(WorkflowGraphValidator::class),
        service(WorkflowTransactionRunner::class),
        service(WorkflowEventQueue::class),
        service(WorkflowRecorder::class),
        service(WorkflowNumberGenerator::class),
        service(WorkflowInstanceStateHandler::class),
        service(WorkflowInputSchema::class),
        service(ClockInterface::class),
        service('translator'),
        service('event_dispatcher'),
    ])->public();
    $services->set(ApprovalTaskParticipation::class)->args([
        service('workflow_task.repository'),
        service(WorkflowTaskFinder::class),
        service(WorkflowTaskStateHandler::class),
        service(OrganizationAssigneeLookup::class),
        service(WorkflowRecorder::class),
        service(WorkflowEventQueue::class),
        service(ExtensionDispatcher::class),
    ]);
    $services->set(ApprovalResubmission::class)->args([
        service(WorkflowService::class),
        service('workflow_instance.repository'),
        service(WorkflowEngine::class),
        service(WorkflowTaskFinder::class),
        service(WorkflowTaskStateHandler::class),
        service(WorkflowInstanceStateHandler::class),
        service(WorkflowRecorder::class),
        service(ApprovalFormService::class),
        service(WorkflowTransactionRunner::class),
        service(WorkflowEventQueue::class),
        service('event_dispatcher'),
    ])->public();
    $services->set(ApprovalWorkflowService::class)->args([
        service(WorkflowService::class),
        service('workflow_task.repository'),
        service('workflow_copy.repository'),
        service(WorkflowTransactionRunner::class),
        service(WorkflowEventQueue::class),
        service(WorkflowTaskFinder::class),
        service(WorkflowTaskStateHandler::class),
        service(WorkflowInstanceStateHandler::class),
        service(WorkflowCancellationStore::class),
        service(ApprovalTaskCompletion::class),
        service(ApprovalTaskParticipation::class),
        service(ApprovalResubmission::class),
        service(ApprovalTaskPolicy::class),
        service(ExtensionDispatcher::class),
        service(WorkflowRecorder::class),
        service(ApprovalFormService::class),
    ])->public();

    $services->set(WorkflowUserLookup::class)->args([service('user.repository')]);
    $services->set(WorkflowExecutionDefinitionPresenter::class);
    $services->set(WorkflowTaskPresenter::class)->args([
        service(WorkflowUserLookup::class),
        service('workflow_instance.repository'),
        service(ApprovalTaskPolicy::class),
        service(ApprovalReminderPresenter::class),
    ]);

    $services->set(WorkflowInstancePresenter::class)->args([
        service(WorkflowUserLookup::class),
        service('workflow_execution_definition.repository'),
        service('workflow_node_execution.repository'),
        service('event_dispatcher'),
        service('workflow_log.repository'),
    ]);
    $services->set(ApprovalInstancePresentationSubscriber::class)->args([
        service(WorkflowTaskFinder::class),
        service(WorkflowTaskPresenter::class),
        service(WorkflowUserLookup::class),
        service('workflow_copy.repository'),
        service(ApprovalRoute::class),
    ])->tag('kernel.event_subscriber');

    $services->set(WorkflowExecutionDefinitionController::class)
        ->args([
            service(WorkflowService::class),
            service(WorkflowExecutionDefinitionPresenter::class),
            service('workflow_execution_definition.repository'),
        ])
        ->public()
        ->call('setContainer', [service('service_container')]);

    $services->set(WorkflowInstanceController::class)
        ->args([
            service(ApprovalWorkflowService::class),
            service(WorkflowInstancePresenter::class),
            service('workflow_instance.repository'),
            service(WorkflowService::class),
            service(ApprovalResubmission::class),
        ])
        ->public()
        ->call('setContainer', [service('service_container')]);

    $services->set(CopyWorkflowSubscriber::class)->args([service('user.repository')])->tag('kernel.event_subscriber');
    $services->set(WorkflowCopyService::class)->args([
        service('workflow_copy.repository'),
        service(WorkflowService::class),
        service(WorkflowTransactionRunner::class),
        service(ClockInterface::class),
    ])->public();
    $services->set(WorkflowCopyPresenter::class)->args([service('workflow_instance.repository'), service(WorkflowUserLookup::class)]);
    $services->set(WorkflowCopyController::class)->args([service(WorkflowCopyService::class), service(WorkflowCopyPresenter::class)])
        ->public()->tag('controller.service_arguments');

    $services->set(WorkflowTaskController::class)
        ->args([
            service(ApprovalWorkflowService::class),
            service(WorkflowTaskPresenter::class),
            service(WorkflowService::class),
        ])
        ->public()
        ->call('setContainer', [service('service_container')]);
};
