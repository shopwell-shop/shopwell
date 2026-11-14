<?php declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Workflow\Definition\Codec\WorkflowDocumentCodec;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowGraphDiagnostics;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowExecutionDefinitionController;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowExecutionDefinitionPresenter;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowInstanceController;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowInstancePresenter;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowTaskController;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowTaskPresenter;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowUserLookup;
use Shopwell\Core\Framework\Workflow\Execution\ApprovalWorkflowPreviewSubscriber;
use Shopwell\Core\Framework\Workflow\Execution\ApprovalWorkflowRuntime;
use Shopwell\Core\Framework\Workflow\Execution\ApprovalWorkflowSummarySubscriber;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverInterface;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverRegistry;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\InitiatorAssigneeResolver;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\RoleAssigneeResolver;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\UserAssigneeResolver;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ComparisonConditionEvaluator;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ConditionEvaluatorInterface;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ExprNormalizer;
use Shopwell\Core\Framework\Workflow\Execution\Condition\WorkflowConditionEvaluator;
use Shopwell\Core\Framework\Workflow\Execution\Copy\WorkflowCopyEntity;
use Shopwell\Core\Framework\Workflow\Execution\CounterSignCalculator;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionEntity;
use Shopwell\Core\Framework\Workflow\Execution\Executor\ConditionExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Executor\CopyExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Executor\EndExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Executor\NodeExecutorInterface;
use Shopwell\Core\Framework\Workflow\Execution\Executor\NodeExecutorRegistry;
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
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalInstancePresentationSubscriber;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalTaskCompletion;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalCondition;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalRoute;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowCompiler;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowPublisher;
use Shopwell\Core\Framework\Workflow\Publication\EndWorkflowNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\StartWorkflowNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowCompiler;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowExecutionPublisher;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowNodeCompilerRegistry;
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

    $services->set(ApprovalWorkflowCompiler::class)->args([service(WorkflowInputSchema::class), service(ApprovalCondition::class)]);
    $services->set(ApprovalWorkflowRuntime::class)->args([service(ApprovalWorkflowService::class), service(WorkflowTransactionRunner::class), service(WorkflowEventQueue::class), service(Connection::class)])->public()->tag('shopwell.workflow.runtime');
    $services->set(ApprovalWorkflowSummarySubscriber::class)->args([service('workflow_instance.repository'), service('workflow_task.repository'), service('workflow_copy.repository'), service(WorkflowUserLookup::class)])->tag('kernel.event_subscriber');
    $services->set(ApprovalWorkflowPreviewSubscriber::class)->args([service('workflow_execution_definition.repository'), service(ApprovalRoute::class), service(WorkflowInputSchema::class), service(WorkflowUserLookup::class)])->tag('kernel.event_subscriber');
    $services->set(WorkflowInputSchema::class)->args([service('event_dispatcher')]);
    $services->set(ApprovalCondition::class)->args([service(WorkflowInputSchema::class)]);
    $services->set(ApprovalRoute::class)->args([service(ConditionEvaluatorInterface::class)]);
    $services->set(ApprovalWorkflowPublisher::class)
        ->args([
            service(WorkflowDocumentCodec::class),
            service(ApprovalWorkflowCompiler::class),
            service('workflow_execution_definition.repository'),
            service('user.repository'),
        ])
        ->tag('shopwell.workflow.publisher')->tag('kernel.event_subscriber');
    $services->set(WorkflowDefinitionWriteProtection::class)
        ->args([service(Connection::class)])->tag('kernel.event_subscriber');
    $services->set(WorkflowRuntimeWriteProtection::class)->args([service(WorkflowTransactionRunner::class)])->tag('kernel.event_subscriber');

    // ------------------------------------------------------------ 实体
    // 属性式实体必须打这个标签，否则 DAL 不认识这张表（`dal:validate` 会报 missing entity）
    $services->set(WorkflowExecutionDefinitionEntity::class)->tag('shopwell.entity');
    $services->set(WorkflowInstanceEntity::class)->tag('shopwell.entity');
    $services->set(WorkflowNodeExecutionEntity::class)->tag('shopwell.entity');
    $services->set(WorkflowTaskEntity::class)->tag('shopwell.entity');
    $services->set(WorkflowTaskHistoryEntity::class)->tag('shopwell.entity');
    $services->set(WorkflowLogEntity::class)->tag('shopwell.entity');
    $services->set(WorkflowCopyEntity::class)->tag('shopwell.entity');

    // ------------------------------------------------------------ 条件表达式
    $services->set(ExprNormalizer::class);
    $services->set(ComparisonConditionEvaluator::class)->args([service(ExprNormalizer::class)]);
    $services->set(WorkflowConditionEvaluator::class)->args([service(ComparisonConditionEvaluator::class), service(ApprovalCondition::class)]);
    $services->alias(ConditionEvaluatorInterface::class, WorkflowConditionEvaluator::class);

    // ------------------------------------------------------------ 校验规则链
    // 规则的注册顺序 = 报告顺序，前端 graph-validator.ts 必须保持一致
    $services->set(ShapeRule::class)->args([service(NodeExecutorRegistry::class)]);
    $services->set(SingleStartRule::class);
    $services->set(SingleEndRule::class);
    $services->set(CycleRule::class);
    $services->set(ReachabilityRule::class);
    $services->set(NodePropertiesRule::class);
    $services->set(EdgeConditionRule::class)->args([service(ExprNormalizer::class), service(ApprovalCondition::class)]);
    $services->set(BranchMergeRule::class);

    $services->set(WorkflowGraphValidator::class)
        ->args([tagged_iterator('shopwell.workflow.execution.graph_rule')]);

    // ------------------------------------------------------------ 执行器
    $services->set(StartExecutor::class);
    $services->set(ConditionExecutor::class);
    $services->set(EndExecutor::class);

    $services->set(TaskExecutor::class)->args([
        service(AssigneeResolverRegistry::class),
        service('workflow_task.repository'),
        service(WorkflowEventQueue::class),
        service(WorkflowTaskStateHandler::class),
    ]);

    $services->set(CopyExecutor::class)->args([
        service('workflow_copy.repository'),
    ]);

    $services->set(NodeExecutorRegistry::class)
        ->args([tagged_iterator('shopwell.workflow.execution.node_executor')]);

    // ------------------------------------------------------------ 审批人解析
    $services->set(UserAssigneeResolver::class)->args([service('user.repository')]);
    $services->set(RoleAssigneeResolver::class)->args([service('user.repository')]);
    $services->set(InitiatorAssigneeResolver::class);

    $services->set(AssigneeResolverRegistry::class)
        ->args([tagged_iterator('shopwell.workflow.execution.assignee_resolver')]);

    // ------------------------------------------------------------ 引擎与门面
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

    // 事件队列是**请求级**的，必须共享单例：引擎 push、门面 flush，各拿一个新实例就永远是空队列
    $services->set(WorkflowEventQueue::class)->args([
        service('event_dispatcher'),
    ]);

    // 6.7 里 DBAL 连接的服务 id 就是类名。写 `doctrine.dbal.default_connection`
    // 是 Symfony 全栈的惯例，在平台容器里并不存在，容器编译期就会抛 not found
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

    $services->set(CounterSignCalculator::class);
    $services->set(WorkflowNumberGenerator::class);

    $services->set(WorkflowRoute::class)->args([service(ConditionEvaluatorInterface::class)]);
    $services->set(WorkflowNodeExecutionStore::class)->args([service('workflow_node_execution.repository'), service(ClockInterface::class)])->public();
    $services->set(ApprovalTaskCompletion::class)->args([
        service(CounterSignCalculator::class), service(WorkflowTaskStateHandler::class), service(WorkflowTaskFinder::class),
        service(WorkflowRecorder::class), service(WorkflowEventQueue::class), service(WorkflowEngine::class),
    ]);
    $services->set(ApprovalCompletionSubscriber::class)->args([service(WorkflowTaskFinder::class), service(WorkflowTaskStateHandler::class), service(WorkflowInstanceStateHandler::class)])->tag('kernel.event_subscriber');
    $services->set(WorkflowNodeCompilerRegistry::class)->args([tagged_iterator('shopwell.workflow.node_compiler')]);
    $services->set(StartWorkflowNodeCompiler::class)->args([service(WorkflowInputSchema::class)]);
    $services->set(EndWorkflowNodeCompiler::class);
    $services->set(WorkflowCompiler::class)->args([service(WorkflowNodeCompilerRegistry::class), service(WorkflowGraphDiagnostics::class), service(WorkflowGraphValidator::class)])->public();
    $services->set(WorkflowExecutionPublisher::class)->args([service(WorkflowDocumentCodec::class), service(WorkflowCompiler::class), service('workflow_execution_definition.repository')])->tag('shopwell.workflow.publisher')->tag('kernel.event_subscriber');

    $services->set(WorkflowEngine::class)->args([
        service(NodeExecutorRegistry::class), service(WorkflowRoute::class), service(WorkflowNodeExecutionStore::class),
        service(WorkflowInstanceStateHandler::class), service(WorkflowEventQueue::class), service('event_dispatcher'),
        service('workflow_instance.repository'),
    ])->public();

    $services->set(WorkflowService::class)->args([
        service('workflow_execution_definition.repository'), service('workflow_instance.repository'), service(WorkflowEngine::class),
        service(WorkflowGraphValidator::class), service(WorkflowTransactionRunner::class), service(WorkflowEventQueue::class),
        service(WorkflowRecorder::class), service(WorkflowNumberGenerator::class), service(WorkflowInstanceStateHandler::class),
        service(WorkflowInputSchema::class), service(ClockInterface::class),
    ])->public();
    $services->set(ApprovalWorkflowService::class)->args([
        service(WorkflowService::class), service('workflow_task.repository'), service('workflow_copy.repository'),
        service(WorkflowTransactionRunner::class), service(WorkflowEventQueue::class),
        service(WorkflowTaskFinder::class), service(WorkflowTaskStateHandler::class), service(WorkflowInstanceStateHandler::class),
        service(WorkflowCancellationStore::class), service(ApprovalTaskCompletion::class),
    ])->public();

    // ------------------------------------------------------------ 输出层
    $services->set(WorkflowUserLookup::class)->args([service('user.repository')]);
    $services->set(WorkflowExecutionDefinitionPresenter::class);
    $services->set(WorkflowTaskPresenter::class)->args([service(WorkflowUserLookup::class), service('workflow_instance.repository')]);

    $services->set(WorkflowInstancePresenter::class)->args([
        service(WorkflowUserLookup::class), service('workflow_execution_definition.repository'),
        service('workflow_node_execution.repository'), service('event_dispatcher'), service('workflow_log.repository'),
    ]);
    $services->set(ApprovalInstancePresentationSubscriber::class)->args([
        service(WorkflowTaskFinder::class), service(WorkflowTaskPresenter::class), service(WorkflowUserLookup::class),
        service('workflow_copy.repository'), service(ApprovalRoute::class),
    ])->tag('kernel.event_subscriber');

    // ------------------------------------------------------------ Api 控制器
    // 三个控制器都要 `public()` + `setContainer()`：路由解析器从容器按 id 取控制器，
    // 非 public 的服务在编译后会被内联掉，表现为 404 或 "controller not found"
    $services->set(WorkflowExecutionDefinitionController::class)
        ->args([
            service(ApprovalWorkflowService::class),
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
        ])
        ->public()
        ->call('setContainer', [service('service_container')]);

    $services->set(WorkflowTaskController::class)
        ->args([
            service(ApprovalWorkflowService::class),
            service(WorkflowTaskPresenter::class),
        ])
        ->public()
        ->call('setContainer', [service('service_container')]);
};
