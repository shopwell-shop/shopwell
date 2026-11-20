<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Transfer;

use Doctrine\DBAL\Connection;
use Payum\Core\GatewayInterface;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowReleaseStore;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowRequestCatalogueEvent;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntime;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimeException;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimeRegistry;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimeStatus;
use Shopwell\Core\System\Payment\Api\Struct\PaymentTransferDraft;
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
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalEntity;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalException;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalPolicy;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalResultVerifier;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalService;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalStore;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferBooking;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferResultBooker;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferSubmitter;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Tests\Integration\Core\System\Payment\Transfer\_fixtures\PaymentTransferApprovalFixture;

/**
 * @internal
 */
#[Package('framework')]
class PaymentTransferApprovalServiceTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testBookingWithoutMandatoryWorkflowConfigurationIsBlocked(): void
    {
        static::getContainer()->get(SystemConfigService::class)->set(PaymentTransferApprovalService::CONFIG_KEY, null);
        $context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()));
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('workflow_not_configured'));
        $this->service()->book(new PaymentTransferDraft(), $context);
    }

    public function testCatalogueHidesTransfersWhenNoWorkflowIsEnabled(): void
    {
        static::getContainer()->get(SystemConfigService::class)->set(PaymentTransferApprovalService::CONFIG_KEY, null);
        $event = new WorkflowRequestCatalogueEvent(Context::createDefaultContext());
        $this->service()->restrictRequestCatalogue($event);
        static::assertSame(['payment:transfer' => null], $event->restrictedActions);
    }

    public function testCatalogueUsesOnlyTheEnabledReleaseAndRequiresTransferCreation(): void
    {
        $transfer = $this->boundTransfer();
        $binding = static::getContainer()->get(PaymentTransferApprovalStore::class)->get($transfer->id);
        static::assertNotNull($binding);
        $document = json_decode((string) file_get_contents(\dirname(__DIR__, 5) . '/unit/Core/Framework/Workflow/Publication/Approval/_fixtures/default-approval-request.json'), true, flags: \JSON_THROW_ON_ERROR);
        $document['nodes'][0]['config']['formFields'] = [
            ['key' => 'amount', 'type' => 'number', 'label' => 'Amount', 'required' => true],
            ['key' => 'currency', 'type' => 'text', 'label' => 'Currency', 'required' => true],
            ['key' => 'recipient', 'type' => 'text', 'label' => 'Recipient', 'required' => true],
            ['key' => 'paymentAccount', 'type' => 'text', 'label' => 'Account', 'required' => true],
            ['key' => 'reason', 'type' => 'text', 'label' => 'Reason', 'required' => false],
        ];
        static::getContainer()->get('workflow_release.repository')->update([['id' => $binding->releaseId, 'document' => json_encode($document, \JSON_THROW_ON_ERROR)]], Context::createDefaultContext());
        static::getContainer()->get(SystemConfigService::class)->set(PaymentTransferApprovalService::CONFIG_KEY, $binding->releaseId);
        $event = new WorkflowRequestCatalogueEvent(Context::createDefaultContext());
        $this->service()->restrictRequestCatalogue($event);
        static::assertSame(['payment:transfer' => $binding->releaseId], $event->restrictedActions);
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['workflow:read', 'workflow_instance:start']);
        $denied = new WorkflowRequestCatalogueEvent(new Context($source));
        $this->service()->restrictRequestCatalogue($denied);
        static::assertSame(['payment:transfer' => null], $denied->restrictedActions);
    }

    public function testBookingRequiresARealAdministrationActor(): void
    {
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('administrator_required'));
        $this->service()->book(new PaymentTransferDraft(), Context::createDefaultContext());
    }

    public function testRemovingTheRuntimeCannotTurnApprovalIntoAnOptionalStep(): void
    {
        $transfer = $this->boundTransfer();
        $this->expectExceptionObject(WorkflowRuntimeException::unavailable('test:approval'));
        static::getContainer()->get(PaymentTransferApprovalService::class)->assertApproved($transfer);
    }

    public function testStatusExposesOnlySafeImmutableRecipientAndAccountLabels(): void
    {
        $transfer = $this->boundTransfer();
        $status = $this->service('approved')->status($transfer->id, Context::createDefaultContext());
        static::assertSame('approved', $status['status']);
        static::assertSame('Approved recipient ••••1234', $status['recipientLabel']);
        static::assertSame('Approved account', $status['paymentAccountLabel']);
        static::assertArrayNotHasKey('snapshot', $status);
        static::assertArrayNotHasKey('identityValue', $status);
    }

    public function testOnlyTheOriginalInitiatorMayReapply(): void
    {
        $transfer = $this->boundTransfer();
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['payment_transfer:create']);
        $context = new Context($source);
        static::assertFalse($this->service('rejected')->status($transfer->id, $context)['canReapply']);
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('reapplication_forbidden'));
        $this->service('rejected')->reapply($transfer->id, new PaymentTransferDraft(), $context);
    }

    public function testPendingApprovalCannotBeReapplied(): void
    {
        $transfer = $this->boundTransfer();
        $binding = static::getContainer()->get(PaymentTransferApprovalStore::class)->get($transfer->id);
        static::assertNotNull($binding);
        $source = new AdminApiSource($binding->initiatorId);
        $source->setPermissions(['payment_transfer:create']);
        $context = new Context($source);
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('reapplication_unavailable'));
        $this->service('pending')->reapply($transfer->id, new PaymentTransferDraft(), $context);
    }

    public function testAnUnboundTransferCannotExecute(): void
    {
        $transfer = PaymentTransferApprovalFixture::create(static::getContainer());
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('not_started'));
        $this->service()->assertApproved($transfer);
    }

    public function testAPendingApprovalCannotClaimTheTransfer(): void
    {
        $transfer = $this->boundTransfer();
        $service = $this->service('pending');
        $booker = $this->booker($service);
        try {
            $booker->claim($transfer);
            static::fail('Pending approval must prevent execution.');
        } catch (PaymentTransferApprovalException $exception) {
            static::assertSame('pending', $exception->getParameter('reason'));
        }
        static::assertSame(0, static::getContainer()->get(PaymentTransferService::class)->getById($transfer->id)->submitAttempts);
    }

    public function testRejectedAndCanceledApprovalsNeverAuthorizeExecution(): void
    {
        $transfer = $this->boundTransfer();
        foreach (['rejected', 'canceled'] as $state) {
            try {
                $this->service($state)->assertApproved($transfer);
                static::fail('A terminal refusal must prevent execution.');
            } catch (PaymentTransferApprovalException $exception) {
                static::assertSame($state, $exception->getParameter('reason'));
            }
        }
    }

    public function testAnApprovedTransferExecutesOnceUsingTheFrozenRecipient(): void
    {
        $transfer = $this->boundTransfer();
        $cipher = static::getContainer()->get(PaymentCredentialCipher::class);
        static::getContainer()->get('payment_transfer_recipient.repository')->update([['id' => $transfer->recipientId, 'identityValue' => $cipher->encryptValue('a different payee')]], Context::createDefaultContext());
        $service = $this->service('approved');
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->once())->method('execute')->willReturnCallback(static function (PaymentTransferRequest $request) use ($cipher): void {
            static::assertSame('recipient', $cipher->decryptValue($request->recipient->identityValue));
            $model = $request->getModel();
            static::assertInstanceOf(PaymentGatewayModel::class, $model);
            $model->offsetSet(PaymentGatewayModel::RESULT, new TransferResult(TransferResult::STATE_UNKNOWN));
        });
        $submitter = $this->submitter($service, $transfer, $gateway);
        $app = new PaymentAppEntity();
        $app->id = $transfer->paymentAppId;
        $submitter->submit($app, $transfer);
        static::assertSame(1, static::getContainer()->get(PaymentTransferService::class)->getById($transfer->id)->submitAttempts);
        $this->expectExceptionObject(PaymentTransferException::transferAlreadyFinalized($transfer->outNo, 'unknown'));
        $submitter->submit($app, $transfer);
    }

    public function testApprovalIsRevalidatedInsideTheAtomicClaim(): void
    {
        $transfer = $this->boundTransfer();
        $service = $this->service('approved', 'pending');
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');
        $app = new PaymentAppEntity();
        $app->id = $transfer->paymentAppId;
        try {
            $this->submitter($service, $transfer, $gateway)->submit($app, $transfer);
            static::fail('A changed approval must prevent the provider call.');
        } catch (PaymentTransferApprovalException $exception) {
            static::assertSame('pending', $exception->getParameter('reason'));
        }
        static::assertSame(0, static::getContainer()->get(PaymentTransferService::class)->getById($transfer->id)->submitAttempts);
    }

    /**
     * @param 'pending'|'approved'|'rejected'|'canceled' ...$states
     */
    private function service(string ...$states): PaymentTransferApprovalService
    {
        $container = static::getContainer();
        $runtime = static::createStub(WorkflowRuntime::class);
        $runtime->method('name')->willReturn('test:approval');
        $runtime->method('status')->willReturnCallback(static function () use (&$states): WorkflowRuntimeStatus {
            $state = \count($states) > 1 ? array_shift($states) : ($states[0] ?? 'pending');

            return new WorkflowRuntimeStatus($state);
        });

        return new PaymentTransferApprovalService(
            $container->get(PaymentTransferApprovalStore::class),
            $container->get(PaymentTransferApprovalPolicy::class),
            $container->get(WorkflowReleaseStore::class),
            new WorkflowRuntimeRegistry([$runtime]),
            $container->get(SystemConfigService::class),
            $container->get(PaymentTransferBooking::class),
            $container->get(PaymentTransferService::class),
            $container->get(PaymentTransferPresenter::class),
            $container->get('event_dispatcher'),
            $container->get(PaymentTransferApprovalResultVerifier::class),
        );
    }

    private function boundTransfer(): PaymentTransferEntity
    {
        $container = static::getContainer();
        $transfer = PaymentTransferApprovalFixture::create($container);
        $releaseId = Uuid::randomHex();
        $container->get('workflow_release.repository')->create([[
            'id' => $releaseId, 'draftId' => Uuid::randomHex(), 'revision' => 1, 'name' => 'Test approval', 'publisher' => 'test:approval', 'publishedBy' => Uuid::randomHex(), 'document' => '{}',
        ]], Context::createDefaultContext());
        static::assertNotNull($transfer->recipient);
        $approval = new PaymentTransferApprovalEntity();
        $approval->assign([
            'id' => $transfer->id, 'releaseId' => $releaseId, 'instanceId' => Uuid::randomHex(), 'initiatorId' => Uuid::randomHex(),
            'snapshot' => $container->get(PaymentTransferApprovalPolicy::class)->snapshot($transfer, $transfer->recipient),
        ]);
        $approval->snapshot['recipientLabel'] = 'Approved recipient ••••1234';
        $approval->snapshot['paymentAccountLabel'] = 'Approved account';
        $container->get(PaymentTransferApprovalStore::class)->create($approval);

        return $transfer;
    }

    private function booker(PaymentTransferApprovalService $service): PaymentTransferResultBooker
    {
        return new PaymentTransferResultBooker(static::getContainer()->get(PaymentTransferService::class), static::getContainer()->get(Connection::class), $service);
    }

    private function submitter(PaymentTransferApprovalService $service, PaymentTransferEntity $transfer, GatewayInterface $gateway): PaymentTransferSubmitter
    {
        $factory = static::createStub(PaymentGatewayFactory::class);
        $factory->method('createForPayment')->willReturn($gateway);
        $product = new PaymentProduct('alipay_h5', 'alipay', 'alipay', [PaymentInteraction::REDIRECT], 'Alipay');
        $provider = new PaymentProvider('alipay', 'Alipay', [], ['alipay_h5' => $product], $factory, static::createStub(PaymentNotificationExtractorInterface::class), capabilities: [PaymentCapability::TRANSFER]);
        $resolver = static::createStub(PaymentConfigResolver::class);
        $resolver->method('resolveForTransfer')->willReturn(new ResolvedPaymentConfig($provider, $product, [], [], [], [], $transfer->accountId));

        return new PaymentTransferSubmitter($resolver, static::getContainer()->get(PaymentTransferService::class), $this->booker($service), $service);
    }
}
