<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Transfer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Definition\Type\WorkflowAssigneeSources;
use Shopwell\Core\Framework\Workflow\Definition\Type\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\Draft\WorkflowDraftStore;
use Shopwell\Core\Framework\Workflow\Execution\Copy\WorkflowCopyCollection;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowCountersignTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalResubmission;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalTaskActions;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderEntity;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderService;
use Shopwell\Core\Framework\Workflow\Publication\Entity\WorkflowReleaseEntity;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowReleaseStore;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimePreviewEvent;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimeRegistry;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskCollection;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStates;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Shopwell\Core\System\Organization\OrganizationDefaults;
use Shopwell\Core\System\Payment\Api\Struct\PaymentTransferDraft;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Exception\PaymentTransferException;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalException;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferResultBooker;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Tests\Integration\Core\System\Payment\Transfer\_fixtures\PaymentTransferApprovalFixture;

/**
 * Exercises the same template asserted by the Administration template spec.
 * No payment gateway is called: approval authorizes a later, explicit execution.
 *
 * @internal
 */
#[Package('framework')]
class PaymentTransferTemplateWorkflowTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private Context $context;

    private PaymentTransferApprovalService $approvals;

    private PaymentTransferEntity $paymentFixture;

    /**
     * @var list<TestUser>
     */
    private array $users = [];

    private string $positionId;

    private string $draftId;

    private mixed $previousRelease;

    private WorkflowReleaseEntity $release;

    /**
     * @var list<string>
     */
    private array $currencyIds = [];

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->connection = $container->get(Connection::class);
        $this->approvals = $container->get(PaymentTransferApprovalService::class);
        $this->previousRelease = $container->get(SystemConfigService::class)->get(PaymentTransferApprovalService::CONFIG_KEY);
        $this->positionId = Uuid::randomHex();
        $this->draftId = Uuid::randomHex();

        for ($index = 0; $index < 7; ++$index) {
            $user = TestUser::createNewTestUser($this->connection, ['workflow_instance:access', 'workflow_task:decide']);
            $this->users[] = $user;
            $container->get('user.repository')->update([[
                'id' => $user->getUserId(),
                'organizationUnitMappings' => [[
                    'id' => Uuid::randomHex(), 'organizationUnitId' => OrganizationDefaults::UNIT, 'isPrimary' => true,
                ]],
                'positionMappings' => [[
                    'id' => Uuid::randomHex(), 'positionId' => OrganizationDefaults::POSITION,
                    'organizationUnitId' => OrganizationDefaults::UNIT, 'isPrimary' => true,
                ]],
            ]], Context::createDefaultContext());
        }
        $this->context = $this->actor($this->users[0]->getUserId());
        $container->get('position.repository')->create([[
            'id' => $this->positionId, 'name' => 'Transfer reviewers', 'active' => true,
        ]], Context::createDefaultContext());
        foreach ([$this->users[1], $this->users[2]] as $reviewer) {
            $container->get('user_position.repository')->create([[
                'id' => Uuid::randomHex(), 'userId' => $reviewer->getUserId(), 'positionId' => $this->positionId,
                'organizationUnitId' => OrganizationDefaults::UNIT, 'isPrimary' => false,
            ]], Context::createDefaultContext());
        }
        $this->ensureCurrency('CNY');
        $this->ensureCurrency('USD');
        $this->paymentFixture = PaymentTransferApprovalFixture::create($container, credentials: $container->get(PaymentCredentialCipher::class)->encrypt(['app_id' => 'template-test-' . $this->draftId]));
        $container->get('payment_app.repository')->update([[
            'id' => $this->paymentFixture->paymentAppId,
            'accounts' => [['id' => Uuid::randomHex(), 'accountId' => $this->paymentFixture->accountId, 'enabled' => true, 'position' => 0]],
            'channels' => [['id' => Uuid::randomHex(), 'channelTechnicalName' => 'alipay_h5', 'enabled' => true]],
        ]], Context::createDefaultContext());

        $document = json_decode((string) file_get_contents(__DIR__ . '/_fixtures/transfer-workflow-template.json'), true, flags: \JSON_THROW_ON_ERROR);
        foreach ($document['nodes'] as &$node) {
            if ($node['id'] === 'department') {
                $node['config']['assignee']['config']['positionIds'] = [$this->positionId];
            } elseif ($node['id'] === 'finance') {
                $node['config']['assignee']['config']['userId'] = $this->users[3]->getUserId();
            } elseif ($node['type'] === 'core:copy') {
                $node['config']['userIds'] = [$this->users[4]->getUserId()];
            }
        }
        unset($node);
        $container->get(WorkflowDraftStore::class)->create($this->draftId, 'Transfer template test', json_encode($document, \JSON_THROW_ON_ERROR), $this->context);
        $release = $container->get(WorkflowReleaseStore::class)->publish($this->draftId, 1, 'core:approval', $this->context);
        $this->release = $release;
        $this->approvals->configure($release->getId(), $this->context);
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $container->get(SystemConfigService::class)->set(PaymentTransferApprovalService::CONFIG_KEY, $this->previousRelease);
        if (isset($this->paymentFixture)) {
            $appId = Uuid::fromHexToBytes($this->paymentFixture->paymentAppId);
            $instances = $this->connection->fetchFirstColumn('SELECT i.id FROM workflow_instance i INNER JOIN workflow_release r ON r.id = i.definition_id WHERE r.draft_id = :id', ['id' => Uuid::fromHexToBytes($this->draftId)]);
            foreach ($instances as $id) {
                $this->connection->executeStatement('DELETE h FROM state_machine_history h INNER JOIN workflow_task t ON h.referenced_id = t.id WHERE t.instance_id = :id', ['id' => $id]);
                $this->connection->delete('state_machine_history', ['entity_name' => 'workflow_instance', 'referenced_id' => $id]);
                $this->connection->delete('workflow_instance', ['id' => $id]);
            }
            $this->connection->executeStatement('DELETE h FROM state_machine_history h INNER JOIN payment_transfer t ON h.referenced_id = t.id WHERE t.payment_app_id = :id', ['id' => $appId]);
            $this->connection->delete('payment_transfer', ['payment_app_id' => $appId]);
            $this->connection->delete('payment_transfer_recipient', ['payment_app_id' => $appId]);
            $this->connection->delete('payment_app', ['id' => $appId]);
            $this->connection->delete('payment_account', ['id' => Uuid::fromHexToBytes($this->paymentFixture->accountId)]);
        }
        foreach ($this->connection->fetchFirstColumn('SELECT id FROM workflow_release WHERE draft_id = :id', ['id' => Uuid::fromHexToBytes($this->draftId)]) as $id) {
            $this->connection->delete('workflow_execution_definition', ['id' => $id]);
            $this->connection->delete('workflow_release', ['id' => $id]);
        }
        $this->connection->delete('workflow_draft', ['id' => Uuid::fromHexToBytes($this->draftId)]);
        foreach ($this->users as $user) {
            $id = Uuid::fromHexToBytes($user->getUserId());
            $avatar = $this->connection->fetchOne('SELECT avatar_id FROM user WHERE id = :id', ['id' => $id]);
            $this->connection->delete('user', ['id' => $id]);
            $this->connection->delete('media', ['id' => $avatar]);
            if ($user->getAclRoleId() !== null) {
                $this->connection->delete('acl_role', ['id' => Uuid::fromHexToBytes($user->getAclRoleId())]);
            }
        }
        $this->connection->delete('position', ['id' => Uuid::fromHexToBytes($this->positionId)]);
        foreach ($this->currencyIds as $id) {
            $this->connection->delete('currency', ['id' => Uuid::fromHexToBytes($id)]);
        }
        parent::tearDown();
    }

    #[DataProvider('transferRoutes')]
    public function testPreviewAndExecutedRouteAgree(int $amount, string $currency, bool $financeRequired): void
    {
        $draft = $this->draft($amount, $currency);
        $before = $this->rowCounts();
        $preview = $this->approvals->preview($draft, $this->context);
        static::assertTrue($preview['available'], json_encode($preview, \JSON_THROW_ON_ERROR));
        static::assertSame($before, $this->rowCounts(), 'Preview must not persist business or runtime records.');
        static::assertSame($financeRequired ? ['department', 'review-policy', 'finance', 'copy-reviewed'] : ['department', 'review-policy', 'copy-standard'], array_column($preview['steps'], 'nodeId'));
        $expectedReviewers = [$this->users[1]->getUserId(), $this->users[2]->getUserId()];
        sort($expectedReviewers);
        static::assertSame($expectedReviewers, array_column($preview['steps'][0]['assignees'], 'id'));
        static::assertSame('countersign', $preview['steps'][0]['rule']);

        $transfer = $this->approvals->book($draft, $this->context);
        $instanceId = $this->instanceId($transfer);
        $tasks = $this->tasks($instanceId);
        static::assertSame($expectedReviewers, array_values(array_map(static fn ($task): string => $task->assigneeId, $tasks->getElements())));
        static::assertCount(0, $this->copies($instanceId));
        foreach ($tasks as $task) {
            $this->decide($task->id, $task->assigneeId);
        }
        if ($financeRequired) {
            static::assertSame('pending', $this->approvals->status($transfer->id, $this->context)['status']);
            static::assertCount(0, $this->copies($instanceId));
            $finance = $this->tasks($instanceId)->filter(static fn ($task): bool => $task->nodeId === 'finance')->first();
            static::assertNotNull($finance);
            static::assertSame($preview['steps'][2]['assignees'][0]['id'], $finance->assigneeId);
            $this->decide($finance->id, $finance->assigneeId);
        }
        static::assertSame('approved', $this->approvals->status($transfer->id, $this->context)['status']);
        static::assertSame('approved', $this->approvals->summaries([$transfer->id], $this->context)[$transfer->id]['status']);
        $copies = $this->copies($instanceId);
        static::assertCount(1, $copies);
        $receipt = $copies->first();
        static::assertNotNull($receipt);
        static::assertSame($this->users[4]->getUserId(), $receipt->userId);
        static::assertSame($financeRequired ? 'copy-reviewed' : 'copy-standard', $receipt->nodeId);
        static::assertSame(0, $this->tasks($instanceId)->countByState('todo'));
        static::assertCount($financeRequired ? 3 : 2, static::getContainer()->get(WorkflowTaskFinder::class)->historyOf($instanceId, $this->context));
        $this->approvals->assertApproved($transfer);
        $persisted = static::getContainer()->get(PaymentTransferService::class)->getById($transfer->id);
        static::assertSame('created', $persisted->statusMachineState?->getTechnicalName());
        static::assertSame(0, $persisted->submitAttempts);
    }

    public static function transferRoutes(): \Generator
    {
        yield 'CNY below threshold' => [499999, 'CNY', false];
        yield 'CNY exact threshold' => [500000, 'CNY', false];
        yield 'CNY one cent above threshold' => [500001, 'CNY', true];
    }

    #[DataProvider('foreignAmounts')]
    public function testForeignCurrencyTemplateRouteUsesFinanceWithoutAnExchangeRate(string $amount): void
    {
        // The approval template supports foreign currencies independently of the installed payment products.
        $data = ['amount' => $amount, 'currency' => 'USD', 'recipient' => 'Masked recipient', 'paymentAccount' => 'Test account', 'reason' => 'Foreign transfer'];
        $preview = new WorkflowRuntimePreviewEvent($this->release, $data, $this->context);
        static::getContainer()->get('event_dispatcher')->dispatch($preview);
        $steps = $preview->getSteps();
        static::assertNotNull($steps);
        static::assertSame(['department', 'review-policy', 'finance', 'copy-reviewed'], array_column($steps, 'nodeId'));
        $runtime = static::getContainer()->get(WorkflowRuntimeRegistry::class)->get('core:approval');
        $id = $runtime->start($this->release, $data, 'Foreign currency template test', $this->context);
        foreach ($this->tasks($id) as $task) {
            $this->decide($task->id, $task->assigneeId);
        }
        static::assertSame('pending', $runtime->status($this->release, $id, $this->context)->status);
        $finance = $this->tasks($id)->filter(static fn ($task): bool => $task->nodeId === 'finance')->first();
        static::assertNotNull($finance);
        static::assertSame($steps[2]['assignees'][0]['id'], $finance->assigneeId);
        $this->decide($finance->id, $finance->assigneeId);
        static::assertSame('approved', $runtime->status($this->release, $id, $this->context)->status);
        static::assertCount(1, $this->copies($id));
    }

    public static function foreignAmounts(): \Generator
    {
        yield 'foreign amount below CNY threshold' => ['1.00'];
        yield 'foreign amount above CNY threshold' => ['5000.01'];
    }

    public function testUnsupportedTransferCurrencyIsBlockedBeforePreviewingAnApproval(): void
    {
        $draft = $this->draft(100, 'USD');
        $before = $this->rowCounts();
        $preview = $this->approvals->preview($draft, $this->context);
        static::assertFalse($preview['available']);
        static::assertSame('currency_not_supported', $preview['reason']);
        static::assertSame([], $preview['steps']);
        static::assertSame($before, $this->rowCounts());
        $this->expectExceptionObject(PaymentProviderException::currencyNotSupported('alipay_h5', 'USD'));
        $this->approvals->book($draft, $this->context);
    }

    #[DataProvider('organizationChanges')]
    public function testOrganizationChangeBlocksPreviewAndRollsBackBooking(string $change): void
    {
        $draft = $this->draft(500001, 'CNY');
        static::assertTrue($this->approvals->preview($draft, $this->context)['available']);
        $system = Context::createDefaultContext();
        if ($change === 'unstaffed') {
            $this->connection->delete('user_position', ['position_id' => Uuid::fromHexToBytes($this->positionId)]);
        } elseif ($change === 'inactive-member') {
            static::getContainer()->get('user.repository')->update([['id' => $this->users[1]->getUserId(), 'active' => false]], $system);
        } elseif ($change === 'permission-removed') {
            static::getContainer()->get('acl_role.repository')->update([['id' => $this->users[1]->getAclRoleId(), 'privileges' => []]], $system);
        } else {
            static::getContainer()->get('position.repository')->update([['id' => $this->positionId, 'active' => false]], $system);
        }
        $before = $this->rowCounts();
        $preview = $this->approvals->preview($draft, $this->context);
        static::assertFalse($preview['available']);
        static::assertSame($change === 'inactive-position' ? 'position_unavailable' : 'approval_assignee_unavailable', $preview['reason']);
        try {
            $this->approvals->book($draft, $this->context);
            static::fail('An invalid organization assignment must prevent booking.');
        } catch (WorkflowException) {
            static::assertSame($before, $this->rowCounts());
        }
    }

    public static function organizationChanges(): \Generator
    {
        yield 'selected position becomes unstaffed' => ['unstaffed'];
        yield 'one of two reviewers becomes inactive' => ['inactive-member'];
        yield 'one reviewer loses decision permission' => ['permission-removed'];
        yield 'selected position becomes inactive' => ['inactive-position'];
    }

    #[DataProvider('refusals')]
    public function testRefusalLeavesNoFutureTaskOrCopy(string $decision): void
    {
        $transfer = $this->approvals->book($this->draft(500001, 'CNY'), $this->context);
        $instanceId = $this->instanceId($transfer);
        $tasks = $this->tasks($instanceId);
        $first = $tasks->first();
        static::assertNotNull($first);
        $this->decide($first->id, $first->assigneeId);
        if ($decision === 'canceled') {
            $this->approvals->cancel($transfer->id, 'Withdraw request', $this->context);
        } else {
            $remaining = $this->tasks($instanceId)->filter(static fn ($task): bool => $task->state() === 'todo')->first();
            static::assertNotNull($remaining);
            static::getContainer()->get(ApprovalWorkflowService::class)->reject($remaining->id, 'Invoice missing', $this->actor($remaining->assigneeId));
        }
        static::assertSame($decision, $this->approvals->status($transfer->id, $this->context)['status']);
        static::assertSame($decision, $this->approvals->summaries([$transfer->id], $this->context)[$transfer->id]['status']);
        static::assertSame(0, $this->tasks($instanceId)->countByState('todo'));
        static::assertCount(2, $this->tasks($instanceId));
        static::assertSame(1, $this->tasks($instanceId)->countByState('agreed'));
        static::assertCount(0, $this->copies($instanceId));
        $history = static::getContainer()->get(WorkflowTaskFinder::class)->historyOf($instanceId, $this->context);
        static::assertCount($decision === 'canceled' ? 1 : 2, $history);
        static::assertContains('agree', array_values(array_map(static fn ($entry): string => $entry->decision, $history->getElements())));
        $persisted = static::getContainer()->get(PaymentTransferService::class)->getById($transfer->id);
        static::assertSame($decision === 'canceled' ? 'canceled' : 'created', $persisted->statusMachineState?->getTechnicalName());
        static::assertSame(0, $persisted->submitAttempts);
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked($decision));
        $this->approvals->assertApproved($persisted);
    }

    public function testReturnedTransferKeepsItsEvidenceAndRequiresFreshApprovals(): void
    {
        $transfer = $this->approvals->book($this->draft(500001, 'CNY'), $this->context);
        $instanceId = $this->instanceId($transfer);
        $flows = static::getContainer()->get(ApprovalWorkflowService::class);
        $task = $this->tasks($instanceId)->first();
        static::assertNotNull($task);
        $flows->returnToInitiator($task->id, 'Provide supporting information', $this->actor($task->assigneeId));
        static::assertSame('returned', $this->approvals->status($transfer->id, $this->context)['status']);
        $resubmission = static::getContainer()->get(ApprovalResubmission::class);
        $changed = ['amount' => '1'];
        try {
            $resubmission->resubmit($instanceId, 1, $changed, 'Explanation', $this->context, formVersion: 0);
            static::fail('Booked transfer inputs must remain immutable');
        } catch (WorkflowExecutionException $exception) {
            static::assertSame(403, $exception->getStatusCode());
        }
        static::assertSame(1, $flows->instanceDetail($instanceId, $this->context)->round);
        $persisted = static::getContainer()->get(PaymentTransferService::class)->getById($transfer->id);
        try {
            $this->approvals->assertApproved($persisted);
            static::fail('A returned transfer cannot be executed');
        } catch (PaymentTransferApprovalException $exception) {
            static::assertSame(PaymentTransferApprovalException::blocked('returned')->getMessage(), $exception->getMessage());
        }
        static::assertSame(500001, $persisted->amount);
        static::assertSame(0, $persisted->submitAttempts);
        $resubmission->resubmit($instanceId, 1, [], 'Supporting information provided', $this->context);
        static::assertSame('pending', $this->approvals->status($transfer->id, $this->context)['status']);
        static::assertSame(2, $flows->instanceDetail($instanceId, $this->context)->round);
        static::assertSame(2, $this->tasks($instanceId)->countByState('todo'));
    }

    public static function refusals(): \Generator
    {
        yield 'reject after a sibling agreed' => ['rejected'];
        yield 'withdraw after a sibling agreed' => ['canceled'];
    }

    public function testComplexTransferWaitsForAddedReviewerBeforeFinanceOrSign(): void
    {
        $this->configureFinanceReview('core:or-sign');
        $draft = $this->draft(500001, 'CNY');
        $preview = $this->approvals->preview($draft, $this->context);
        static::assertTrue($preview['available']);
        static::assertSame(WorkflowCountersignTypes::OR_SIGN, $preview['steps'][2]['rule']);

        $transfer = $this->approvals->book($draft, $this->context);
        $instanceId = $this->instanceId($transfer);
        $flows = static::getContainer()->get(ApprovalWorkflowService::class);
        $reminders = static::getContainer()->get(ApprovalReminderService::class);
        $first = $this->pendingTask($instanceId, 'department', 1);
        $originalDeadline = $this->reminder($first->id)->dueAt;
        static::assertNotNull($originalDeadline);
        $this->assertExecutionBlocked($transfer, 'pending');

        $reminders->remind($instanceId, $this->context);
        static::assertSame(1, $this->reminder($first->id)->reminderCount);
        $flows->participate($first->id, ApprovalTaskActions::TRANSFER, $this->users[5]->getUserId(), 'Delegate review', $this->actor($first->assigneeId));
        $replacement = $this->pendingTask($instanceId, 'department', 5);
        static::assertEquals($originalDeadline, $this->reminder($replacement->id)->dueAt);
        $flows->participate($replacement->id, ApprovalTaskActions::ADD_SIGN, $this->users[6]->getUserId(), 'Additional evidence review', $this->actor($replacement->assigneeId));
        $added = $this->pendingTask($instanceId, 'department', 6);
        $addedCreatedAt = $added->getCreatedAt();
        static::assertNotNull($addedCreatedAt);
        $addedDeadline = $this->reminder($added->id)->dueAt;
        static::assertNotNull($addedDeadline);
        static::assertSame(3600, $addedDeadline->getTimestamp() - $addedCreatedAt->getTimestamp());

        $this->decide($replacement->id, $replacement->assigneeId);
        $second = $this->pendingTask($instanceId, 'department', 2);
        $this->decide($second->id, $second->assigneeId);
        static::assertSame(1, $this->tasks($instanceId)->countByState(WorkflowTaskStates::TODO));
        static::assertCount(0, $this->copies($instanceId));
        $this->assertExecutionBlocked($transfer, 'pending');

        $this->decide($added->id, $added->assigneeId);
        static::assertSame(2, $this->tasks($instanceId)->countByState(WorkflowTaskStates::TODO));
        $finance = $this->pendingTask($instanceId, 'finance', 3);
        $otherFinance = $this->pendingTask($instanceId, 'finance', 5);
        $this->assertExecutionBlocked($transfer, 'pending');
        $this->decide($finance->id, $finance->assigneeId);

        static::assertSame('approved', $this->approvals->status($transfer->id, $this->context)['status']);
        static::assertSame(WorkflowTaskStates::CANCELED, $flows->loadTask($otherFinance->id, $this->context)->state());
        static::assertCount(1, $this->copies($instanceId));
        static::assertSame(0, $this->tasks($instanceId)->countByState(WorkflowTaskStates::TODO));
        $booker = static::getContainer()->get(PaymentTransferResultBooker::class);
        $booker->claim($transfer);
        static::assertSame(1, static::getContainer()->get(PaymentTransferService::class)->getById($transfer->id)->submitAttempts);
        $this->expectExceptionObject(PaymentTransferException::transferAlreadyFinalized($transfer->outNo, 'unknown'));
        $booker->claim($transfer);
    }

    public function testReturnedComplexTransferRetainsPublishedSequenceAndResetsReminders(): void
    {
        $this->configureFinanceReview('core:sequential');
        $transfer = $this->approvals->book($this->draft(500001, 'CNY'), $this->context);
        $instanceId = $this->instanceId($transfer);
        $flows = static::getContainer()->get(ApprovalWorkflowService::class);
        $reminders = static::getContainer()->get(ApprovalReminderService::class);
        foreach ($this->tasks($instanceId) as $task) {
            $this->decide($task->id, $task->assigneeId);
        }
        $finance = $this->pendingTask($instanceId, 'finance', 3);
        static::assertSame(1, $this->tasks($instanceId)->countByState(WorkflowTaskStates::TODO));
        static::getContainer()->get('workflow_approval_reminder.repository')->update([
            ['id' => $finance->id, 'dueAt' => new \DateTimeImmutable('-1 minute')],
        ], Context::createDefaultContext());
        $reminders->runDue();
        static::assertSame(ApprovalReminderEntity::OVERDUE, $this->reminder($finance->id)->source);
        static::assertSame(WorkflowTaskStates::TODO, $flows->loadTask($finance->id, $this->context)->state());
        $flows->returnToInitiator($finance->id, 'Provide supporting information', $this->actor($finance->assigneeId));
        $this->assertExecutionBlocked($transfer, 'returned');
        static::assertCount(0, $this->copies($instanceId));

        // A newer release must not change an existing transfer's resubmitted approval.
        $this->configureFinanceReview('core:or-sign');
        static::getContainer()->get(ApprovalResubmission::class)->resubmit($instanceId, 1, [], 'Evidence supplied', $this->context);
        static::assertSame(2, $flows->instanceDetail($instanceId, $this->context)->round);
        foreach ($this->tasks($instanceId) as $task) {
            if ($task->state() === WorkflowTaskStates::TODO) {
                $this->decide($task->id, $task->assigneeId);
            }
        }

        $first = $this->pendingTask($instanceId, 'finance', 3);
        static::assertSame(2, $first->round);
        static::assertSame(WorkflowCountersignTypes::ORDER_SIGN, $first->countersignType);
        static::assertSame(1, $this->tasks($instanceId)->countByState(WorkflowTaskStates::TODO));
        $receipt = $this->reminder($first->id);
        $firstCreatedAt = $first->getCreatedAt();
        static::assertNotNull($firstCreatedAt);
        static::assertNotNull($receipt->dueAt);
        static::assertSame(1800, $receipt->dueAt->getTimestamp() - $firstCreatedAt->getTimestamp());
        static::assertSame(0, $receipt->reminderCount);
        static::assertNull($receipt->overdueRemindedAt);
        static::assertNull($receipt->lastRemindedAt);
        $this->decide($first->id, $first->assigneeId);
        $this->assertExecutionBlocked($transfer, 'pending');
        $next = $this->pendingTask($instanceId, 'finance', 5);
        static::assertSame(2, $next->round);
        $nextDeadline = $this->reminder($next->id)->dueAt;
        $nextCreatedAt = $next->getCreatedAt();
        static::assertNotNull($nextDeadline);
        static::assertNotNull($nextCreatedAt);
        static::assertSame(1800, $nextDeadline->getTimestamp() - $nextCreatedAt->getTimestamp());
        $this->decide($next->id, $next->assigneeId);

        static::assertSame('approved', $this->approvals->status($transfer->id, $this->context)['status']);
        static::assertCount(1, $this->copies($instanceId));
        static::assertSame(2, $this->copies($instanceId)->first()?->round);
        $this->approvals->assertApproved($transfer);
        static::assertSame(0, static::getContainer()->get(PaymentTransferService::class)->getById($transfer->id)->submitAttempts);
    }

    #[DataProvider('refusals')]
    public function testStoppedFinanceReviewCannotContinueOrClaimATransfer(string $decision): void
    {
        $this->configureFinanceReview('core:sequential');
        $transfer = $this->approvals->book($this->draft(500001, 'CNY'), $this->context);
        $instanceId = $this->instanceId($transfer);
        foreach ($this->tasks($instanceId) as $task) {
            $this->decide($task->id, $task->assigneeId);
        }
        $finance = $this->pendingTask($instanceId, 'finance', 3);
        static::getContainer()->get('workflow_approval_reminder.repository')->update([
            ['id' => $finance->id, 'dueAt' => new \DateTimeImmutable('-1 minute')],
        ], Context::createDefaultContext());
        if ($decision === 'canceled') {
            $this->approvals->cancel($transfer->id, 'Withdraw before payout', $this->context);
        } else {
            static::getContainer()->get(ApprovalWorkflowService::class)->reject($finance->id, 'Evidence rejected', $this->actor($finance->assigneeId));
        }

        static::getContainer()->get(ApprovalReminderService::class)->runDue();
        static::assertSame(0, $this->reminder($finance->id)->reminderCount);
        static::assertSame($decision, $this->approvals->status($transfer->id, $this->context)['status']);
        static::assertSame(0, $this->tasks($instanceId)->countByState(WorkflowTaskStates::TODO));
        static::assertCount(3, $this->tasks($instanceId), 'The second sequential reviewer must never receive a task.');
        static::assertCount(0, $this->copies($instanceId));

        $expected = $decision === 'canceled'
            ? PaymentTransferException::transferAlreadyFinalized($transfer->outNo, 'unknown')
            : PaymentTransferApprovalException::blocked($decision);
        try {
            static::getContainer()->get(PaymentTransferResultBooker::class)->claim($transfer);
            static::fail('A stopped finance approval must not authorize a transfer attempt.');
        } catch (PaymentTransferException|PaymentTransferApprovalException $exception) {
            static::assertSame($expected->getErrorCode(), $exception->getErrorCode());
            static::assertSame($expected->getMessage(), $exception->getMessage());
        }
        static::assertSame(0, static::getContainer()->get(PaymentTransferService::class)->getById($transfer->id)->submitAttempts);
    }

    public function testSupplementalReviewDataNeverChangesBookedTransferEvidence(): void
    {
        $drafts = static::getContainer()->get(WorkflowDraftStore::class);
        $draft = $drafts->get($this->draftId, $this->context);
        $document = json_decode($draft->getDocument(), true, flags: \JSON_THROW_ON_ERROR);
        foreach ($document['nodes'] as &$node) {
            if ($node['type'] === WorkflowNodeTypes::START) {
                $node['config']['formFields'][] = ['key' => 'financeNote', 'label' => 'Finance review', 'type' => 'text', 'required' => false];
            } elseif ($node['type'] === WorkflowNodeTypes::APPROVAL) {
                $node['config']['formPermissions'] = [
                    'amount' => ['access' => 'editable'],
                    'financeNote' => ['access' => 'editable', 'required' => true],
                ];
            }
        }
        unset($node);
        $updated = $drafts->update($this->draftId, $draft->getName(), json_encode($document, \JSON_THROW_ON_ERROR), $draft->getRevision(), $this->context);
        $this->release = static::getContainer()->get(WorkflowReleaseStore::class)->publish($this->draftId, $updated->getRevision(), 'core:approval', $this->context);
        $this->approvals->configure($this->release->getId(), $this->context);
        $transfer = $this->approvals->book($this->draft(10000, 'CNY'), $this->context);
        $instanceId = $this->instanceId($transfer);
        $flows = static::getContainer()->get(ApprovalWorkflowService::class);
        $first = $this->tasks($instanceId)->first();
        static::assertNotNull($first);
        try {
            $flows->approve($first->id, '', $this->actor($first->assigneeId), ['amount' => '1', 'financeNote' => 'Checked'], 0);
            static::fail('Published edit access must not override financial locks.');
        } catch (WorkflowExecutionException $exception) {
            static::assertSame(403, $exception->getStatusCode());
        }
        $flows->approve($first->id, '', $this->actor($first->assigneeId), ['financeNote' => 'Checked'], 0);
        foreach ($this->tasks($instanceId) as $task) {
            if ($task->isTodo()) {
                $flows->approve($task->id, '', $this->actor($task->assigneeId), [], 1);
            }
        }
        static::assertSame('Checked', $flows->instanceDetail($instanceId, $this->context)->form()['financeNote']);
        $persisted = static::getContainer()->get(PaymentTransferService::class)->getById($transfer->id);
        static::assertSame(10000, $persisted->amount);
        static::assertSame(0, $persisted->submitAttempts);
        static::assertSame('approved', $this->approvals->status($transfer->id, $this->context)['status']);
    }

    private function configureFinanceReview(string $mode): void
    {
        $drafts = static::getContainer()->get(WorkflowDraftStore::class);
        $draft = $drafts->get($this->draftId, $this->context);
        $document = json_decode($draft->getDocument(), true, flags: \JSON_THROW_ON_ERROR);
        foreach ($document['nodes'] as &$node) {
            if ($node['type'] !== WorkflowNodeTypes::APPROVAL) {
                continue;
            }
            $node['config']['deadline'] = ['minutes' => 60, 'remind' => true];
            if ($node['id'] === 'finance') {
                $node['config']['assignee'] = [
                    'source' => WorkflowAssigneeSources::USERS,
                    'config' => ['userIds' => [$this->users[3]->getUserId(), $this->users[5]->getUserId()]],
                ];
                $node['config']['approval'] = ['mode' => $mode, 'config' => []];
                $node['config']['deadline']['minutes'] = 30;
            }
        }
        unset($node);
        $updated = $drafts->update($this->draftId, $draft->getName(), json_encode($document, \JSON_THROW_ON_ERROR), $draft->getRevision(), $this->context);
        $this->release = static::getContainer()->get(WorkflowReleaseStore::class)->publish($this->draftId, $updated->getRevision(), 'core:approval', $this->context);
        $this->approvals->configure($this->release->getId(), $this->context);
    }

    private function pendingTask(string $instanceId, string $nodeId, int $user): WorkflowTaskEntity
    {
        $task = $this->tasks($instanceId)->filter(fn (WorkflowTaskEntity $task): bool => $task->nodeId === $nodeId
            && $task->assigneeId === $this->users[$user]->getUserId()
            && $task->state() === WorkflowTaskStates::TODO)->first();
        static::assertNotNull($task, 'Expected a pending task at ' . $nodeId . ' for user ' . $user);

        return $task;
    }

    private function reminder(string $taskId): ApprovalReminderEntity
    {
        $receipt = static::getContainer()->get('workflow_approval_reminder.repository')->search(new Criteria([$taskId]), Context::createDefaultContext())->first();
        static::assertInstanceOf(ApprovalReminderEntity::class, $receipt);

        return $receipt;
    }

    private function assertExecutionBlocked(PaymentTransferEntity $transfer, string $status): void
    {
        try {
            static::getContainer()->get(PaymentTransferResultBooker::class)->claim($transfer);
            static::fail('An incomplete approval must not authorize a transfer attempt.');
        } catch (PaymentTransferApprovalException $exception) {
            static::assertSame(PaymentTransferApprovalException::blocked($status)->getMessage(), $exception->getMessage());
        }
        static::assertSame(0, static::getContainer()->get(PaymentTransferService::class)->getById($transfer->id)->submitAttempts);
    }

    private function actor(string $id): Context
    {
        $source = new AdminApiSource($id);
        $source->setPermissions(['workflow:read', 'workflow:publish', 'workflow_instance:access', 'workflow_instance:start', 'workflow_task:decide', 'payment_transfer:create']);

        return new Context($source);
    }

    private function draft(int $amount, string $currency): PaymentTransferDraft
    {
        return new PaymentTransferDraft(paymentAppId: $this->paymentFixture->paymentAppId, provider: 'alipay', channel: 'alipay_h5', recipientId: $this->paymentFixture->recipientId, amount: $amount, currency: $currency, outNo: substr(Uuid::randomHex(), 0, 30), note: 'Template test', accountId: $this->paymentFixture->accountId);
    }

    private function instanceId(PaymentTransferEntity $transfer): string
    {
        $id = $this->approvals->status($transfer->id, $this->context)['instanceId'];
        static::assertIsString($id);

        return $id;
    }

    private function tasks(string $instanceId): WorkflowTaskCollection
    {
        return static::getContainer()->get(WorkflowTaskFinder::class)->forInstance($instanceId, $this->context);
    }

    private function copies(string $instanceId): WorkflowCopyCollection
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('instanceId', $instanceId));

        $copies = static::getContainer()->get('workflow_copy.repository')->search($criteria, $this->context)->getEntities();
        static::assertInstanceOf(WorkflowCopyCollection::class, $copies);

        return $copies;
    }

    private function decide(string $taskId, string $userId): void
    {
        static::getContainer()->get(ApprovalWorkflowService::class)->approve($taskId, 'Approved', $this->actor($userId));
    }

    /**
     * @return array<string, int>
     */
    private function rowCounts(): array
    {
        $counts = [];
        foreach (['payment_transfer', 'payment_transfer_approval', 'payment_transfer_recipient', 'workflow_instance', 'workflow_task', 'workflow_copy'] as $table) {
            $counts[$table] = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $table);
        }

        return $counts;
    }

    private function ensureCurrency(string $code): void
    {
        if ($this->connection->fetchOne('SELECT id FROM currency WHERE iso_code = :code', ['code' => $code]) !== false) {
            return;
        }
        $id = Uuid::randomHex();
        $this->currencyIds[] = $id;
        static::getContainer()->get('currency.repository')->create([[
            'id' => $id, 'isoCode' => $code, 'name' => $code, 'shortName' => $code, 'symbol' => $code,
            'factor' => 1.0, 'position' => 0, 'decimalPrecision' => 2,
        ]], Context::createDefaultContext());
    }
}
