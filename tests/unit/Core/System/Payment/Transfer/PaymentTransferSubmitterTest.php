<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Transfer;

use Payum\Core\GatewayInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Event\NestedEventCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PaymentTransferRequest;
use Shopwell\Core\System\Payment\Gateway\TransferResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferCollection;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientCollection;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferReconciler;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStateHandler;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferSubmitter;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\Clock\MockClock;

/**
 * What a channel answer means for the state of the transfer. The pure part carries the money-critical
 * knowledge, so it is asserted directly - the service behind it is final and can not be doubled.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentTransferSubmitter::class)]
class PaymentTransferSubmitterTest extends TestCase
{
    private const SECRET = 'unit-test-application-secret';

    private const NOW = '2026-10-18T09:00:00+00:00';

    /**
     * What the four interfaces declare for their payouts: they pay out and they answer what became of
     * a payout.
     */
    private const TRANSFER_CAPABILITIES = [
        PaymentCapability::CAPTURE,
        PaymentCapability::TRANSFER,
        PaymentCapability::TRANSFER_QUERY,
    ];

    /**
     * A channel that hands out the final state immediately still walks the money path: the machine has
     * no shortcut from `submitted` that could be relied on, and every step is a real observation.
     */
    public function testASucceededAnswerWalksTheWholeMoneyPath(): void
    {
        static::assertSame(
            [
                PaymentTransferSubmitter::STEP_SUBMIT,
                PaymentTransferSubmitter::STEP_PROCESS,
                PaymentTransferSubmitter::STEP_SUCCEED,
            ],
            PaymentTransferSubmitter::stepNames(
                new TransferResult(TransferResult::STATE_SUCCEEDED, 'po_1')
            )
        );
    }

    /**
     * Wechat hands out the `package_info` and waits for the shopper - there is nothing to process yet.
     */
    public function testAnAwaitingConfirmAnswerOnlyWaits(): void
    {
        static::assertSame(
            [PaymentTransferSubmitter::STEP_SUBMIT, PaymentTransferSubmitter::STEP_AWAIT_CONFIRM],
            PaymentTransferSubmitter::stepNames(
                new TransferResult(TransferResult::STATE_AWAITING_CONFIRM, 'transfer-bill-1', confirmPayload: 'MchId=1')
            )
        );
    }

    /**
     * `accepted` and `processing` are the same fact for the state machine: the interface took the
     * payout and moves the money by itself.
     */
    #[DataProvider('movingStates')]
    public function testAMovingAnswerIsSubmittedAndProcessing(string $state): void
    {
        static::assertSame(
            [PaymentTransferSubmitter::STEP_SUBMIT, PaymentTransferSubmitter::STEP_PROCESS],
            PaymentTransferSubmitter::stepNames(new TransferResult($state, 'order-1'))
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function movingStates(): iterable
    {
        yield 'processing' => [TransferResult::STATE_PROCESSING];
        yield 'accepted' => [TransferResult::STATE_ACCEPTED];
    }

    /**
     * The interface opened an object and then refused the payout - the object is the record of the
     * attempt and the transfer has been submitted, so the failure is booked from `submitted`.
     */
    public function testAFailureWithAnObjectIsSubmittedFirst(): void
    {
        static::assertSame(
            [PaymentTransferSubmitter::STEP_SUBMIT, PaymentTransferSubmitter::STEP_FAIL],
            PaymentTransferSubmitter::stepNames(
                new TransferResult(TransferResult::STATE_FAILED, 'order-1', failureCode: 'PAYEE_NOT_EXIST')
            )
        );
    }

    /**
     * A refusal that never opened an object leaves nothing to submit. Fabricating a `submit` would
     * mean inventing a reference no query could ever use - `created -> failed` is the edge for this.
     */
    public function testAFailureWithoutAnObjectIsAFailureFromCreated(): void
    {
        static::assertSame(
            [PaymentTransferSubmitter::STEP_FAIL],
            PaymentTransferSubmitter::stepNames(
                new TransferResult(TransferResult::STATE_FAILED, failureCode: 'PAYEE_NOT_EXIST')
            )
        );
    }

    /**
     * A transfer that never left `created` can not have arrived and come back: a channel answering
     * `RETURNED` to a fresh payout has simply not moved the money. `mark_returned` is the reconciler's
     * step, never the submitter's.
     */
    public function testAReturnedAnswerAtSubmissionIsAFailureNotAReturn(): void
    {
        $steps = PaymentTransferSubmitter::stepNames(new TransferResult(TransferResult::STATE_RETURNED, 'order-1'));

        static::assertSame(
            [PaymentTransferSubmitter::STEP_SUBMIT, PaymentTransferSubmitter::STEP_PROCESS, PaymentTransferSubmitter::STEP_FAIL],
            $steps
        );
        static::assertNotContains(PaymentTransferReconciler::STEP_MARK_RETURNED, $steps);
    }

    /**
     * The interface named the object but said nothing about its fate - the submission is a fact worth
     * recording (it is what the query is addressed to), the outcome is not.
     */
    public function testAnUnknownAnswerWithAnObjectOnlyRecordsTheSubmission(): void
    {
        static::assertSame(
            [PaymentTransferSubmitter::STEP_SUBMIT],
            PaymentTransferSubmitter::stepNames(new TransferResult(TransferResult::STATE_UNKNOWN, 'order-1'))
        );
    }

    /**
     * Nothing was learned at all - the transfer stays `created` and the caller may send the very same
     * out number again.
     */
    public function testAnUnknownAnswerWithoutAnObjectRecordsNothing(): void
    {
        static::assertSame([], PaymentTransferSubmitter::stepNames(new TransferResult(TransferResult::STATE_UNKNOWN)));
    }

    /**
     * The credentials are resolved from the app, so a transfer of another app would be paid out of this
     * merchant account.
     */
    public function testATransferOfAnotherAppIsRefused(): void
    {
        try {
            $this->submitter()->submit($this->app('app-id'), $this->transfer(['paymentAppId' => 'other-app']));
            static::fail('a payout of another app has to be refused');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_FOREIGN_APP, $exception->getErrorCode());
            // the app that tried to pay it out, not the one the transfer belongs to
            static::assertSame('app-id', $exception->getParameter('appId'));
        }
    }

    /**
     * Submitting twice is not a retry but a second payout, and the guard has to fire before the
     * credentials are resolved at all: the resolver below knows no channel, so reaching it would raise
     * `PAYMENT__UNKNOWN_CHANNEL` instead of the state conflict.
     */
    public function testATransferThatIsNotCreatedIsRefusedBeforeTheChannelIsResolved(): void
    {
        $transfer = $this->transfer([
            'statusMachineState' => $this->stateOf(PaymentTransferStates::STATE_SUBMITTED),
        ]);

        try {
            $this->submitter()->submit($this->app('app-id'), $transfer);
            static::fail('a submitted transfer must not be submitted again');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_ALREADY_FINALIZED, $exception->getErrorCode());
            static::assertSame(PaymentTransferStates::STATE_SUBMITTED, $exception->getParameter('state'));
        }
    }

    /**
     * A transfer whose state was not read can not be classified, and sending it is the one thing that
     * must never happen - the guard fails closed instead of assuming `created`.
     */
    public function testATransferWithoutAStateIsRefused(): void
    {
        try {
            $this->submitter()->submit($this->app('app-id'), $this->transfer());
            static::fail('a transfer whose state is unknown must not be submitted');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_ALREADY_FINALIZED, $exception->getErrorCode());
        }
    }

    /**
     * A domain error names its own cause and its own status. Wrapping it would report a missing
     * parameter as a gateway outage and turn a 4xx into a 5xx the caller believes it may retry.
     */
    public function testADomainErrorIsNotWrappedIntoAGatewayError(): void
    {
        $error = PaymentException::missingParameter('channel_params.transfer_scene_name');

        $attempts = [];
        $transfers = static::createMock(EntityRepository::class);
        $transfers->expects($this->once())
            ->method('update')
            ->willReturnCallback(function (array $data) use (&$attempts): EntityWrittenContainerEvent {
                $attempts[] = $data[0];

                return $this->written();
            });

        try {
            $this->submitter($this->gatewayThatThrows($error), $transfers)
                ->submit($this->app('app-id'), $this->createdTransfer());
            static::fail('the domain error has to surface');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::MISSING_PARAMETER, $exception->getErrorCode());
            static::assertSame('channel_params.transfer_scene_name', $exception->getParameter('name'));
        }

        // the attempt is still recorded: "someone tried and this is why it failed" is true even when
        // nothing was sent, and the counter carries no behaviour
        static::assertCount(1, $attempts);
        static::assertSame(1, $attempts[0]['submitAttempts']);
        static::assertStringContainsString('channel_params.transfer_scene_name', (string) $attempts[0]['failureReason']);
    }

    /**
     * An interface that never declared a payout is refused before anything is sent, and before the
     * attempt is recorded: nothing was attempted, so nothing may be written as an attempt - and the
     * caller gets a 400 naming the capability instead of a 502 on money it would retry.
     */
    public function testAnInterfaceWithoutTransferIsRefusedBeforeAnythingIsSent(): void
    {
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');

        $transfers = static::createMock(EntityRepository::class);
        $transfers->expects($this->never())->method('update');

        $submitter = new PaymentTransferSubmitter(
            $this->resolverFor($gateway, [PaymentCapability::CAPTURE, PaymentCapability::REFUND]),
            $this->transferService($transfers)
        );

        $this->expectExceptionObject(PaymentException::providerLacksCapability('alipay', PaymentCapability::TRANSFER->value));

        $submitter->submit($this->app('app-id'), $this->createdTransfer());
    }

    /**
     * A channel that refuses a payout names the reason in its answer, and the answer is the only place
     * that has it - the code alone (`PAYEE_NOT_EXIST`) does not say what the holder should fix.
     */
    public function testAFailureIsBookedWithTheAnswerOfTheChannel(): void
    {
        $response = [
            'code' => '40004',
            'sub_code' => 'PAYEE_NOT_EXIST',
            'sub_msg' => '收款账号不存在',
        ];

        $written = [];
        $this->submitter($this->answeringGateway($response, new TransferResult(
            TransferResult::STATE_FAILED,
            providerRef: 'order-1',
            failureCode: 'PAYEE_NOT_EXIST',
        )), $this->writingRepository($written))
            ->submit($this->app('app-id'), $this->createdTransfer());

        // the submission is the first write, the failure the second
        static::assertSame('order-1', $written[0]['providerRef']);
        static::assertSame('PAYEE_NOT_EXIST', $written[1]['failureCode']);
        static::assertSame(
            json_encode($response, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
            $written[1]['failureReason']
        );
        static::assertNotNull($written[1]['failureReason']);
    }

    /**
     * `returned` carries no failure code, so the state is the code - a row that says nothing at all
     * would leave the case undiagnosable.
     */
    public function testAReturnedAnswerStillNamesACodeAndTheAnswer(): void
    {
        $written = [];
        $this->submitter($this->answeringGateway(
            ['status' => 'REFUND'],
            new TransferResult(TransferResult::STATE_RETURNED, 'order-1')
        ), $this->writingRepository($written))
            ->submit($this->app('app-id'), $this->createdTransfer());

        static::assertSame(TransferResult::STATE_RETURNED, $written[1]['failureCode']);
        static::assertStringContainsString('REFUND', (string) $written[1]['failureReason']);
    }

    /**
     * A channel answer is not bounded by us - it has to fit the column of the row that keeps it.
     */
    public function testAnOverlongAnswerIsCutToTheColumnWidth(): void
    {
        $written = [];
        $this->submitter($this->answeringGateway(
            ['sub_msg' => str_repeat('错', 400)],
            new TransferResult(TransferResult::STATE_FAILED, 'order-1', failureCode: 'PAYEE_NOT_EXIST')
        ), $this->writingRepository($written))
            ->submit($this->app('app-id'), $this->createdTransfer());

        static::assertSame(255, mb_strlen((string) $written[1]['failureReason']));
    }

    /**
     * @param EntityRepository<PaymentTransferCollection>|null $transfers
     */
    private function submitter(?GatewayInterface $gateway = null, ?EntityRepository $transfers = null): PaymentTransferSubmitter
    {
        $resolver = $gateway instanceof GatewayInterface
            ? $this->resolverFor($gateway)
            : $this->resolverWithoutChannels();

        return new PaymentTransferSubmitter($resolver, $this->transferService($transfers));
    }

    private function gatewayThatThrows(PaymentException $error): GatewayInterface
    {
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willThrowException($error);

        return $gateway;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function answeringGateway(array $response, TransferResult $result): GatewayInterface
    {
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willReturnCallback(static function (object $request) use ($response, $result): void {
            static::assertInstanceOf(PaymentTransferRequest::class, $request);
            // the parameters the payout was booked with are the ones it is built with - they survive
            // the row and are handed to the adapter again on every attempt
            static::assertSame(['transfer_scene_name' => '佣金报酬'], $request->channelParams);

            $model = $request->getModel();
            static::assertInstanceOf(PaymentGatewayModel::class, $model);

            $model->offsetSet(PaymentGatewayModel::RESPONSE, $response);
            $model->offsetSet(PaymentGatewayModel::RESULT, $result);
        });

        return $gateway;
    }

    /**
     * @param list<array<string, mixed>> $written
     *
     * @return EntityRepository<PaymentTransferCollection>
     */
    private function writingRepository(array &$written): EntityRepository
    {
        $transfers = static::createStub(EntityRepository::class);
        $transfers->method('update')->willReturnCallback(function (array $data) use (&$written): EntityWrittenContainerEvent {
            $written[] = $data[0];

            return $this->written();
        });

        return $transfers;
    }

    private function written(): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []);
    }

    /**
     * A resolver that knows no interface and no channel: any attempt to resolve one fails loudly, which
     * is exactly what proves a guard ran before the channel was reached.
     */
    private function resolverWithoutChannels(): PaymentConfigResolver
    {
        $providers = new PaymentProviderRegistry([]);

        return new PaymentConfigResolver(
            $providers,
            new PaymentChannelRegistry($providers),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            new PaymentCredentialCipher(self::SECRET),
        );
    }

    /**
     * A resolver that actually resolves: one app, authorized for alipay, with the channel enabled - and
     * the given gateway handed out by the factory of the interface.
     *
     * @param list<PaymentCapability> $capabilities
     */
    private function resolverFor(GatewayInterface $gateway, array $capabilities = self::TRANSFER_CAPABILITIES): PaymentConfigResolver
    {
        $cipher = new PaymentCredentialCipher(self::SECRET);

        $channel = new PaymentChannel(
            technicalName: 'alipay_h5',
            providerTechnicalName: 'alipay',
            label: 'Alipay H5',
            flow: PaymentFlow::REDIRECT,
        );

        $gatewayFactory = static::createStub(PaymentGatewayFactory::class);
        $gatewayFactory->method('createForPayment')->willReturn($gateway);

        $provider = new PaymentProvider(
            technicalName: 'alipay',
            label: 'Alipay',
            credentials: [],
            channels: ['alipay_h5' => $channel],
            gatewayFactory: $gatewayFactory,
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
            capabilities: $capabilities,
        );

        $providers = new PaymentProviderRegistry([$provider]);

        $appProvider = new PaymentAppProviderEntity();
        $appProvider->setUniqueIdentifier('app-provider-id');
        $appProvider->paymentAppId = 'app-id';
        $appProvider->providerTechnicalName = 'alipay';
        $appProvider->enabled = true;
        $appProvider->credentials = $cipher->encrypt(['app_id' => 'own-app-id']);

        $appChannel = new PaymentAppChannelEntity();
        $appChannel->setUniqueIdentifier('app-channel-id');
        $appChannel->paymentAppId = 'app-id';
        $appChannel->channelTechnicalName = 'alipay_h5';
        $appChannel->enabled = true;
        $appChannel->params = $cipher->encrypt(['appid' => 'own-appid']);

        $channelConfig = new PaymentChannelConfigEntity();
        $channelConfig->setUniqueIdentifier('channel-config-id');
        $channelConfig->providerTechnicalName = 'alipay';
        $channelConfig->channelTechnicalName = 'alipay_h5';
        $channelConfig->enabled = true;
        $channelConfig->params = $cipher->encrypt(['appid' => 'platform-appid']);

        $appProviderRepository = static::createStub(EntityRepository::class);
        $appProviderRepository->method('search')->willReturn($this->entityResult('payment_app_provider', $appProvider));

        $appChannelRepository = static::createStub(EntityRepository::class);
        $appChannelRepository->method('search')->willReturn($this->entityResult('payment_app_channel', $appChannel));

        $channelConfigRepository = static::createStub(EntityRepository::class);
        $channelConfigRepository->method('search')->willReturn($this->entityResult('payment_channel_config', $channelConfig));

        return new PaymentConfigResolver(
            $providers,
            new PaymentChannelRegistry($providers),
            $appProviderRepository,
            static::createStub(EntityRepository::class),
            $appChannelRepository,
            $channelConfigRepository,
            $cipher,
        );
    }

    /**
     * @return EntitySearchResult<EntityCollection<Entity>>
     */
    private function entityResult(string $entityName, Entity $entity): EntitySearchResult
    {
        return new EntitySearchResult(
            $entityName,
            1,
            new EntityCollection([$entity]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * @param EntityRepository<PaymentTransferCollection>|null $transfers
     */
    private function transferService(?EntityRepository $transfers = null): PaymentTransferService
    {
        $transfers ??= static::createStub(EntityRepository::class);

        $recipients = static::createStub(EntityRepository::class);
        $recipients->method('search')->willReturn(new EntitySearchResult(
            PaymentTransferRecipientEntity::ENTITY_NAME,
            1,
            new PaymentTransferRecipientCollection([$this->recipient()]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        $registry = static::createStub(StateMachineRegistry::class);
        $registry->method('transition')->willReturn(new StateMachineStateCollection());

        $initialStateIdLoader = static::createStub(InitialStateIdLoader::class);
        $initialStateIdLoader->method('get')->willReturn('state-created');

        $cipher = new PaymentCredentialCipher(self::SECRET);

        return new PaymentTransferService(
            $transfers,
            $recipients,
            new PaymentTransferStateHandler($registry),
            new PaymentTransferRecipientService($recipients, $cipher, new MockClock(self::NOW)),
            $cipher,
            $initialStateIdLoader,
            new MockClock(self::NOW),
        );
    }

    private function recipient(): PaymentTransferRecipientEntity
    {
        $recipient = new PaymentTransferRecipientEntity();
        $recipient->id = 'recipient-id';
        $recipient->paymentAppId = 'app-id';
        $recipient->provider = 'alipay';
        $recipient->identityType = 'alipay_user_id';
        $recipient->identityValue = 'ciphertext';
        $recipient->identityHash = str_repeat('a', 64);
        $recipient->onboardingStatus = PaymentTransferStates::ONBOARDING_REGISTERED;
        $recipient->payoutEnabled = true;

        $recipient->setUniqueIdentifier($recipient->id);

        return $recipient;
    }

    /**
     * A transfer that may be submitted: `created`, with the channel parameters it was booked with.
     */
    private function createdTransfer(): PaymentTransferEntity
    {
        return $this->transfer([
            'statusMachineState' => $this->stateOf(PaymentTransferStates::STATE_CREATED),
            'channelParams' => ['transfer_scene_name' => '佣金报酬'],
        ]);
    }

    private function app(string $id): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->id = $id;

        return $app;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function transfer(array $overrides = []): PaymentTransferEntity
    {
        $transfer = new PaymentTransferEntity();
        $transfer->id = 'transfer-id';
        $transfer->paymentAppId = 'app-id';
        $transfer->transferNo = 'PT261018090000ABCDEF0123';
        $transfer->outNo = 'commission0001';
        $transfer->recipientId = 'recipient-id';
        $transfer->provider = 'alipay';
        $transfer->channel = 'alipay_h5';
        $transfer->amount = 100;
        $transfer->currency = 'CNY';
        $transfer->statusId = 'status-id';
        $transfer->submitAttempts = 0;
        $transfer->assign($overrides);

        $transfer->setUniqueIdentifier($transfer->id);

        return $transfer;
    }

    private function stateOf(string $technicalName): StateMachineStateEntity
    {
        $state = new StateMachineStateEntity();
        $state->setUniqueIdentifier($technicalName);
        $state->setTechnicalName($technicalName);

        return $state;
    }
}
