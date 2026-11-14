<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Transfer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Draft\WorkflowDraftStore;
use Shopwell\Core\Framework\Workflow\Execution\ApprovalWorkflowRuntime;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowPublisherRegistry;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowReleaseStore;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimeRegistry;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Shopwell\Core\System\Payment\Api\Struct\PaymentTransferDraft;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Exception\PaymentTransferException;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalException;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferResultBooker;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\SystemConfig\SystemConfigService;

/**
 * Real core publication, booking and approval with committed fixtures.
 * Provider execution is covered separately with a fake gateway; this test only claims its first attempt.
 *
 * @internal
 */
#[Package('framework')]
class PaymentTransferCoreWorkflowTest extends TestCase
{
    use KernelTestBehaviour;

    public function testCorePublisherAndRuntimeApproveATransferWithoutPluginServices(): void
    {
        static::assertArrayNotHasKey('SwagOa', static::getKernel()->getBundles());
        $container = static::getContainer();
        $connection = $container->get(Connection::class);
        static::assertSame(0, $connection->getTransactionNestingLevel());
        static::assertTrue($container->get(WorkflowRuntimeRegistry::class)->has('core:approval'));
        static::assertInstanceOf(ApprovalWorkflowRuntime::class, $container->get(WorkflowRuntimeRegistry::class)->get('core:approval'));
        static::assertSame('core:approval', $container->get(WorkflowPublisherRegistry::class)->get('core:approval')->name());
        $user = TestUser::createNewTestUser($connection, ['workflow_instance:access', 'workflow_task:decide']);
        $avatar = $connection->fetchOne('SELECT avatar_id FROM user WHERE id = :id', ['id' => Uuid::fromHexToBytes($user->getUserId())]);
        $source = new AdminApiSource($user->getUserId());
        $source->setPermissions(['workflow:read', 'workflow:publish', 'workflow_instance:access', 'workflow_task:decide', 'payment_transfer:create']);
        $context = new Context($source);
        $system = Context::createDefaultContext();
        $config = $container->get(SystemConfigService::class);
        $previousRelease = $config->get(PaymentTransferApprovalService::CONFIG_KEY);
        $draftId = Uuid::randomHex();
        $accountId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $recipientId = Uuid::randomHex();
        $releaseId = null;
        $transferId = null;
        $instanceId = null;
        try {
            $container->get(WorkflowDraftStore::class)->create($draftId, 'Core finance approval', $this->document($user->getUserId()), $context);
            $release = $container->get(WorkflowReleaseStore::class)->publish($draftId, 1, 'core:approval', $context);
            $releaseId = $release->getId();
            $approval = $container->get(PaymentTransferApprovalService::class);
            $approval->configure($releaseId, $context);
            static::assertTrue($approval->configuration($context)['available']);
            $container->get('user.repository')->update([['id' => $user->getUserId(), 'active' => false]], $system);
            $unavailable = $approval->configuration($context);
            static::assertFalse($unavailable['available']);
            static::assertSame('approval_assignee_unavailable', $unavailable['reason']);
            try {
                $approval->configure($releaseId, $context);
                static::fail('An inactive reviewer must prevent binding.');
            } catch (WorkflowException $exception) {
                static::assertSame('approval_assignee_unavailable', $exception->getParameter('reason'));
            }
            $container->get('user.repository')->update([['id' => $user->getUserId(), 'active' => true]], $system);
            $cipher = $container->get(PaymentCredentialCipher::class);
            $container->get('payment_account.repository')->create([[
                'id' => $accountId, 'providerTechnicalName' => 'alipay', 'name' => 'Core test merchant', 'active' => true, 'enabledProducts' => ['alipay_h5'],
                'credentials' => $cipher->encrypt(['app_id' => 'core-finance-test']),
            ]], $system);
            $container->get('payment_app.repository')->create([[
                'id' => $appId, 'technicalName' => 'core_workflow_' . $appId, 'name' => 'Core finance app', 'type' => PaymentAppEntity::TYPE_EXTERNAL,
                'appSecret' => $cipher->encryptValue('test-secret'), 'active' => true,
                'accounts' => [['id' => Uuid::randomHex(), 'accountId' => $accountId, 'enabled' => true, 'position' => 0]],
                'channels' => [['id' => Uuid::randomHex(), 'channelTechnicalName' => 'alipay_h5', 'enabled' => true]],
            ]], $system);
            $container->get('payment_transfer_recipient.repository')->create([[
                'id' => $recipientId, 'paymentAppId' => $appId, 'provider' => 'alipay', 'identityType' => 'alipay_user_id',
                'identityValue' => $cipher->encryptValue('2088123412341234'), 'identityHash' => hash('sha256', $recipientId),
                'holderName' => 'Approved recipient', 'onboardingStatus' => 'registered', 'payoutEnabled' => true,
            ]], $system);
            $draft = new PaymentTransferDraft(paymentAppId: $appId, provider: 'alipay', channel: 'alipay_h5', recipientId: $recipientId, amount: 1200, currency: 'CNY', outNo: substr(Uuid::randomHex(), 0, 30), note: 'Core publication payment', accountId: $accountId);
            $counts = [];
            foreach (['payment_transfer', 'payment_transfer_recipient', 'workflow_instance', 'workflow_task'] as $table) {
                $counts[$table] = $connection->fetchOne('SELECT COUNT(*) FROM ' . $table);
            }
            $preview = $approval->preview($draft, $context);
            static::assertTrue($preview['available'], json_encode($preview, \JSON_THROW_ON_ERROR));
            static::assertSame($releaseId, $preview['releaseId']);
            static::assertSame(['approval'], array_column($preview['steps'], 'nodeId'));
            static::assertSame($user->getUserId(), $preview['steps'][0]['assignees'][0]['id']);
            $newRecipientPreview = $approval->preview(new PaymentTransferDraft(paymentAppId: $appId, provider: 'alipay', channel: 'alipay_h5', identityType: 'alipay_logon_id', identityValue: 'preview-only@example.invalid', holderName: 'Preview only', amount: 1200, currency: 'CNY', accountId: $accountId), $context);
            static::assertTrue($newRecipientPreview['available']);
            foreach ($counts as $table => $count) {
                static::assertSame($count, $connection->fetchOne('SELECT COUNT(*) FROM ' . $table), 'Preview must not write ' . $table);
            }
            $wrongAccount = $approval->preview(new PaymentTransferDraft(paymentAppId: $appId, provider: 'alipay', channel: 'alipay_h5', recipientId: $recipientId, amount: 1200, currency: 'CNY', accountId: Uuid::randomHex()), $context);
            static::assertFalse($wrongAccount['available']);
            static::assertSame('payment_account_unavailable', $wrongAccount['reason']);
            $container->get('user.repository')->update([['id' => $user->getUserId(), 'active' => false]], $system);
            $blockedPreview = $approval->preview($draft, $context);
            static::assertFalse($blockedPreview['available']);
            static::assertSame('approval_assignee_unavailable', $blockedPreview['reason']);
            $container->get('user.repository')->update([['id' => $user->getUserId(), 'active' => true]], $system);
            $transfer = $approval->book($draft, $context);
            $transferId = $transfer->id;
            $status = $approval->status($transferId, $context);
            $instanceId = $status['instanceId'];
            static::assertIsString($instanceId);
            static::assertSame('pending', $status['status']);
            $summaries = $approval->summaries([$transferId], $context);
            static::assertSame('pending', $summaries[$transferId]['status']);
            static::assertSame($user->getUserId(), $summaries[$transferId]['currentAssignees'][0]['id']);
            static::assertSame(['name' => 'sw.workflow.instance', 'params' => ['id' => $instanceId]], $summaries[$transferId]['detailRoute']);
            static::assertSame($releaseId, $status['releaseId']);
            static::assertSame('Core test merchant', $status['paymentAccountLabel']);
            static::assertStringContainsString('Approved recipient', $status['recipientLabel']);
            static::assertStringNotContainsString('2088123412341234', $status['recipientLabel']);
            static::assertSame($transferId, $approval->book($draft, $context)->id);
            static::assertSame($instanceId, $approval->status($transferId, $context)['instanceId']);
            try {
                $approval->assertApproved($transfer);
                static::fail('A pending workflow must block execution.');
            } catch (PaymentTransferApprovalException $exception) {
                static::assertSame('pending', $exception->getParameter('reason'));
            }
            $tasks = $container->get(WorkflowTaskFinder::class)->forInstance($instanceId, $context);
            static::assertCount(1, $tasks);
            $task = $tasks->first();
            static::assertNotNull($task);
            $container->get(ApprovalWorkflowService::class)->approve($task->id, 'Approved using core workflow', $context);
            static::assertSame('approved', $approval->status($transferId, $context)['status']);
            $completed = $approval->summaries([$transferId], $context);
            static::assertSame('approved', $completed[$transferId]['status']);
            static::assertSame([], $completed[$transferId]['currentAssignees']);
            static::assertSame('2088123412341234', $cipher->decryptValue($approval->assertApproved($transfer)->identityValue));
            static::assertSame(0, $container->get(PaymentTransferService::class)->getById($transferId)->submitAttempts);
            $booker = $container->get(PaymentTransferResultBooker::class);
            $booker->claim($transfer);
            static::assertSame(1, $container->get(PaymentTransferService::class)->getById($transferId)->submitAttempts);
            $this->expectExceptionObject(PaymentTransferException::transferAlreadyFinalized($transfer->outNo, 'unknown'));
            $booker->claim($transfer);
        } finally {
            $config->set(PaymentTransferApprovalService::CONFIG_KEY, $previousRelease);
            if ($instanceId !== null) {
                $binaryId = Uuid::fromHexToBytes($instanceId);
                $connection->executeStatement('DELETE history FROM state_machine_history history INNER JOIN workflow_task task ON history.referenced_id = task.id WHERE task.instance_id = :id', ['id' => $binaryId]);
                $connection->delete('state_machine_history', ['entity_name' => 'workflow_instance', 'referenced_id' => $binaryId]);
                $connection->delete('workflow_instance', ['id' => $binaryId]);
            }
            if ($transferId !== null) {
                $connection->delete('payment_transfer_approval', ['id' => Uuid::fromHexToBytes($transferId)]);
                $connection->delete('payment_transfer', ['id' => Uuid::fromHexToBytes($transferId)]);
            }
            $connection->delete('payment_transfer_recipient', ['id' => Uuid::fromHexToBytes($recipientId)]);
            $connection->delete('payment_app', ['id' => Uuid::fromHexToBytes($appId)]);
            $connection->delete('payment_account', ['id' => Uuid::fromHexToBytes($accountId)]);
            if ($releaseId !== null) {
                $connection->delete('workflow_execution_definition', ['id' => Uuid::fromHexToBytes($releaseId)]);
                $connection->delete('workflow_release', ['id' => Uuid::fromHexToBytes($releaseId)]);
            }
            $connection->delete('workflow_draft', ['id' => Uuid::fromHexToBytes($draftId)]);
            $connection->delete('user', ['id' => Uuid::fromHexToBytes($user->getUserId())]);
            $connection->delete('media', ['id' => $avatar]);
            if ($user->getAclRoleId() !== null) {
                $connection->delete('acl_role', ['id' => Uuid::fromHexToBytes($user->getAclRoleId())]);
            }
        }
    }

    private function document(string $assignee): string
    {
        $path = \dirname(__DIR__, 5) . '/unit/Core/Framework/Workflow/Publication/Approval/_fixtures/default-approval-request.json';
        $raw = json_decode(str_replace('0123456789abcdef0123456789abcdef', $assignee, (string) file_get_contents($path)), true, flags: \JSON_THROW_ON_ERROR);
        $raw['nodes'][0]['config']['formFields'] = [
            ['key' => 'amount', 'type' => 'number', 'label' => 'Amount', 'required' => true],
            ['key' => 'currency', 'type' => 'text', 'label' => 'Currency', 'required' => true],
            ['key' => 'recipient', 'type' => 'text', 'label' => 'Recipient', 'required' => true],
            ['key' => 'paymentAccount', 'type' => 'text', 'label' => 'Payment account', 'required' => true],
            ['key' => 'reason', 'type' => 'text', 'label' => 'Reason', 'required' => false],
        ];

        return json_encode($raw, \JSON_THROW_ON_ERROR);
    }
}
