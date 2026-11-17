<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Workflow\Node\Approval\Form;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\Event\WorkflowCompletingEvent;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;
use Shopwell\Core\Framework\Workflow\Node\Approval\Form\ApprovalFormPolicyEvent;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalCondition;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\System\Organization\OrganizationDefaults;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalFormTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    /**
     * @var list<TestBrowser>
     */
    private array $actors;

    /**
     * @var list<string>
     */
    private array $userIds;

    private Context $context;

    protected function setUp(): void
    {
        $this->actors = [];
        $this->userIds = [];
        for ($index = 0; $index < 3; ++$index) {
            $this->actors[] = $this->createClient(permissions: ['workflow_instance:access', 'workflow_instance:start', 'workflow_task:decide', 'workflow_instance:read', 'workflow_log:read']);
            $this->userIds[] = Uuid::fromBytesToHex(static::getContainer()->get(Connection::class)->fetchOne(
                'SELECT id FROM user WHERE username = :username',
                ['username' => $this->apiUsernames[array_key_last($this->apiUsernames) ?? 0]],
            ));
            $userId = $this->userIds[array_key_last($this->userIds)];
            static::getContainer()->get('user.repository')->update([[
                'id' => $userId,
                'email' => $userId . '@example.com',
                'organizationUnitMappings' => [['organizationUnitId' => OrganizationDefaults::UNIT, 'isPrimary' => true]],
                'positionMappings' => [[
                    'positionId' => OrganizationDefaults::POSITION,
                    'organizationUnitId' => OrganizationDefaults::UNIT,
                    'isPrimary' => true,
                ]],
            ]], Context::createDefaultContext());
        }
        array_multisort($this->userIds, $this->actors);
        $source = new AdminApiSource($this->userIds[0]);
        $source->setIsAdmin(true);
        $this->context = new Context($source);
    }

    public function testApprovalUpdatesOnlyEditableFieldsAndAuditsTheChange(): void
    {
        $id = $this->start();
        $task = $this->task($id, 1);
        $this->approve($task, ['note' => 'Verified'], 0);
        static::assertSame(['note' => 'Verified', 'reason' => 'Original', 'secret' => 'Restricted'], $this->instance($id)->form());
        $detail = $this->detail($id, 1, $task->id);
        static::assertSame(1, $detail['formVersion']);
        static::assertArrayNotHasKey('secret', $detail['formData']);
        static::assertSame(['note' => ['after' => 'Verified', 'before' => null]], $detail['formChanges'][0]['changes']);
    }

    public function testReadonlyAndUnknownPatchesDoNotCompleteTheTask(): void
    {
        $id = $this->start();
        $task = $this->task($id, 1);
        $this->approve($task, ['reason' => 'Forged'], 0, 403);
        $this->approve($task, ['unknown' => 'Forged'], 0, 403);
        static::assertTrue(static::getContainer()->get(ApprovalWorkflowService::class)->loadTask($task->id, $this->context)->isTodo());
        static::assertSame('Original', $this->instance($id)->form()['reason']);
        static::assertSame(0, $this->detail($id, 1, $task->id)['formVersion']);
    }

    public function testRequiredNoteBlocksApproveButNotReturn(): void
    {
        $id = $this->start();
        $task = $this->task($id, 1);
        $this->approve($task, [], 0, 400);
        $this->actors[1]->jsonRequest('POST', '/api/_action/workflow/task/' . $task->id . '/return', ['comment' => 'Revise']);
        static::assertSame(204, $this->actors[1]->getResponse()->getStatusCode());
    }

    public function testStaleCountersignPatchCannotOverwriteAnotherApprover(): void
    {
        $id = $this->start(countersign: true);
        $first = $this->task($id, 1);
        $second = $this->task($id, 2);
        $this->approve($first, ['note' => 'First reviewer'], 0);
        $this->approve($second, ['note' => 'Stale edit'], 0, 409);
        static::assertSame('First reviewer', $this->instance($id)->form()['note']);
        $this->approve($second, ['note' => 'Second reviewer'], 1);
        static::assertSame('agreed', $this->instance($id)->state());
    }

    public function testBusinessSubscriberCanOnlyRestrictNodePermissions(): void
    {
        $id = $this->start();
        $task = $this->task($id, 1);
        EventHookDispatcher::fromContainer(static::getContainer())->on(ApprovalFormPolicyEvent::class, static function (ApprovalFormPolicyEvent $event): void {
            $event->readOnly('note');
        });
        $detail = $this->detail($id, 1, $task->id);
        static::assertSame('readonly', $detail['formPermissions']['note']['access']);
        $this->approve($task, ['note' => 'Bypass restriction'], 0, 403);
    }

    #[DataProvider('genericApiFormats')]
    public function testCopyVisibilityAndGenericApiCannotLeakHiddenValues(string $accept): void
    {
        $id = $this->start(withCopy: true);
        $detail = $this->detail($id, 2);
        static::assertSame(['reason' => 'Original'], $detail['formData']);
        static::assertSame(['reason'], array_column($detail['formSchema'], 'key'));
        static::assertStringNotContainsString('Restricted', json_encode($detail, \JSON_THROW_ON_ERROR));
        $logId = Uuid::randomHex();
        static::getContainer()->get('workflow_log.repository')->create([[
            'id' => $logId,
            'instanceId' => $id,
            'action' => 'start',
            'logType' => 'normal',
            'payload' => ['secret' => 'Restricted'],
        ]], Context::createDefaultContext());
        $this->actors[2]->request('GET', '/api/workflow-instance/' . $id, server: ['HTTP_ACCEPT' => $accept]);
        static::assertSame(200, $this->actors[2]->getResponse()->getStatusCode());
        static::assertStringNotContainsString('Restricted', (string) $this->actors[2]->getResponse()->getContent());
        $this->actors[2]->request('GET', '/api/workflow-log', server: ['HTTP_ACCEPT' => $accept]);
        static::assertSame(200, $this->actors[2]->getResponse()->getStatusCode());
        $body = json_decode((string) $this->actors[2]->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertContains($logId, array_column($body['data'], 'id'));
        foreach ($body['data'] as $log) {
            static::assertArrayNotHasKey('payload', $log['attributes'] ?? $log);
        }
        static::assertStringNotContainsString('Restricted', (string) $this->actors[2]->getResponse()->getContent());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function genericApiFormats(): iterable
    {
        yield 'JSON:API' => ['application/vnd.api+json'];
        yield 'plain JSON' => ['application/json'];
    }

    public function testReturnedInstanceKeepsThePreviousReviewerReadContext(): void
    {
        $id = $this->start();
        $task = $this->task($id, 1);
        $this->actors[1]->jsonRequest('POST', '/api/_action/workflow/task/' . $task->id . '/return', ['comment' => 'Revise']);
        static::assertSame(204, $this->actors[1]->getResponse()->getStatusCode());

        $reviewer = $this->detail($id, 1, $task->id);
        static::assertSame('readonly', $reviewer['formPermissions']['note']['access']);
        static::assertArrayNotHasKey('secret', $reviewer['formData']);
        static::assertSame('Original', $reviewer['formData']['reason']);
        $initiator = $this->detail($id, 0);
        static::assertSame('editable', $initiator['formPermissions']['reason']['access']);
    }

    public function testResubmissionMergesPatchAndKeepsUnchangedFields(): void
    {
        $id = $this->start();
        $task = $this->task($id, 1);
        $this->actors[1]->jsonRequest('POST', '/api/_action/workflow/task/' . $task->id . '/return', ['comment' => 'Revise']);
        $this->actors[0]->jsonRequest('POST', '/api/_action/workflow/instance/' . $id . '/resubmit', [
            'round' => 1, 'formVersion' => 0, 'formData' => ['reason' => 'Revised'],
        ]);
        static::assertSame(204, $this->actors[0]->getResponse()->getStatusCode(), (string) $this->actors[0]->getResponse()->getContent());
        static::assertSame('Restricted', $this->instance($id)->form()['secret']);
        static::assertSame('Revised', $this->instance($id)->form()['reason']);
        static::assertSame(1, $this->detail($id, 0)['formVersion']);
    }

    public function testFailureAfterFormWriteRollsBackDataJournalAndDecision(): void
    {
        $id = $this->start();
        $task = $this->task($id, 1);
        EventHookDispatcher::fromContainer(static::getContainer())->on(WorkflowCompletingEvent::class, static function (): void {
            throw new \RuntimeException('Business completion failed');
        });
        try {
            static::getContainer()->get(ApprovalWorkflowService::class)->approve($task->id, '', $this->actor(1), ['note' => 'Must roll back'], 0);
            static::fail('Expected completion failure.');
        } catch (\RuntimeException $exception) {
            static::assertSame('Business completion failed', $exception->getMessage());
        }
        static::assertArrayNotHasKey('note', $this->instance($id)->form());
        static::assertSame(0, $this->detail($id, 1, $task->id)['formVersion']);
        static::assertSame([], $this->detail($id, 1, $task->id)['formChanges']);
        static::assertTrue(static::getContainer()->get(ApprovalWorkflowService::class)->loadTask($task->id, $this->context)->isTodo());
    }

    public function testTransferAndAddedReviewerInheritTheNodePolicy(): void
    {
        $id = $this->start();
        $original = $this->task($id, 1);
        $flows = static::getContainer()->get(ApprovalWorkflowService::class);
        $flows->participate($original->id, 'add-sign', $this->userIds[2], 'Specialist needed', $this->actor(1));
        $added = $this->task($id, 2);
        static::assertSame('editable', $this->detail($id, 2, $added->id)['formPermissions']['note']['access']);
        $this->approve($added, ['note' => 'Specialist checked'], 0);
        $flows->participate($original->id, 'transfer', $this->userIds[0], 'Transfer review', $this->actor(1));
        $transferred = $this->task($id, 0);
        $detail = $this->detail($id, 0, $transferred->id);
        static::assertArrayNotHasKey('secret', $detail['formData']);
        $this->approve($transferred, ['reason' => 'Not allowed'], 1, 403);
        $this->approve($transferred, [], 1);
        static::assertSame('agreed', $this->instance($id)->state());
    }

    public function testEditingChangesOnlyFutureConditionEvaluation(): void
    {
        $id = $this->start(withCondition: true);
        $task = $this->task($id, 1);
        $this->approve($task, ['note' => 'Escalate'], 0);
        static::assertSame('followup', $this->instance($id)->currentNodeId);
        $detail = $this->detail($id, 2);
        static::assertContains('route-followup', $detail['selectedPath']['edgeIds']);
        static::assertNotContains('route-end', $detail['selectedPath']['edgeIds']);
    }

    public function testUnknownOrMissingVersionAndHiddenFieldPatchesAreRejected(): void
    {
        $id = $this->start();
        $task = $this->task($id, 1);
        $this->approve($task, ['secret' => 'Leak'], 0, 403);
        $this->actors[1]->jsonRequest('POST', '/api/_action/workflow/task/' . $task->id . '/approve', ['formData' => ['note' => 'Missing version']]);
        static::assertSame(409, $this->actors[1]->getResponse()->getStatusCode());
        $this->approve($task, ['note' => str_repeat('a', 4001)], 0, 400);
        static::assertSame([], $this->detail($id, 1, $task->id)['formChanges']);
    }

    public function testMultipleHistoricalContextsDoNotUnionTheirVisibility(): void
    {
        $id = $this->start(withCondition: true, sameFollowupActor: true);
        $first = $this->task($id, 1);
        $this->approve($first, ['note' => 'Escalate'], 0);
        $second = $this->task($id, 1);
        $this->approve($second, [], 1);
        $unselected = $this->detail($id, 1);
        static::assertTrue($unselected['formContextRequired']);
        static::assertSame([], $unselected['formData']);
        $firstContext = $this->detail($id, 1, $first->id);
        static::assertArrayNotHasKey('secret', $firstContext['formData']);
        $secondContext = $this->detail($id, 1, $second->id);
        static::assertSame('Restricted', $secondContext['formData']['secret']);
        static::assertArrayNotHasKey('note', $secondContext['formData']);
        static::assertSame([], $secondContext['formChanges']);
    }

    private function actor(int $index): Context
    {
        $source = new AdminApiSource($this->userIds[$index]);
        $source->setIsAdmin(true);

        return new Context($source);
    }

    private function start(bool $countersign = false, bool $withCopy = false, bool $withCondition = false, bool $sameFollowupActor = false): string
    {
        $service = static::getContainer()->get(WorkflowService::class);
        $schema = [
            ['key' => 'reason', 'label' => 'Reason', 'type' => 'text', 'required' => true],
            ['key' => 'secret', 'label' => 'Secret', 'type' => 'text', 'required' => false],
            ['key' => 'note', 'label' => 'Review note', 'type' => 'text', 'required' => false],
        ];
        $nodes = [
            ['id' => 'start', 'type' => 'snaker:start', 'properties' => ['inputSchema' => $schema]],
            ['id' => 'review', 'type' => 'snaker:task', 'properties' => [
                'name' => 'Review', 'approvalStage' => true, 'assigneeType' => 'user',
                'assigneeValue' => $countersign ? [$this->userIds[1], $this->userIds[2]] : [$this->userIds[1]],
                'countersignType' => $countersign ? 'countersign' : 'single',
                'approvalFormPermissions' => [
                    'secret' => ['access' => 'hidden', 'required' => false],
                    'note' => ['access' => 'editable', 'required' => true],
                ],
            ]],
            ['id' => 'end', 'type' => 'snaker:end'],
        ];
        $edges = [
            ['id' => 'start-review', 'sourceNodeId' => 'start', 'targetNodeId' => 'review'],
            ['id' => 'review-end', 'sourceNodeId' => 'review', 'targetNodeId' => 'end'],
        ];
        if ($withCopy) {
            $nodes[] = ['id' => 'copy', 'type' => 'snaker:copy', 'properties' => [
                'name' => 'Notify', 'userIdList' => [$this->userIds[2]], 'approvalFormPermissions' => [
                    'secret' => ['access' => 'hidden'], 'note' => ['access' => 'hidden'],
                ],
            ]];
            $edges[0]['targetNodeId'] = 'copy';
            $edges[] = ['id' => 'copy-review', 'sourceNodeId' => 'copy', 'targetNodeId' => 'review'];
        }
        if ($withCondition) {
            $condition = (new ApprovalCondition())->encode([
                'field' => 'request.note', 'fieldType' => 'text', 'operator' => 'equals', 'value' => 'Escalate',
            ], $schema);
            $nodes[] = ['id' => 'route', 'type' => 'snaker:condition'];
            $nodes[] = ['id' => 'followup', 'type' => 'snaker:task', 'properties' => [
                'name' => 'Follow-up', 'approvalStage' => true, 'assigneeType' => 'user',
                'assigneeValue' => [$this->userIds[$sameFollowupActor ? 1 : 2]], 'countersignType' => 'single',
                'approvalFormPermissions' => ['note' => ['access' => 'hidden']],
            ]];
            $edges[1]['targetNodeId'] = 'route';
            $edges[] = ['id' => 'route-followup', 'sourceNodeId' => 'route', 'targetNodeId' => 'followup', 'properties' => ['expr' => $condition]];
            $edges[] = ['id' => 'route-end', 'sourceNodeId' => 'route', 'targetNodeId' => 'end'];
            $edges[] = ['id' => 'followup-end', 'sourceNodeId' => 'followup', 'targetNodeId' => 'end'];
        }
        $definitionId = $service->createDefinition(['code' => Uuid::randomHex(), 'name' => 'Form permissions', 'formSchema' => $schema, 'graphData' => ['nodes' => $nodes, 'edges' => $edges]], $this->context);
        $service->publishDefinition($definitionId, $this->context);

        return $service->start($definitionId, ['reason' => 'Original', 'secret' => 'Restricted'], 'Form permissions', $this->context);
    }

    private function task(string $id, int $actor): WorkflowTaskEntity
    {
        foreach (static::getContainer()->get(WorkflowTaskFinder::class)->forInstance($id, $this->context) as $task) {
            if ($task->assigneeId === $this->userIds[$actor] && $task->isTodo()) {
                return $task;
            }
        }
        static::fail('Expected a pending task.');
    }

    private function instance(string $id): WorkflowInstanceEntity
    {
        return static::getContainer()->get(ApprovalWorkflowService::class)->instanceDetail($id, $this->context);
    }

    /**
     * @param array<string, mixed> $patch
     */
    private function approve(WorkflowTaskEntity $task, array $patch, int $version, int $status = 204): void
    {
        $actor = array_search($task->assigneeId, $this->userIds, true);
        static::assertNotFalse($actor);
        $this->actors[$actor]->jsonRequest('POST', '/api/_action/workflow/task/' . $task->id . '/approve', ['formData' => $patch, 'formVersion' => $version]);
        static::assertSame($status, $this->actors[$actor]->getResponse()->getStatusCode(), (string) $this->actors[$actor]->getResponse()->getContent());
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(string $id, int $actor, ?string $taskId = null): array
    {
        $this->actors[$actor]->request('GET', '/api/_action/workflow/instance/' . $id . ($taskId === null ? '' : '?taskId=' . $taskId));
        static::assertSame(200, $this->actors[$actor]->getResponse()->getStatusCode(), (string) $this->actors[$actor]->getResponse()->getContent());

        return json_decode((string) $this->actors[$actor]->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['data'];
    }
}
