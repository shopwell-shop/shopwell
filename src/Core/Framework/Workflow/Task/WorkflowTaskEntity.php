<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Task;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\State;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * @internal
 *
 * 一条待办 = 一个审批人的一次表态机会。
 *
 * 任务不是"节点的属性"，而是一等公民：会签 = 同一节点 N 条任务；
 * 顺序会签 = 同一节点按 `sequence` 逐条创建。这样"谁在什么时候同意了哪一步"
 * 天然可查，不需要回头解析日志，也不会因为模板改版而丢失历史。
 *
 * `round` 与实例上的同名。当前同步为 1；退回后同节点进入第 2 轮，
 * 旧任务保持第 1 轮不动，会签分母按 `(instance, node, round)` 分组才不会串轮。
 *
 * **ext 里有个约定键**：`ext.assignees` 存顺序会签的完整审批人列表快照
 * （`list<string>`，按审批顺序）。顺序会签每次只建一条任务，轮到下一个人时
 * 从这份快照里取——如果每次都重新解析审批人，期间组织架构一变就会漏人或多出人。
 * 解析一次、快照一次、后续只读。
 */
#[Package('framework')]
#[Entity('workflow_task', since: '6.7.15.0', collectionClass: WorkflowTaskCollection::class)]
class WorkflowTaskEntity extends EntityStruct
{
    public const ENTITY_NAME = 'workflow_task';

    /**
     * ext 里的审批人快照键，见类注释
     */
    public const EXT_ASSIGNEES = 'assignees';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: true)]
    public string $id;

    #[State(machine: WorkflowTaskStates::STATE_MACHINE, api: true)]
    public string $stateId;

    #[ManyToOne(entity: 'state_machine_state', api: true, column: 'state_id')]
    public ?StateMachineStateEntity $stateMachineState = null;

    #[Required]
    #[ForeignKey(entity: 'workflow_instance', api: true)]
    public string $instanceId;

    /**
     * 模板里的节点 id，与图里的 node.id 一致
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: true, maxLength: 64)]
    public string $nodeId;

    /**
     * 节点名快照：模板改名后历史待办仍显示当时的名字
     */
    #[Field(type: FieldType::STRING, api: true, maxLength: 255)]
    public string $nodeName;

    #[Required]
    #[Field(type: FieldType::STRING, api: true, maxLength: 32)]
    public string $countersignType;

    #[Required]
    #[Field(type: FieldType::INT, api: true)]
    public int $round = 1;

    /**
     * 顺序会签的序号（从 1 开始）；其它类型恒为 1
     */
    #[Field(type: FieldType::INT, api: true)]
    public int $sequence = 1;

    #[Required]
    #[ForeignKey(entity: 'user', api: true)]
    public string $assigneeId;

    /**
     * 审批人来源类型，用于详情页显示"部门负责人"这类说明，也便于排查解析问题
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: true, maxLength: 32)]
    public string $assigneeType;

    #[Field(type: FieldType::DATETIME, api: true)]
    public ?\DateTimeInterface $completedAt = null;

    /**
     * 预留：加签来源任务 id、转办人、代理人、超时策略。
     * 约定键 `assignees` 见类注释。
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: false)]
    public ?array $ext = null;

    public function state(): string
    {
        return $this->stateMachineState?->getTechnicalName()
            ?? throw WorkflowExecutionException::stateNotLoaded();
    }

    public function isTodo(): bool
    {
        return $this->state() === WorkflowTaskStates::TODO;
    }

    /**
     * 顺序会签的审批人快照。
     *
     * @return list<string>
     */
    public function assigneeSnapshot(): array
    {
        $snapshot = $this->ext[self::EXT_ASSIGNEES] ?? [];

        if (!\is_array($snapshot)) {
            return [];
        }

        return array_values(array_filter($snapshot, static fn (mixed $id): bool => \is_string($id) && $id !== ''));
    }
}
