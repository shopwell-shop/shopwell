<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Transfer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Draft\WorkflowDraftStore;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowReleaseStore;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\System\Payment\Api\Struct\PaymentTransferDraft;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalException;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Tests\Integration\Core\System\Payment\Transfer\_fixtures\PaymentTransferApprovalFixture;

/**
 * @internal
 */
#[Package('framework')]
class PaymentTransferReapplicationTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private Context $context;

    private TestUser $user;

    private PaymentTransferApprovalService $approvals;

    private PaymentTransferEntity $original;

    private string $draftId;

    private string $document;

    private mixed $previousRelease;

    private mixed $avatar;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->connection = $container->get(Connection::class);
        $this->user = TestUser::createNewTestUser($this->connection, ['workflow_instance:access', 'workflow_task:decide']);
        $this->avatar = $this->connection->fetchOne('SELECT avatar_id FROM user WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->user->getUserId())]);
        $source = new AdminApiSource($this->user->getUserId());
        $source->setPermissions(['workflow:read', 'workflow:publish', 'workflow_instance:access', 'workflow_task:decide', 'payment_transfer:create']);
        $this->context = new Context($source);
        $this->approvals = $container->get(PaymentTransferApprovalService::class);
        $this->previousRelease = $container->get(SystemConfigService::class)->get(PaymentTransferApprovalService::CONFIG_KEY);
        $this->draftId = Uuid::randomHex();
        $path = \dirname(__DIR__, 5) . '/unit/Core/Framework/Workflow/Publication/Approval/_fixtures/default-approval-request.json';
        $raw = json_decode(str_replace('0123456789abcdef0123456789abcdef', $this->user->getUserId(), (string) file_get_contents($path)), true, flags: \JSON_THROW_ON_ERROR);
        $raw['nodes'][0]['config']['formFields'] = [
            ['key' => 'amount', 'type' => 'number', 'label' => 'Amount', 'required' => true],
            ['key' => 'currency', 'type' => 'text', 'label' => 'Currency', 'required' => true],
            ['key' => 'recipient', 'type' => 'text', 'label' => 'Recipient', 'required' => true],
            ['key' => 'paymentAccount', 'type' => 'text', 'label' => 'Payment account', 'required' => true],
            ['key' => 'reason', 'type' => 'text', 'label' => 'Reason', 'required' => false],
        ];
        $this->document = json_encode($raw, \JSON_THROW_ON_ERROR);
        $container->get(WorkflowDraftStore::class)->create($this->draftId, 'Reapplication test', $this->document, $this->context);
        $release = $container->get(WorkflowReleaseStore::class)->publish($this->draftId, 1, 'core:approval', $this->context);
        $this->approvals->configure($release->getId(), $this->context);
        $fixture = PaymentTransferApprovalFixture::create($container, credentials: $container->get(PaymentCredentialCipher::class)->encrypt(['app_id' => 'reapplication-test-' . $this->draftId]));
        $this->original = $fixture;
        $container->get('payment_app.repository')->update([['id' => $fixture->paymentAppId,
            'accounts' => [['id' => Uuid::randomHex(), 'accountId' => $fixture->accountId, 'enabled' => true, 'position' => 0]],
            'channels' => [['id' => Uuid::randomHex(), 'channelTechnicalName' => 'alipay_h5', 'enabled' => true]],
        ]], Context::createDefaultContext());
        $this->original = $this->approvals->book(new PaymentTransferDraft(paymentAppId: $fixture->paymentAppId, provider: $fixture->provider, channel: $fixture->channel, recipientId: $fixture->recipientId, amount: 100, currency: 'CNY', outNo: substr(Uuid::randomHex(), 0, 30), accountId: $fixture->accountId), $this->context);
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();
        $container->get(SystemConfigService::class)->set(PaymentTransferApprovalService::CONFIG_KEY, $this->previousRelease);
        $appId = Uuid::fromHexToBytes($this->original->paymentAppId);
        $bindings = $this->connection->fetchAllAssociative('SELECT a.id, a.instance_id FROM payment_transfer_approval a INNER JOIN payment_transfer t ON a.id = t.id WHERE t.payment_app_id = :id', ['id' => $appId]);
        foreach ($bindings as $binding) {
            $this->connection->executeStatement('DELETE h FROM state_machine_history h INNER JOIN workflow_task t ON h.referenced_id = t.id WHERE t.instance_id = :id', ['id' => $binding['instance_id']]);
            $this->connection->delete('state_machine_history', ['entity_name' => 'workflow_instance', 'referenced_id' => $binding['instance_id']]);
            $this->connection->delete('workflow_instance', ['id' => $binding['instance_id']]);
        }
        $this->connection->executeStatement('DELETE h FROM state_machine_history h INNER JOIN payment_transfer t ON h.referenced_id = t.id WHERE t.payment_app_id = :id', ['id' => $appId]);
        $this->connection->delete('payment_transfer', ['payment_app_id' => $appId]);
        $this->connection->delete('payment_transfer_recipient', ['payment_app_id' => $appId]);
        $this->connection->delete('payment_app', ['id' => $appId]);
        $this->connection->delete('payment_account', ['id' => Uuid::fromHexToBytes($this->original->accountId)]);
        foreach ($this->connection->fetchFirstColumn('SELECT id FROM workflow_release WHERE draft_id = :id', ['id' => Uuid::fromHexToBytes($this->draftId)]) as $id) {
            $this->connection->delete('workflow_execution_definition', ['id' => $id]);
            $this->connection->delete('workflow_release', ['id' => $id]);
        }
        $this->connection->delete('workflow_draft', ['id' => Uuid::fromHexToBytes($this->draftId)]);
        $this->connection->delete('user', ['id' => Uuid::fromHexToBytes($this->user->getUserId())]);
        $this->connection->delete('media', ['id' => $this->avatar]);
        if ($this->user->getAclRoleId() !== null) {
            $this->connection->delete('acl_role', ['id' => Uuid::fromHexToBytes($this->user->getAclRoleId())]);
        }
        parent::tearDown();
    }

    #[DataProvider('terminalStates')]
    public function testFreshRequestUsesTheCurrentReleaseAndPreservesOriginalAudit(string $terminal): void
    {
        $old = $this->approvals->status($this->original->id, $this->context);
        $this->refuse($terminal);
        static::assertTrue($this->approvals->status($this->original->id, $this->context)['canReapply']);
        $container = static::getContainer();
        $container->get(WorkflowDraftStore::class)->update($this->draftId, 'Revised workflow', $this->document, 1, $this->context);
        $current = $container->get(WorkflowReleaseStore::class)->publish($this->draftId, 2, 'core:approval', $this->context);
        $this->approvals->configure($current->getId(), $this->context);
        $draft = $this->draft();
        $new = $this->approvals->reapply($this->original->id, $draft, $this->context);
        $status = $this->approvals->status($new->id, $this->context);
        static::assertNotSame($this->original->id, $new->id);
        static::assertNotSame($this->original->transferNo, $new->transferNo);
        static::assertNotSame($old['instanceId'], $status['instanceId']);
        static::assertSame($current->getId(), $status['releaseId']);
        static::assertSame(['id' => $this->original->id, 'transferNo' => $this->original->transferNo], $status['previousTransfer']);
        static::assertSame(250, $new->amount);
        static::assertSame($new->id, $this->approvals->reapply($this->original->id, $draft, $this->context)->id);
        $preserved = $this->approvals->status($this->original->id, $this->context);
        static::assertSame($terminal, $preserved['status']);
        static::assertSame($old['instanceId'], $preserved['instanceId']);
        static::assertSame($old['releaseId'], $preserved['releaseId']);
        static::assertSame(0, $container->get(PaymentTransferService::class)->getById($this->original->id)->submitAttempts);
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked($terminal));
        $this->approvals->assertApproved($this->original);
    }

    public function testOriginalOutNumberCannotBeReused(): void
    {
        $this->refuse('rejected');
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('reapplication_requires_new_transfer'));
        $this->approvals->reapply($this->original->id, $this->draft($this->original->outNo), $this->context);
    }

    public function testStartedTransferCannotBeReappliedEvenWithARejectedWorkflow(): void
    {
        $this->refuse('rejected');
        $this->connection->update('payment_transfer', ['submit_attempts' => 1], ['id' => Uuid::fromHexToBytes($this->original->id)]);
        static::assertFalse($this->approvals->status($this->original->id, $this->context)['canReapply']);
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('reapplication_unavailable'));
        $this->approvals->reapply($this->original->id, $this->draft(), $this->context);
    }

    public static function terminalStates(): \Generator
    {
        yield 'rejected' => ['rejected'];
        yield 'withdrawn' => ['canceled'];
    }

    private function refuse(string $terminal): void
    {
        $instanceId = $this->approvals->status($this->original->id, $this->context)['instanceId'];
        if ($terminal === 'canceled') {
            $this->approvals->cancel($this->original->id, 'Correct the amount', $this->context);

            return;
        }
        $task = static::getContainer()->get(WorkflowTaskFinder::class)->forInstance($instanceId, $this->context)->first();
        static::assertNotNull($task);
        static::getContainer()->get(ApprovalWorkflowService::class)->reject($task->id, 'Invoice missing', $this->context);
    }

    private function draft(?string $outNo = null): PaymentTransferDraft
    {
        return new PaymentTransferDraft(paymentAppId: $this->original->paymentAppId, provider: $this->original->provider, channel: $this->original->channel, recipientId: $this->original->recipientId, amount: 250, currency: 'CNY', outNo: $outNo ?? substr(Uuid::randomHex(), 0, 30), accountId: $this->original->accountId);
    }
}
