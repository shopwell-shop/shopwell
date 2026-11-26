<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestEntity;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestStore;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResult;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalResultHandler;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Tests\Integration\Core\System\Payment\Transfer\_fixtures\PaymentTransferApprovalFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalReleaseControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use EnvTestBehaviour;

    private Filesystem $filesystem;

    private string $manifestDirectory;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->manifestDirectory = sys_get_temp_dir() . '/approval-release-api-' . bin2hex(random_bytes(8));
        $manifest = PublicationFixture::writeManifest($this->filesystem, $this->manifestDirectory);
        $this->setEnvVars(['APPROVAL_FLOW_ARTIFACT_MANIFEST' => $manifest]);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->manifestDirectory);
    }

    public function testPublishingUsesThePreviewedRevisionAndRequiresPublicationPermission(): void
    {
        $drafts = static::getContainer()->get(ApprovalDraftService::class);
        $context = Context::createDefaultContext();
        $draft = $drafts->create('Transfer approval', GraphFixture::json(), $context);
        $path = '/api/_action/approval-flow/design/drafts/' . $draft->definitionId;

        $editor = $this->getBrowser(permissions: ['approval_flow_draft:update']);
        $editor->jsonRequest('POST', $path . '/publish', ['expectedRevision' => 1, 'expectedHash' => str_repeat('a', 64)]);
        static::assertSame(403, $editor->getResponse()->getStatusCode());

        $this->resetBrowser();
        $publisher = $this->getBrowser(permissions: ['approval_flow_release:create']);
        $publisher->jsonRequest('POST', $path . '/publication-preview', ['expectedRevision' => 1]);
        static::assertSame(200, $publisher->getResponse()->getStatusCode(), (string) $publisher->getResponse()->getContent());
        $preview = json_decode((string) $publisher->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $payload = ['expectedRevision' => 1, 'expectedHash' => $preview['contentHash']];
        $publisher->jsonRequest('POST', $path . '/publish', $payload);
        static::assertSame(200, $publisher->getResponse()->getStatusCode(), (string) $publisher->getResponse()->getContent());
        $release = json_decode((string) $publisher->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame($draft->definitionId, $release['definitionId']);
        static::assertSame(1, $release['draftRevision']);
        static::assertSame($preview['contentHash'], $release['contentHash']);

        $drafts->save($draft->definitionId, 1, GraphFixture::json(), $context);
        $publisher->jsonRequest('POST', $path . '/publish', $payload);
        static::assertSame(409, $publisher->getResponse()->getStatusCode());
    }

    public function testActivationAtomicallySelectsAReleaseAndRejectsStaleState(): void
    {
        $draft = static::getContainer()->get(ApprovalDraftService::class)->create('Transfer approval', GraphFixture::json(), Context::createDefaultContext());
        $browser = $this->getBrowser(permissions: ['approval_flow_release:create', 'approval_flow_binding:activate', 'approval_flow_binding:create']);
        $path = '/api/_action/approval-flow/design/drafts/' . $draft->definitionId;
        $browser->jsonRequest('POST', $path . '/publication-preview', ['expectedRevision' => 1]);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $preview = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $browser->jsonRequest('POST', $path . '/publish', ['expectedRevision' => 1, 'expectedHash' => $preview['contentHash']]);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $release = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $path = '/api/_action/approval-flow/design/releases/' . $release['id'] . '/activate';
        $payload = ['businessType' => 'payment:transfer', 'businessVersion' => '1.0.0', 'expectedRevision' => 0, 'expectedVersion' => 0];
        $browser->jsonRequest('POST', $path, $payload);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertSame(['revision' => 1, 'version' => 1], json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR));

        $browser->jsonRequest('POST', $path, $payload);
        static::assertSame(409, $browser->getResponse()->getStatusCode());
    }

    public function testTransferOptionsUseTheApprovalFlowBinding(): void
    {
        $browser = $this->getBrowser(permissions: ['payment_transfer:create']);
        $browser->request('GET', '/api/_action/payment/transfers/options');

        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $options = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertFalse($options['approvalFlowActive']);
        static::assertArrayNotHasKey('workflowAvailable', $options);
    }

    public function testTransferBookingRollsBackWithoutAnActiveBinding(): void
    {
        $browser = $this->getBrowser(permissions: ['payment_transfer:create']);
        $payload = $this->transferPayload();
        $browser->jsonRequest('POST', '/api/_action/payment/transfers', $payload);

        static::assertSame(404, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT COUNT(*) FROM payment_transfer WHERE out_no = :outNo',
            ['outNo' => $payload['outNo']],
        ));
    }

    public function testTransferRequestsKeepTheirPublishedVersionAfterSwitching(): void
    {
        $container = static::getContainer();
        $connection = $container->get(Connection::class);
        $reviewer = TestUser::createNewTestUser($connection, [ApprovalInstanceService::DECIDE_PRIVILEGE, 'approval_flow:access']);
        $publisher = $this->getBrowser(permissions: [
            'approval_flow_release:create', 'approval_flow_binding:activate', 'approval_flow_binding:create',
            'payment_transfer:create', 'payment_transfer:read', 'approval_flow:access',
        ]);
        $context = Context::createDefaultContext();
        $drafts = $container->get(ApprovalDraftService::class);
        $graph = str_replace('finance-user', $reviewer->getUserId(), GraphFixture::json());
        $draft = $drafts->create('Transfer approval', $graph, $context);
        $first = $this->publishAndActivate($publisher, $draft->definitionId, 1);

        $payload = $this->transferPayload();
        $firstTransfer = $this->bookTransfer($publisher, $payload);
        static::assertSame($first, $firstTransfer['releaseId']);
        static::assertSame('pending', $firstTransfer['status']);

        $edited = GraphFixture::codec()->decodeWritable(new DraftGraph($graph));
        $edited->meta->name = 'Transfer approval v2';
        $drafts->save($draft->definitionId, 1, json_encode($edited, \JSON_THROW_ON_ERROR), $context);
        $second = $this->publishAndActivate($publisher, $draft->definitionId, 2);
        static::assertNotSame($first, $second);
        $payload['outNo'] = substr(Uuid::randomHex(), 0, 30);
        $secondTransfer = $this->bookTransfer($publisher, $payload);
        static::assertSame($second, $secondTransfer['releaseId']);

        $task = $connection->fetchAssociative(
            'SELECT t.id, t.execution_id FROM approval_flow_task t
             INNER JOIN approval_flow_execution e ON e.id = t.execution_id WHERE e.instance_id = :id',
            ['id' => Uuid::fromHexToBytes($firstTransfer['instanceId'])],
        );
        static::assertNotFalse($task);
        $this->resetBrowser();
        $review = $this->getBrowser(authorized: false);
        $reviewer->authorizeBrowser($review);
        $review->request('GET', '/api/_action/approval-flow/requests?view=todo');
        static::assertSame(200, $review->getResponse()->getStatusCode());
        $inbox = json_decode((string) $review->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertContains($firstTransfer['instanceId'], array_column($inbox['data'], 'instanceId'));
        $request = $this->decideAndDeliver($review, $firstTransfer, $task, 'approved');
        $publisher->request('GET', '/api/_action/payment/transfers/' . $request->getBusinessId() . '/approval');
        $approved = json_decode((string) $publisher->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame('approved', $approved['status']);
        static::assertSame($first, $approved['releaseId']);
        static::assertSame(0, $container->get(PaymentTransferService::class)->getById($request->getBusinessId())->submitAttempts);
    }

    #[DataProvider('reapplicationReasons')]
    public function testAnInitiatorCanReapplyWithTheActiveRelease(bool $withdraw): void
    {
        $container = static::getContainer();
        $connection = $container->get(Connection::class);
        $reviewer = TestUser::createNewTestUser($connection, [
            ApprovalInstanceService::DECIDE_PRIVILEGE, 'approval_flow:access', 'payment_transfer:create', 'payment_transfer:read',
        ]);
        $initiator = $this->getBrowser(permissions: [
            'approval_flow_release:create', 'approval_flow_binding:activate', 'approval_flow_binding:create',
            'payment_transfer:create', 'payment_transfer:read', 'approval_flow:access',
        ]);
        $context = Context::createDefaultContext();
        $drafts = $container->get(ApprovalDraftService::class);
        $graph = str_replace('finance-user', $reviewer->getUserId(), GraphFixture::json());
        $draft = $drafts->create('Transfer approval', $graph, $context);
        $firstRelease = $this->publishAndActivate($initiator, $draft->definitionId, 1);
        $payload = $this->transferPayload();
        $original = $this->bookTransfer($initiator, $payload);
        $path = '/api/_action/payment/transfers/' . $original['transferId'];
        $instanceId = Uuid::fromHexToBytes($original['instanceId']);
        $task = $connection->fetchAssociative(
            'SELECT t.id, t.execution_id FROM approval_flow_task t
             INNER JOIN approval_flow_execution e ON e.id = t.execution_id WHERE e.instance_id = :id',
            ['id' => $instanceId],
        );
        static::assertNotFalse($task);

        $this->resetBrowser();
        $review = $this->getBrowser(authorized: false);
        $reviewer->authorizeBrowser($review);
        if ($withdraw) {
            $review->jsonRequest('POST', $path . '/approval/cancel', ['reason' => 'Wrong amount']);
            static::assertSame(403, $review->getResponse()->getStatusCode());
            $initiator->jsonRequest('POST', $path . '/approval/cancel', ['reason' => 'Wrong amount']);
            static::assertSame(200, $initiator->getResponse()->getStatusCode(), (string) $initiator->getResponse()->getContent());
            static::assertSame('withdrawn', $connection->fetchOne('SELECT status FROM approval_flow_instance WHERE id = :id', ['id' => $instanceId]));
            static::assertSame('cancelled', $connection->fetchOne('SELECT status FROM approval_flow_task WHERE id = :id', ['id' => $task['id']]));
            static::assertSame('Wrong amount', $connection->fetchOne('SELECT reason FROM approval_flow_operation WHERE instance_id = :id AND operation = :operation', ['id' => $instanceId, 'operation' => 'withdraw']));
            static::assertFalse($connection->fetchOne('SELECT id FROM approval_flow_result WHERE instance_id = :id', ['id' => $instanceId]));
            $initiator->jsonRequest('POST', $path . '/cancel', ['reason' => 'Wrong amount']);
            static::assertSame(200, $initiator->getResponse()->getStatusCode());
            $review->request('GET', '/api/_action/approval-flow/requests?view=todo');
            $inbox = json_decode((string) $review->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
            static::assertNotContains($original['instanceId'], array_column($inbox['data'], 'instanceId'));
        } else {
            $this->decideAndDeliver($review, $original, $task, 'rejected');
        }

        $edited = GraphFixture::codec()->decodeWritable(new DraftGraph($graph));
        $edited->meta->name = 'Transfer approval v2';
        $drafts->save($draft->definitionId, 1, json_encode($edited, \JSON_THROW_ON_ERROR), $context);
        $activeRelease = $this->publishAndActivate($initiator, $draft->definitionId, 2);
        $initiator->request('GET', $path . '/approval');
        $approval = json_decode((string) $initiator->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame($withdraw ? 'canceled' : 'rejected', $approval['status']);
        static::assertSame($firstRelease, $approval['releaseId']);
        static::assertTrue($approval['canReapply']);

        $payload['outNo'] = substr(Uuid::randomHex(), 0, 30);
        $unrelated = $this->bookTransfer($initiator, $payload);
        $initiator->jsonRequest('POST', $path . '/reapply', $payload);
        static::assertSame(409, $initiator->getResponse()->getStatusCode());

        $payload['outNo'] = substr(Uuid::randomHex(), 0, 30);
        $payload['amount'] = 1000;
        $review->jsonRequest('POST', $path . '/reapply', $payload);
        static::assertSame(409, $review->getResponse()->getStatusCode());

        $initiator->jsonRequest('POST', $path . '/reapply', $payload);
        static::assertSame(201, $initiator->getResponse()->getStatusCode(), (string) $initiator->getResponse()->getContent());
        $replacement = json_decode((string) $initiator->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['transfer'];
        static::assertNotContains($replacement['id'], [$original['transferId'], $unrelated['transferId']]);

        $initiator->jsonRequest('POST', $path . '/reapply', $payload);
        static::assertSame(200, $initiator->getResponse()->getStatusCode(), (string) $initiator->getResponse()->getContent());
        $replayed = json_decode((string) $initiator->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame($replacement['id'], $replayed['transfer']['id']);

        $initiator->request('GET', '/api/_action/payment/transfers/' . $replacement['id'] . '/approval');
        $approval = json_decode((string) $initiator->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame($activeRelease, $approval['releaseId']);
        static::assertSame('pending', $approval['status']);
        static::assertTrue($approval['canCancel']);
        static::assertSame(['id' => $original['transferId'], 'transferNo' => $original['transferNo']], $approval['previousTransfer']);
        static::assertSame(0, $container->get(PaymentTransferService::class)->getById($original['transferId'])->submitAttempts);
    }

    public static function reapplicationReasons(): iterable
    {
        yield 'withdrawn by the owner' => [true];
        yield 'rejected by the reviewer' => [false];
    }

    /**
     * @param array<string, mixed> $transfer
     * @param array<string, mixed> $task
     */
    private function decideAndDeliver(TestBrowser $reviewer, array $transfer, array $task, string $outcome): ApprovalRequestEntity
    {
        $container = static::getContainer();
        $context = Context::createDefaultContext();
        $instanceId = $transfer['instanceId'];
        $reviewer->jsonRequest('POST', '/api/_action/approval-flow/instances/' . $instanceId . '/tasks/' . Uuid::fromBytesToHex($task['id']) . '/decide', [
            'commandId' => Uuid::randomHex(), 'expectedVersion' => 2, 'outcome' => $outcome,
        ]);
        static::assertSame(200, $reviewer->getResponse()->getStatusCode(), (string) $reviewer->getResponse()->getContent());
        $container->get(ApprovalInstanceService::class)->progress($instanceId, Uuid::fromBytesToHex($task['execution_id']), Uuid::randomHex(), 3, $context);

        $result = $container->get('approval_flow_result.repository')->search((new Criteria())->addFilter(new EqualsFilter('instanceId', $instanceId)), $context)->first();
        static::assertInstanceOf(ApprovalResultEntity::class, $result);
        static::assertSame($transfer['releaseId'], $result->getReleaseId());
        $request = $container->get(ApprovalRequestStore::class)->read($transfer['detailRoute']['params']['id']);
        $container->get(PaymentTransferApprovalResultHandler::class)->handle(
            new ApprovalResult($result->getId(), $instanceId, $result->getReleaseId(), $result->getCommandId(), $result->getAggregateVersion(), $result->getOutcome(), $result->getFinishedAt()),
            $request,
            $context,
        );

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function transferPayload(): array
    {
        $container = static::getContainer();
        $context = Context::createDefaultContext();
        $credentials = $container->get(PaymentCredentialCipher::class)->encrypt(['app_id' => 'test-transfer-approval']);
        $fixture = PaymentTransferApprovalFixture::create($container, $credentials);
        $container->get('payment_app_account.repository')->create([[
            'paymentAppId' => $fixture->paymentAppId, 'accountId' => $fixture->accountId, 'enabled' => true, 'position' => 0,
        ]], $context);
        $container->get('payment_app_channel.repository')->create([[
            'paymentAppId' => $fixture->paymentAppId, 'channelTechnicalName' => $fixture->channel, 'enabled' => true,
        ]], $context);

        return [
            'paymentAppId' => $fixture->paymentAppId, 'accountId' => $fixture->accountId, 'recipientId' => $fixture->recipientId,
            'provider' => $fixture->provider, 'channel' => $fixture->channel, 'amount' => 1200, 'currency' => 'CNY',
            'outNo' => substr(Uuid::randomHex(), 0, 30),
        ];
    }

    private function publishAndActivate(TestBrowser $browser, string $definitionId, int $revision): string
    {
        $path = '/api/_action/approval-flow/design/drafts/' . $definitionId;
        $browser->jsonRequest('POST', $path . '/publication-preview', ['expectedRevision' => $revision]);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $preview = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $browser->jsonRequest('POST', $path . '/publish', ['expectedRevision' => $revision, 'expectedHash' => $preview['contentHash']]);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $release = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $browser->jsonRequest('POST', '/api/_action/approval-flow/design/releases/' . $release['id'] . '/activate', [
            'businessType' => 'payment:transfer', 'businessVersion' => '1.0.0',
            'expectedRevision' => $revision - 1, 'expectedVersion' => $revision - 1,
        ]);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        return $release['id'];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function bookTransfer(TestBrowser $browser, array $payload): array
    {
        $browser->jsonRequest('POST', '/api/_action/payment/transfers', $payload);
        static::assertSame(201, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $transfer = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $browser->request('GET', '/api/_action/payment/transfers/' . $transfer['transfer']['id'] . '/approval');
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        return json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR) + [
            'transferId' => $transfer['transfer']['id'],
            'transferNo' => $transfer['transfer']['transferNo'],
        ];
    }
}
