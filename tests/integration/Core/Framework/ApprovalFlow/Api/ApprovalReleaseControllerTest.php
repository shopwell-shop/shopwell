<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
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

        $credentials = $container->get(PaymentCredentialCipher::class)->encrypt(['app_id' => 'test-transfer-approval']);
        $fixture = PaymentTransferApprovalFixture::create($container, $credentials);
        $container->get('payment_app_account.repository')->create([[
            'paymentAppId' => $fixture->paymentAppId, 'accountId' => $fixture->accountId, 'enabled' => true, 'position' => 0,
        ]], $context);
        $container->get('payment_app_channel.repository')->create([[
            'paymentAppId' => $fixture->paymentAppId, 'channelTechnicalName' => $fixture->channel, 'enabled' => true,
        ]], $context);
        $payload = [
            'paymentAppId' => $fixture->paymentAppId, 'accountId' => $fixture->accountId, 'recipientId' => $fixture->recipientId,
            'provider' => $fixture->provider, 'channel' => $fixture->channel, 'amount' => 1200, 'currency' => 'CNY',
            'outNo' => substr(Uuid::randomHex(), 0, 30),
        ];
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
        $review->jsonRequest('POST', '/api/_action/approval-flow/instances/' . $firstTransfer['instanceId'] . '/tasks/' . Uuid::fromBytesToHex($task['id']) . '/decide', [
            'commandId' => Uuid::randomHex(), 'expectedVersion' => 2, 'outcome' => 'approved',
        ]);
        static::assertSame(200, $review->getResponse()->getStatusCode(), (string) $review->getResponse()->getContent());
        $container->get(ApprovalInstanceService::class)->progress($firstTransfer['instanceId'], Uuid::fromBytesToHex($task['execution_id']), Uuid::randomHex(), 3, $context);

        $result = $container->get('approval_flow_result.repository')->search((new Criteria())->addFilter(new EqualsFilter('instanceId', $firstTransfer['instanceId'])), $context)->first();
        static::assertInstanceOf(ApprovalResultEntity::class, $result);
        static::assertSame($first, $result->getReleaseId());
        $request = $container->get(ApprovalRequestStore::class)->read($firstTransfer['detailRoute']['params']['id']);
        $container->get(PaymentTransferApprovalResultHandler::class)->handle(
            new ApprovalResult($result->getId(), $result->getInstanceId(), $result->getReleaseId(), $result->getCommandId(), $result->getAggregateVersion(), $result->getOutcome(), $result->getFinishedAt()),
            $request,
            $context,
        );
        $publisher->request('GET', '/api/_action/payment/transfers/' . $request->getBusinessId() . '/approval');
        $approved = json_decode((string) $publisher->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame('approved', $approved['status']);
        static::assertSame($first, $approved['releaseId']);
        static::assertSame(0, $container->get(PaymentTransferService::class)->getById($request->getBusinessId())->submitAttempts);
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

        return json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
