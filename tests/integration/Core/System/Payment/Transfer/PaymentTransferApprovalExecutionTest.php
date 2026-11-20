<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Transfer;

use Doctrine\DBAL\Connection;
use Payum\Core\GatewayInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestEntity;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestService;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResult;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Exception\PaymentTransferException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\PaymentTransferRequest;
use Shopwell\Core\System\Payment\Gateway\Request\TransferResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Transfer\Api\PaymentTransferPresenter;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalException;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalRequestService;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalRequestStore;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalResultHandler;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalService;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalStore;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferResultBooker;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferSubmitter;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\ApprovalRuntimeFixture;
use Shopwell\Tests\Integration\Core\System\Payment\Transfer\_fixtures\PaymentTransferApprovalFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class PaymentTransferApprovalExecutionTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private Filesystem $filesystem;

    private string $directory;

    private ContainerBuilder $runtime;

    private string $reviewerId;

    private PaymentTransferEntity $transfer;

    private ApprovalRequestEntity $request;

    protected function setUp(): void
    {
        $kernel = static::getContainer();
        $this->connection = $kernel->get(Connection::class);
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/transfer-execution-' . Uuid::randomHex();
        $this->runtime = ApprovalRuntimeFixture::container($kernel, PublicationFixture::writeManifest($this->filesystem, $this->directory));
        $this->reviewerId = Uuid::randomHex();
        $locale = $this->connection->fetchOne('SELECT id FROM locale LIMIT 1');
        static::assertIsString($locale);
        $kernel->get('user.repository')->create([[
            'id' => $this->reviewerId, 'username' => $this->reviewerId, 'name' => 'Transfer reviewer',
            'email' => $this->reviewerId . '@example.test', 'password' => 'shopwell', 'active' => true,
            'localeId' => Uuid::fromBytesToHex($locale),
        ]], Context::createDefaultContext());
        $releaseId = ApprovalRuntimeFixture::publish($kernel, $this->runtime, str_replace('finance-user', $this->reviewerId, GraphFixture::json()));
        $bindings = $this->runtime->get(ApprovalBindingService::class);
        $bindings->save('payment:transfer', '1.0.0', $releaseId, 0, Context::createDefaultContext());
        $bindings->activate('payment:transfer', 1, 0, Context::createDefaultContext());
        $this->transfer = PaymentTransferApprovalFixture::create($kernel);
        $source = new AdminApiSource($this->reviewerId);
        $source->setPermissions(['approval_flow_request:create', 'approval_flow_instance:create']);
        $service = new PaymentTransferApprovalRequestService(
            $kernel->get(PaymentTransferApprovalRequestStore::class),
            $this->runtime->get(ApprovalRequestService::class),
            $kernel->get(PaymentTransferApprovalStore::class),
            $kernel->get(PaymentTransferPresenter::class),
        );
        $this->request = $service->submit($this->transfer->id, Context::createDefaultContext($source));
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testAnAppliedApprovalAuthorizesTheOriginalTransfer(): void
    {
        $result = $this->complete(ApprovalOutcome::Approved);
        static::getContainer()->get(PaymentTransferApprovalResultHandler::class)->handle($result, $this->request, Context::createDefaultContext());

        $recipient = static::getContainer()->get(PaymentTransferApprovalService::class)->assertApproved($this->transfer);
        static::assertSame($this->transfer->recipientId, $recipient->id);
        static::assertNotNull($this->transfer->recipient);
        static::assertSame($this->transfer->recipient->identityValue, $recipient->identityValue);
        static::getContainer()->get(PaymentTransferResultBooker::class)->claim($this->transfer);

        static::assertSame(1, static::getContainer()->get(PaymentTransferService::class)->getById($this->transfer->id)->submitAttempts);
    }

    public function testThePayoutUsesTheApprovedRecipientAndCannotBeSubmittedTwice(): void
    {
        $result = $this->complete(ApprovalOutcome::Approved);
        static::getContainer()->get(PaymentTransferApprovalResultHandler::class)->handle($result, $this->request, Context::createDefaultContext());
        $cipher = static::getContainer()->get(PaymentCredentialCipher::class);
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->once())->method('execute')->willReturnCallback(static function (PaymentTransferRequest $request) use ($cipher): void {
            static::assertSame(100, $request->transfer->amount);
            static::assertSame('CNY', $request->transfer->currency);
            static::assertSame('recipient', $cipher->decryptValue($request->recipient->identityValue));
            $model = $request->getModel();
            static::assertInstanceOf(PaymentGatewayModel::class, $model);
            $model->offsetSet(PaymentGatewayModel::RESULT, new TransferResult(TransferResult::STATE_UNKNOWN));
        });
        $app = new PaymentAppEntity();
        $app->id = $this->transfer->paymentAppId;
        $submitter = $this->submitter($gateway);

        $submitter->submit($app, $this->transfer);

        static::assertSame(1, static::getContainer()->get(PaymentTransferService::class)->getById($this->transfer->id)->submitAttempts);
        $this->expectExceptionObject(PaymentTransferException::transferAlreadyFinalized($this->transfer->outNo, 'unknown'));
        $submitter->submit($app, $this->transfer);
    }

    public function testPendingApprovalDoesNotClaimAnAttempt(): void
    {
        $this->assertClaimBlocked('pending');
    }

    public function testACompletedApprovalWaitsForBusinessDelivery(): void
    {
        $this->complete(ApprovalOutcome::Approved);

        $this->assertClaimBlocked('pending');
    }

    public function testARejectedBusinessDecisionDoesNotClaimAnAttempt(): void
    {
        $result = $this->complete(ApprovalOutcome::Rejected);
        static::getContainer()->get(PaymentTransferApprovalResultHandler::class)->handle($result, $this->request, Context::createDefaultContext());

        $this->assertClaimBlocked('rejected');
    }

    public function testTheClaimRechecksBusinessFactsAfterPreflight(): void
    {
        $result = $this->complete(ApprovalOutcome::Approved);
        static::getContainer()->get(PaymentTransferApprovalResultHandler::class)->handle($result, $this->request, Context::createDefaultContext());
        static::getContainer()->get(PaymentTransferApprovalService::class)->assertApproved($this->transfer);
        static::getContainer()->get('payment_transfer.repository')->update([['id' => $this->transfer->id, 'metadata' => ['note' => 'Changed after preflight']]], Context::createDefaultContext());

        $this->assertClaimBlocked('changed_transfer');
    }

    public function testAChangedRecipientCannotUseAnEarlierApproval(): void
    {
        $result = $this->complete(ApprovalOutcome::Approved);
        static::getContainer()->get(PaymentTransferApprovalResultHandler::class)->handle($result, $this->request, Context::createDefaultContext());
        $identity = static::getContainer()->get(PaymentCredentialCipher::class)->encryptValue('another-recipient');
        static::getContainer()->get('payment_transfer_recipient.repository')->update([['id' => $this->transfer->recipientId, 'identityValue' => $identity]], Context::createDefaultContext());

        $this->assertClaimBlocked('changed_transfer');
    }

    public function testADisabledAccountCannotUseAnEarlierApproval(): void
    {
        $result = $this->complete(ApprovalOutcome::Approved);
        static::getContainer()->get(PaymentTransferApprovalResultHandler::class)->handle($result, $this->request, Context::createDefaultContext());
        static::getContainer()->get('payment_account.repository')->update([['id' => $this->transfer->accountId, 'active' => false]], Context::createDefaultContext());

        $this->assertClaimBlocked('payment_account_unavailable');
    }

    #[DataProvider('changedOutgoingInputs')]
    public function testOutgoingInputsMustMatchTheApproval(string $field, mixed $value): void
    {
        $result = $this->complete(ApprovalOutcome::Approved);
        static::getContainer()->get(PaymentTransferApprovalResultHandler::class)->handle($result, $this->request, Context::createDefaultContext());
        $this->transfer->assign([$field => $value]);

        $this->assertClaimBlocked('changed_transfer');
    }

    public static function changedOutgoingInputs(): iterable
    {
        yield 'amount' => ['amount', 101];
        yield 'currency' => ['currency', 'USD'];
        yield 'recipient' => ['recipientId', Uuid::randomHex()];
        yield 'merchant account' => ['accountId', Uuid::randomHex()];
        yield 'application' => ['paymentAppId', Uuid::randomHex()];
        yield 'business reference' => ['outNo', 'another-transfer'];
        yield 'provider' => ['provider', 'wechat'];
        yield 'channel' => ['channel', 'another-product'];
        yield 'channel parameters' => ['channelParams', ['remark' => 'another-purpose']];
        yield 'reason' => ['metadata', ['note' => 'another-purpose']];
    }

    private function assertClaimBlocked(string $reason): void
    {
        try {
            static::getContainer()->get(PaymentTransferResultBooker::class)->claim($this->transfer);
            static::fail('The transfer must not acquire a payout attempt.');
        } catch (PaymentTransferApprovalException $exception) {
            static::assertEquals(PaymentTransferApprovalException::blocked($reason), $exception);
        }

        static::assertSame(0, static::getContainer()->get(PaymentTransferService::class)->getById($this->transfer->id)->submitAttempts);
    }

    private function submitter(GatewayInterface $gateway): PaymentTransferSubmitter
    {
        $factory = static::createStub(PaymentGatewayFactory::class);
        $factory->method('createForPayment')->willReturn($gateway);
        $product = new PaymentProduct('alipay_h5', 'alipay', 'alipay', [PaymentInteraction::REDIRECT], 'Alipay');
        $provider = new PaymentProvider('alipay', 'Alipay', [], ['alipay_h5' => $product], $factory, static::createStub(PaymentNotificationExtractorInterface::class), capabilities: [PaymentCapability::TRANSFER]);
        $resolver = static::createStub(PaymentConfigResolver::class);
        $resolver->method('resolveForTransfer')->willReturn(new ResolvedPaymentConfig($provider, $product, [], [], [], [], $this->transfer->accountId));

        return new PaymentTransferSubmitter($resolver, static::getContainer()->get(PaymentTransferService::class), static::getContainer()->get(PaymentTransferResultBooker::class), static::getContainer()->get(PaymentTransferApprovalService::class));
    }

    private function complete(ApprovalOutcome $outcome): ApprovalResult
    {
        $task = $this->connection->fetchAssociative('SELECT t.id, t.execution_id FROM approval_flow_task t INNER JOIN approval_flow_execution e ON e.id = t.execution_id WHERE e.instance_id = :id', ['id' => Uuid::fromHexToBytes($this->request->getInstanceId())]);
        static::assertNotFalse($task);
        $source = new AdminApiSource($this->reviewerId);
        $source->setPermissions([ApprovalInstanceService::DECIDE_PRIVILEGE]);
        $instances = $this->runtime->get(ApprovalInstanceService::class);
        $instances->decide($this->request->getInstanceId(), Uuid::fromBytesToHex($task['id']), Uuid::randomHex(), 2, $outcome, '', Context::createDefaultContext($source));
        $instances->progress($this->request->getInstanceId(), Uuid::fromBytesToHex($task['execution_id']), Uuid::randomHex(), 3, Context::createDefaultContext());
        $criteria = (new Criteria())->addFilter(new EqualsFilter('instanceId', $this->request->getInstanceId()));
        $entity = static::getContainer()->get('approval_flow_result.repository')->search($criteria, Context::createDefaultContext())->first();
        static::assertInstanceOf(ApprovalResultEntity::class, $entity);

        return new ApprovalResult($entity->getId(), $entity->getInstanceId(), $entity->getReleaseId(), $entity->getCommandId(), $entity->getAggregateVersion(), $entity->getOutcome(), $entity->getFinishedAt());
    }
}
