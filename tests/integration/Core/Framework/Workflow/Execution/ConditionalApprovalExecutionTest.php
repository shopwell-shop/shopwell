<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Workflow\Execution;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;

/**
 * @internal
 */
#[Package('framework')]
class ConditionalApprovalExecutionTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    #[DataProvider('amountBoundaries')]
    public function testRoutesOnlySelectedApproversAndReportsVisitedPrefix(string $amount, bool $financeRequired): void
    {
        [$manager, $department, $finance, $ids] = $this->participants();
        [, $release] = $this->publish($manager, $this->document($ids));
        $instance = $this->start($manager, $release, $amount);
        $detail = $this->detail($manager, $instance);
        static::assertSame(['start', 'department'], $detail['selectedPath']['nodeIds']);
        static::assertSame(['active', $financeRequired ? 'pending' : 'skipped'], array_column($detail['approvalStages'], 'status'));
        static::assertSame($financeRequired, \in_array('finance', $detail['plannedPath']['nodeIds'], true));
        static::assertSame($financeRequired ? ['begin', 'department-approved', 'high', 'finance-approved'] : ['begin', 'department-approved', 'default'], $detail['plannedPath']['edgeIds']);
        static::assertCount(1, $detail['approvalTasks']);
        static::assertSame($amount, $detail['formData']['amount']);
        $finance->request('GET', '/api/_action/workflow/instance/' . $instance);
        static::assertSame(403, $finance->getResponse()->getStatusCode());
        $this->assertNoTodos($finance);
        $departmentTask = $detail['tasks'][0]['id'];
        $department->jsonRequest('POST', '/api/_action/workflow/task/' . $departmentTask . '/approve', []);
        static::assertSame(204, $department->getResponse()->getStatusCode(), $this->errors($department));
        $after = $this->detail($manager, $instance);
        static::assertSame($financeRequired ? 'RUNNING' : 'AGREE', $after['status']);
        static::assertSame(['approved', $financeRequired ? 'active' : 'skipped'], array_column($after['approvalStages'], 'status'));
        static::assertSame($financeRequired ? ['start', 'department', 'threshold', 'finance'] : $after['plannedPath']['nodeIds'], $after['selectedPath']['nodeIds']);
        if ($financeRequired) {
            $finance->jsonRequest('POST', '/api/_action/workflow/task/' . $after['tasks'][0]['id'] . '/approve', []);
            static::assertSame(204, $finance->getResponse()->getStatusCode(), $this->errors($finance));
            $finished = $this->detail($manager, $instance);
            static::assertSame('AGREE', $finished['status']);
            static::assertSame($finished['plannedPath'], $finished['selectedPath']);
        } else {
            $this->assertNoTodos($finance);
            $finance->request('GET', '/api/_action/workflow/instance/' . $instance);
            static::assertSame(403, $finance->getResponse()->getStatusCode());
        }
        $department->jsonRequest('POST', '/api/_action/workflow/task/' . $departmentTask . '/approve', []);
        static::assertSame(409, $department->getResponse()->getStatusCode());
        static::assertCount($financeRequired ? 2 : 1, static::getContainer()->get(WorkflowTaskFinder::class)->forInstance($instance, Context::createDefaultContext()));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function amountBoundaries(): iterable
    {
        yield 'below threshold skips finance' => ['4999.99', false];
        yield 'exact threshold skips finance' => ['5000.00', false];
        yield 'one cent above threshold requires finance' => ['5000.01', true];
    }

    public function testFirstMatchingBranchWinsOverAnotherMatchRegardlessOfConnectionOrder(): void
    {
        [$manager, $department, , $ids] = $this->participants();
        $document = $this->document($ids);
        $document['nodes'][2]['config']['branches'][] = ['id' => 'branch-lower', 'label' => 'Over zero', 'field' => 'request.amount', 'fieldType' => 'amount', 'operator' => 'greaterThan', 'value' => '0'];
        $document['nodes'][] = ['id' => 'lower-end', 'type' => 'core:end', 'typeVersion' => 1, 'config' => []];
        $document['connections'][] = ['id' => 'lower', 'source' => 'threshold', 'sourcePort' => 'branch-lower', 'target' => 'lower-end', 'targetPort' => 'in'];
        $document['connections'] = array_reverse($document['connections']);
        [, $release] = $this->publish($manager, $document);
        $instance = $this->start($manager, $release, '5000.01');
        $detail = $this->detail($manager, $instance);
        $department->jsonRequest('POST', '/api/_action/workflow/task/' . $detail['tasks'][0]['id'] . '/approve', []);
        static::assertSame(204, $department->getResponse()->getStatusCode(), $this->errors($department));
        $after = $this->detail($manager, $instance);
        static::assertSame('finance', $after['currentNodeId']);
        static::assertContains('high', $after['selectedPath']['edgeIds']);
        static::assertNotContains('lower', $after['selectedPath']['edgeIds']);
    }

    public function testRejectionDoesNotTraverseFutureConditionOrCreateFinanceTask(): void
    {
        [$manager, $department, $finance, $ids] = $this->participants();
        [, $release] = $this->publish($manager, $this->document($ids));
        $instance = $this->start($manager, $release, '5000.01');
        $detail = $this->detail($manager, $instance);
        $department->jsonRequest('POST', '/api/_action/workflow/task/' . $detail['tasks'][0]['id'] . '/reject', ['comment' => 'Not approved']);
        static::assertSame(204, $department->getResponse()->getStatusCode(), $this->errors($department));
        $after = $this->detail($manager, $instance);
        static::assertSame('REJECT', $after['status']);
        static::assertSame(['rejected', 'skipped'], array_column($after['approvalStages'], 'status'));
        static::assertSame(['start', 'department'], $after['selectedPath']['nodeIds']);
        static::assertCount(1, $after['approvalTasks']);
        $this->assertNoTodos($finance);
    }

    public function testNewRevisionDoesNotChangeRunningConditionsOrFormSchema(): void
    {
        [$manager, $department, , $ids] = $this->participants();
        $document = $this->document($ids);
        [$draft, $release] = $this->publish($manager, $document);
        $instance = $this->start($manager, $release, '5000.01');
        $before = $this->detail($manager, $instance);
        $document['nodes'][0]['config']['formFields'][1]['label'] = 'Changed amount label';
        $document['nodes'][2]['config']['branches'][0]['value'] = '10000';
        $manager->jsonRequest('PUT', '/api/_action/workflow/draft/' . $draft, ['name' => 'Changed expense', 'document' => $document, 'expectedRevision' => 1]);
        static::assertSame(200, $manager->getResponse()->getStatusCode(), $this->errors($manager));
        $manager->jsonRequest('POST', '/api/_action/workflow/draft/' . $draft . '/publish', ['expectedRevision' => 2, 'publisher' => 'core:approval']);
        static::assertSame(201, $manager->getResponse()->getStatusCode(), $this->errors($manager));
        $newRelease = $this->response($manager)['id'];
        $department->jsonRequest('POST', '/api/_action/workflow/task/' . $before['tasks'][0]['id'] . '/approve', []);
        static::assertSame(204, $department->getResponse()->getStatusCode(), $this->errors($department));
        $after = $this->detail($manager, $instance);
        static::assertSame($before['formSchema'], $after['formSchema']);
        static::assertSame($before['graph'], $after['graph']);
        static::assertSame('finance', $after['currentNodeId']);
        $newInstance = $this->start($manager, $newRelease, '5000.01');
        $new = $this->detail($manager, $newInstance);
        static::assertSame('Changed amount label', $new['formSchema'][1]['label']);
        static::assertNotContains('finance', $new['plannedPath']['nodeIds']);
    }

    public function testInvalidRequestsCreateNoInstanceOrTasks(): void
    {
        [$manager, , , $ids] = $this->participants();
        [, $release] = $this->publish($manager, $this->document($ids));
        foreach ([
            ['reason' => ' '], ['amount' => '5000.001'], ['expenseDate' => '2026-02-30'], ['category' => 'missing'], ['extra' => 'not allowed'], ['quantity' => '1e5'],
        ] as $invalid) {
            $manager->jsonRequest('POST', '/api/_action/workflow/instance', ['definitionId' => $release, 'formData' => [...$this->form('5000.00'), ...$invalid]]);
            static::assertSame(400, $manager->getResponse()->getStatusCode(), $this->errors($manager));
        }
        $manager->jsonRequest('POST', '/api/_action/workflow/instance', ['definitionId' => $release, 'formData' => []]);
        static::assertSame(400, $manager->getResponse()->getStatusCode(), $this->errors($manager));
        static::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM workflow_instance WHERE definition_id = :id', ['id' => Uuid::fromHexToBytes($release)]));
    }

    public function testRemovedFieldDraftIsEditableButCannotBePublished(): void
    {
        [$manager, , , $ids] = $this->participants();
        $document = $this->document($ids);
        array_splice($document['nodes'][0]['config']['formFields'], 1, 1);
        $draft = Uuid::randomHex();
        $manager->jsonRequest('POST', '/api/_action/workflow/draft', ['id' => $draft, 'name' => 'Invalid expense', 'document' => $document]);
        static::assertSame(201, $manager->getResponse()->getStatusCode(), $this->errors($manager));
        $manager->jsonRequest('POST', '/api/_action/workflow/draft/' . $draft . '/publish', ['expectedRevision' => 1, 'publisher' => 'core:approval']);
        static::assertSame(422, $manager->getResponse()->getStatusCode(), $this->errors($manager));
    }

    public function testCompatibleFieldTypeChangeRequiresExplicitRebindingBeforePublication(): void
    {
        [$manager, , , $ids] = $this->participants();
        $document = $this->document($ids);
        $document['nodes'][0]['config']['formFields'][1]['type'] = 'number';
        $draft = Uuid::randomHex();
        $manager->jsonRequest('POST', '/api/_action/workflow/draft', ['id' => $draft, 'name' => 'Changed field type', 'document' => $document]);
        static::assertSame(201, $manager->getResponse()->getStatusCode(), $this->errors($manager));
        $manager->jsonRequest('POST', '/api/_action/workflow/draft/' . $draft . '/publish', ['expectedRevision' => 1, 'publisher' => 'core:approval']);
        static::assertSame(422, $manager->getResponse()->getStatusCode(), $this->errors($manager));
    }

    public function testUnavailableReviewerOnOptionalBranchBlocksPublication(): void
    {
        [$manager, , , $ids] = $this->participants();
        static::getContainer()->get('user.repository')->update([['id' => $ids[1], 'active' => false]], Context::createDefaultContext());
        $draft = Uuid::randomHex();
        $manager->jsonRequest('POST', '/api/_action/workflow/draft', ['id' => $draft, 'name' => 'Invalid expense', 'document' => $this->document($ids)]);
        static::assertSame(201, $manager->getResponse()->getStatusCode(), $this->errors($manager));
        $manager->jsonRequest('POST', '/api/_action/workflow/draft/' . $draft . '/publish', ['expectedRevision' => 1, 'publisher' => 'core:approval']);
        static::assertSame(422, $manager->getResponse()->getStatusCode(), $this->errors($manager));
        static::assertStringContainsString('approval_assignee_unavailable', $this->errors($manager));
    }

    /**
     * @return array{TestBrowser, TestBrowser, TestBrowser, list<string>}
     */
    private function participants(): array
    {
        $manager = $this->createClient(permissions: ['workflow:read', 'workflow:create', 'workflow:update', 'workflow:publish', 'workflow_instance:access', 'workflow_instance:start', 'user:read', 'state_machine_state:read']);
        $approvers = [];
        $ids = [];
        for ($index = 0; $index < 2; ++$index) {
            $approvers[] = $this->createClient(permissions: ['workflow_instance:access', 'workflow_task:decide']);
            $id = Uuid::fromBytesToHex(static::getContainer()->get(Connection::class)->fetchOne('SELECT id FROM user WHERE username = :username', ['username' => $this->apiUsernames[array_key_last($this->apiUsernames) ?? 0]]));
            static::getContainer()->get('user.repository')->update([['id' => $id, 'email' => $id . '@example.com']], Context::createDefaultContext());
            $ids[] = $id;
        }

        return [$manager, $approvers[0], $approvers[1], $ids];
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, mixed>
     */
    private function document(array $ids): array
    {
        $raw = (string) file_get_contents(\dirname(__DIR__, 5) . '/unit/Core/Framework/Workflow/Publication/Approval/_fixtures/expense-approval-request.json');

        return json_decode(str_replace(['0123456789abcdef0123456789abcdef', 'abcdef0123456789abcdef0123456789'], $ids, $raw), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return array{string, string}
     */
    private function publish(TestBrowser $manager, array $document): array
    {
        $draft = Uuid::randomHex();
        $manager->jsonRequest('POST', '/api/_action/workflow/draft', ['id' => $draft, 'name' => 'Expense approval', 'document' => $document]);
        static::assertSame(201, $manager->getResponse()->getStatusCode(), $this->errors($manager));
        $manager->jsonRequest('POST', '/api/_action/workflow/draft/' . $draft . '/publish', ['expectedRevision' => 1, 'publisher' => 'core:approval']);
        static::assertSame(201, $manager->getResponse()->getStatusCode(), $this->errors($manager));

        return [$draft, $this->response($manager)['id']];
    }

    private function start(TestBrowser $manager, string $release, string $amount): string
    {
        $manager->jsonRequest('POST', '/api/_action/workflow/instance', ['definitionId' => $release, 'formData' => $this->form($amount)]);
        static::assertSame(201, $manager->getResponse()->getStatusCode(), $this->errors($manager));

        return $this->response($manager)['data']['id'];
    }

    /**
     * @return array<string, string>
     */
    private function form(string $amount): array
    {
        return ['amount' => $amount, 'reason' => 'Business travel', 'expenseDate' => '2026-11-13', 'category' => 'travel'];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(TestBrowser $manager, string $instance): array
    {
        $manager->request('GET', '/api/_action/workflow/instance/' . $instance);
        static::assertSame(200, $manager->getResponse()->getStatusCode(), $this->errors($manager));

        return $this->response($manager)['data'];
    }

    private function assertNoTodos(TestBrowser $browser): void
    {
        $browser->request('GET', '/api/_action/workflow/task/todo');
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $this->errors($browser));
        static::assertSame([], $this->response($browser)['data']);
    }

    private function errors(TestBrowser $browser): string
    {
        return json_encode(json_decode((string) $browser->getResponse()->getContent(), true)['errors'] ?? [], \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function response(TestBrowser $browser): array
    {
        return json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
