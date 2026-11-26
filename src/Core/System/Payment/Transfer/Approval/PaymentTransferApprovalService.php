<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Approval;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Publication\Entity\WorkflowReleaseEntity;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowReleaseCheckEvent;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowReleaseStore;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowRequestCatalogueEvent;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimeException;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimePreviewEvent;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimeRegistry;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimeStatus;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimeStatusesEvent;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Shopwell\Core\System\Currency\CurrencyAmountConverter;
use Shopwell\Core\System\Payment\Api\Struct\PaymentTransferDraft;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Transfer\Api\PaymentTransferPresenter;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferBooking;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\System\Payment\Transfer\PaymentTransferApprovalServiceTest
 */
#[Package('framework')]
class PaymentTransferApprovalService
{
    public const CONFIG_KEY = 'core.paymentTransfer.workflowReleaseId';

    public function __construct(
        private readonly PaymentTransferApprovalStore $store,
        private readonly PaymentTransferApprovalPolicy $policy,
        private readonly WorkflowReleaseStore $releases,
        private readonly WorkflowRuntimeRegistry $runtimes,
        private readonly SystemConfigService $config,
        private readonly PaymentTransferBooking $booking,
        private readonly PaymentTransferService $transfers,
        private readonly PaymentTransferPresenter $presenter,
        private readonly EventDispatcherInterface $events,
        private readonly PaymentTransferApprovalResultVerifier $results,
        private readonly PaymentTransferApprovalRequestStore $requestStore,
        private readonly ApprovalRequestService $approvalRequests,
    ) {
    }

    public function book(PaymentTransferDraft $draft, Context $context): PaymentTransferEntity
    {
        return $this->bookDraft($draft, $context);
    }

    public function reapply(string $transferId, PaymentTransferDraft $draft, Context $context): PaymentTransferEntity
    {
        $this->assertReapplicable($transferId, $context);
        if ($draft->outNo === null) {
            throw PaymentTransferApprovalException::blocked('reapplication_out_number_required');
        }

        return $this->bookDraft($draft, $context, $transferId);
    }

    public function assertApproved(PaymentTransferEntity $transfer): PaymentTransferRecipientEntity
    {
        $recipient = $this->results->recipient($transfer);
        if ($recipient !== null) {
            return $recipient;
        }

        $approval = $this->store->get($transfer->id);
        if ($approval === null) {
            throw PaymentTransferApprovalException::blocked('not_started');
        }
        $recipient = $this->policy->recipient($approval, $transfer);
        $this->policy->recipient($approval, $this->transfers->getById($transfer->id));
        $context = Context::createDefaultContext();
        $release = $this->releases->get($approval->releaseId, $context);
        $status = $this->runtimes->get($release->getPublisher())->status($release, $approval->instanceId, $context);
        if ($status->status !== WorkflowRuntimeStatus::APPROVED) {
            throw PaymentTransferApprovalException::blocked($status->status);
        }

        return $recipient;
    }

    /**
     * @return array<string, mixed>
     */
    public function status(string $transferId, Context $context): array
    {
        $transfer = $this->transfers->getById($transferId);
        $approval = $this->store->get($transferId);
        $result = ['status' => 'not_started', 'releaseId' => null, 'releaseName' => null, 'revision' => null, 'instanceId' => null, 'detailRoute' => null, 'canCancel' => false, 'canReapply' => false, 'previousTransfer' => null, 'recipientLabel' => null, 'paymentAccountLabel' => null];
        if ($approval === null) {
            return $result;
        }
        $result['releaseId'] = $approval->releaseId;
        $result['instanceId'] = $approval->instanceId;
        if (isset($approval->snapshot['previousTransferId'], $approval->snapshot['previousTransferNo'])) {
            $result['previousTransfer'] = ['id' => $approval->snapshot['previousTransferId'], 'transferNo' => $approval->snapshot['previousTransferNo']];
        }
        $result['recipientLabel'] = $approval->snapshot['recipientLabel'] ?? null;
        $result['paymentAccountLabel'] = $approval->snapshot['paymentAccountLabel'] ?? null;
        try {
            $this->policy->recipient($approval, $transfer);
            $release = $this->releases->get($approval->releaseId, $context);
            $result['releaseName'] = $release->getName();
            $result['revision'] = $release->getRevision();
            $status = $this->runtimes->get($release->getPublisher())->status($release, $approval->instanceId, $context);
            $result['status'] = $status->status;
            $result['detailRoute'] = $status->detailRoute;
            $source = $context->getSource();
            $result['canReapply'] = $source instanceof AdminApiSource && $source->getUserId() === $approval->initiatorId
                && $context->isAllowed('payment_transfer:create') && $transfer->submitAttempts === 0
                && \in_array($transfer->statusMachineState?->getTechnicalName(), [PaymentTransferStates::STATE_CREATED, PaymentTransferStates::STATE_CANCELED], true)
                && \in_array($status->status, [WorkflowRuntimeStatus::REJECTED, WorkflowRuntimeStatus::CANCELED], true);
            $result['canCancel'] = $transfer->submitAttempts === 0 && $status->canCancel && $context->isAllowed('payment_transfer:create');
        } catch (\Throwable) {
            $result['status'] = 'unavailable';
        }

        return $result;
    }

    /**
     * Read-only list projection; it never authorizes executing a transfer.
     *
     * @param list<string> $transferIds Business records authorized by the action endpoint.
     *
     * @return array<string, array<string, mixed>>
     */
    public function summaries(array $transferIds, Context $context): array
    {
        $result = [];
        foreach ($transferIds as $id) {
            $result[$id] = ['status' => 'not_started', 'currentAssignees' => [], 'detailRoute' => null];
        }
        $approvals = $this->store->getMany($transferIds, $context);
        $releaseIds = [];
        foreach ($approvals as $approval) {
            $releaseIds[] = $approval->releaseId;
            $result[$approval->id]['status'] = 'unavailable';
        }
        $releases = $this->releases->getMany($releaseIds, $context);
        $references = [];
        foreach ($approvals as $approval) {
            $release = $releases->get($approval->releaseId);
            if ($release !== null && $this->runtimes->has($release->getPublisher())) {
                $references[$approval->instanceId] = ['releaseId' => $release->getId(), 'publisher' => $release->getPublisher()];
            }
        }
        if ($references === []) {
            return $result;
        }
        $event = new WorkflowRuntimeStatusesEvent($references, $context);
        $this->events->dispatch($event);
        $statuses = $event->getStatuses();
        foreach ($approvals as $approval) {
            $summary = $statuses[$approval->instanceId] ?? null;
            if ($summary === null) {
                continue;
            }
            $result[$approval->id] = ['status' => $summary['status']->status, 'currentAssignees' => $summary['currentAssignees'], 'detailRoute' => $summary['status']->detailRoute];
        }

        return $result;
    }

    public function cancel(string $transferId, string $reason, Context $context): void
    {
        $withdrawn = $this->requestStore->transactional($transferId, function () use ($transferId, $reason, $context): bool {
            $approval = $this->requestStore->getApproval($transferId);
            if ($approval === null) {
                return false;
            }

            $transfer = $this->transfers->getById($transferId);
            $state = $transfer->statusMachineState?->getTechnicalName();
            $alreadyWithdrawn = $state === PaymentTransferStates::STATE_CANCELED && $approval['status'] === ApprovalInstanceStatus::Withdrawn;
            if ($transfer->submitAttempts !== 0 || ($state !== PaymentTransferStates::STATE_CREATED && !$alreadyWithdrawn)) {
                throw PaymentTransferApprovalException::blocked('transfer_already_started');
            }

            $this->approvalRequests->withdraw($approval['requestId'], $reason, $context);
            if (!$alreadyWithdrawn) {
                $this->transfers->cancel($transfer, $reason);
            }

            return true;
        });
        if ($withdrawn) {
            return;
        }

        $approval = $this->store->get($transferId);
        if ($approval === null) {
            throw PaymentTransferApprovalException::blocked('not_started');
        }
        $release = $this->releases->get($approval->releaseId, $context);
        $runtime = $this->runtimes->get($release->getPublisher());
        $runtime->transactional(function () use ($transferId, $approval, $release, $runtime, $reason, $context): void {
            $this->store->lock($transferId);
            $transfer = $this->transfers->getById($transferId);
            if ($transfer->submitAttempts !== 0 || $transfer->statusMachineState?->getTechnicalName() !== 'created') {
                throw PaymentTransferApprovalException::blocked('transfer_already_started');
            }
            $runtime->cancel($release, $approval->instanceId, $reason, $context);
            $this->transfers->cancel($transfer, $reason);
        });
    }

    public function restrictRequestCatalogue(WorkflowRequestCatalogueEvent $event): void
    {
        $event->restrictedActions['payment:transfer'] = null;
        if (!$event->context->isAllowed('payment_transfer:create')) {
            return;
        }
        try {
            $event->restrictedActions['payment:transfer'] = $this->configuredRelease($event->context)->getId();
        } catch (\Throwable) {
            // Unavailable finance configuration must not advertise an unusable or different flow.
        }
    }

    public function available(Context $context): bool
    {
        try {
            $this->configuredRelease($context);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Advisory only: do not book a transfer, onboard a recipient or create runtime tasks here.
     *
     * @return array<string, mixed>
     */
    public function preview(PaymentTransferDraft $draft, Context $context): array
    {
        $result = ['available' => false, 'reason' => null, 'releaseId' => null, 'releaseName' => null, 'revision' => null, 'steps' => []];
        try {
            $release = $this->configuredRelease($context);
            $result['releaseId'] = $release->getId();
            $result['releaseName'] = $release->getName();
            $result['revision'] = $release->getRevision();
            $app = $this->booking->resolveApp($draft->paymentAppId);
            $accountName = null;
            foreach ($app->accounts ?? [] as $authorization) {
                $account = $authorization->account;
                if ($authorization->enabled && $account !== null && $account->active && $account->id === $draft->accountId && $account->providerTechnicalName === $draft->provider) {
                    $accountName = $account->name;
                    break;
                }
            }
            if ($accountName === null) {
                throw PaymentTransferApprovalException::blocked('payment_account_unavailable');
            }
            if ($draft->recipientId !== null) {
                $recipient = $this->transfers->getRecipient($app, $draft->recipientId);
                if (!$recipient->payoutEnabled || $recipient->provider !== $draft->provider) {
                    throw PaymentTransferApprovalException::blocked('recipient_unavailable');
                }
                $recipientLabel = trim(($recipient->holderName ?? '') . ' ' . $this->presenter->recipient($recipient)['maskedIdentity']);
            } else {
                if ($draft->identityValue === null || trim($draft->identityValue) === '') {
                    throw PaymentTransferApprovalException::blocked('recipient_unavailable');
                }
                $recipientLabel = trim(($draft->holderName ?? '') . ' ' . PaymentTransferPresenter::mask(trim($draft->identityValue)));
            }
            if ($draft->amount === null || $draft->amount <= 0 || $draft->amount > PaymentTransferDraft::MAX_AMOUNT || $draft->currency === null) {
                throw PaymentTransferApprovalException::blocked('invalid_transfer_input');
            }
            $this->booking->validateProduct($app, $draft);
            $event = new WorkflowRuntimePreviewEvent($release, $this->workflowData($draft->amount, strtoupper($draft->currency), $recipientLabel, $accountName, $draft->note), $context);
            $this->events->dispatch($event);
            $steps = $event->getSteps();
            if ($steps === null) {
                $result['reason'] = 'preview_unavailable';
            } else {
                $result['available'] = true;
                $result['steps'] = $steps;
            }
        } catch (\Throwable $exception) {
            $result['reason'] = $this->unavailableReason($exception);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function configuration(Context $context): array
    {
        $configured = $this->config->getString(self::CONFIG_KEY);
        $result = ['releaseId' => $configured === '' ? null : $configured, 'releaseName' => null, 'revision' => null, 'available' => false, 'reason' => null, 'releases' => []];
        try {
            $release = $this->configuredRelease($context);
            $result['releaseName'] = $release->getName();
            $result['revision'] = $release->getRevision();
            $result['available'] = true;
        } catch (\Throwable $exception) {
            $result['reason'] = $this->unavailableReason($exception);
        }
        foreach ($this->releases->list(null, 100, 0, $context)->getEntities() as $release) {
            try {
                $this->policy->validate($release);
            } catch (PaymentTransferApprovalException) {
                continue;
            }
            $reason = null;
            try {
                $this->runtimes->get($release->getPublisher());
                $this->events->dispatch(new WorkflowReleaseCheckEvent($release, $context));
            } catch (\Throwable $exception) {
                $reason = $this->unavailableReason($exception);
            }
            $result['releases'][] = ['id' => $release->getId(), 'name' => $release->getName(), 'revision' => $release->getRevision(), 'available' => $reason === null, 'reason' => $reason];
        }

        return $result;
    }

    public function configure(string $releaseId, Context $context): void
    {
        if (!Uuid::isValid($releaseId)) {
            throw PaymentTransferApprovalException::blocked('invalid_release');
        }
        $release = $this->releases->get($releaseId, $context);
        $this->runtimes->get($release->getPublisher());
        $this->policy->validate($release);
        $this->events->dispatch(new WorkflowReleaseCheckEvent($release, $context));
        $this->config->set(self::CONFIG_KEY, $releaseId);
    }

    private function bookDraft(PaymentTransferDraft $draft, Context $context, ?string $previousTransferId = null): PaymentTransferEntity
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource || $source->getUserId() === null) {
            throw PaymentTransferApprovalException::blocked('administrator_required');
        }
        $release = $this->configuredRelease($context);
        $runtime = $this->runtimes->get($release->getPublisher());

        return $runtime->transactional(function () use ($draft, $context, $source, $release, $runtime, $previousTransferId): PaymentTransferEntity {
            $previous = null;
            if ($previousTransferId !== null) {
                $this->store->lock($previousTransferId);
                $previous = $this->assertReapplicable($previousTransferId, $context);
                if ($draft->outNo === $previous->outNo) {
                    throw PaymentTransferApprovalException::blocked('reapplication_requires_new_transfer');
                }
            }
            $transfer = $this->booking->book($draft);

            return $this->store->submitting($transfer->id, function () use ($transfer, $context, $source, $release, $runtime, $previous, $previousTransferId): PaymentTransferEntity {
                $this->store->lock($transfer->id);
                $existing = $this->store->get($transfer->id);
                if ($existing !== null) {
                    if ($previousTransferId !== null && ($existing->initiatorId !== $source->getUserId() || ($existing->snapshot['previousTransferId'] ?? null) !== $previousTransferId)) {
                        throw PaymentTransferApprovalException::blocked('reapplication_requires_new_transfer');
                    }

                    return $transfer;
                }
                // Only a new unexecuted transfer can acquire an approval binding.
                if ($transfer->submitAttempts !== 0 || $transfer->statusMachineState?->getTechnicalName() !== 'created') {
                    throw PaymentTransferApprovalException::blocked('transfer_already_started');
                }
                $app = $this->booking->resolveApp($transfer->paymentAppId);
                $recipient = $this->transfers->recipientOf($app, $transfer);
                $approval = new PaymentTransferApprovalEntity();
                $approval->id = $transfer->id;
                $approval->releaseId = $release->getId();
                $approval->initiatorId = (string) $source->getUserId();
                $approval->snapshot = $this->policy->snapshot($transfer, $recipient);
                if ($previous !== null) {
                    $approval->snapshot['previousTransferId'] = $previous->id;
                    $approval->snapshot['previousTransferNo'] = $previous->transferNo;
                }
                $approval->snapshot['recipientLabel'] = trim(($recipient->holderName ?? '') . ' ' . $this->presenter->recipient($recipient)['maskedIdentity']);
                $accountName = null;
                foreach ($app->accounts ?? [] as $authorization) {
                    if ($authorization->account?->id === $transfer->accountId) {
                        $accountName = $authorization->account->name;
                        break;
                    }
                }
                if ($accountName === null) {
                    throw PaymentTransferApprovalException::blocked('payment_account_unavailable');
                }
                $approval->snapshot['paymentAccountLabel'] = $accountName;
                $approval->instanceId = $runtime->start($release, $this->workflowData($transfer->amount, $transfer->currency, $approval->snapshot['recipientLabel'], $accountName, $approval->snapshot['reason']), $transfer->transferNo, $context);
                $this->store->create($approval);

                return $transfer;
            });
        });
    }

    private function assertReapplicable(string $transferId, Context $context): PaymentTransferEntity
    {
        $source = $context->getSource();
        $approval = $this->store->get($transferId);
        if ($approval === null || !$source instanceof AdminApiSource || $source->getUserId() !== $approval->initiatorId || !$context->isAllowed('payment_transfer:create')) {
            throw PaymentTransferApprovalException::blocked('reapplication_forbidden');
        }
        $transfer = $this->transfers->getById($transferId);
        $release = $this->releases->get($approval->releaseId, $context);
        $status = $this->runtimes->get($release->getPublisher())->status($release, $approval->instanceId, $context);
        if ($transfer->submitAttempts !== 0 || !\in_array($transfer->statusMachineState?->getTechnicalName(), [PaymentTransferStates::STATE_CREATED, PaymentTransferStates::STATE_CANCELED], true) || !\in_array($status->status, [WorkflowRuntimeStatus::REJECTED, WorkflowRuntimeStatus::CANCELED], true)) {
            throw PaymentTransferApprovalException::blocked('reapplication_unavailable');
        }

        return $transfer;
    }

    /**
     * @return array<string, mixed>
     */
    private function workflowData(int $amount, string $currency, string $recipient, string $account, ?string $reason): array
    {
        return ['amount' => CurrencyAmountConverter::toDecimal($amount, $currency), 'currency' => $currency, 'recipient' => $recipient, 'paymentAccount' => $account, 'reason' => $reason];
    }

    private function configuredRelease(Context $context): WorkflowReleaseEntity
    {
        $id = $this->config->getString(self::CONFIG_KEY);
        if ($id === '' || !Uuid::isValid($id)) {
            throw PaymentTransferApprovalException::blocked('workflow_not_configured');
        }
        $release = $this->releases->get($id, $context);
        $this->runtimes->get($release->getPublisher());
        $this->policy->validate($release);
        $this->events->dispatch(new WorkflowReleaseCheckEvent($release, $context));

        return $release;
    }

    private function unavailableReason(\Throwable $exception): string
    {
        if ($exception instanceof PaymentException && $exception->getErrorCode() === PaymentException::CURRENCY_NOT_SUPPORTED) {
            return 'currency_not_supported';
        }
        if ($exception instanceof WorkflowRuntimeException) {
            return 'runtime_unavailable';
        }
        if ($exception instanceof WorkflowException || $exception instanceof PaymentTransferApprovalException) {
            $reason = $exception->getParameter('reason');
            if (\is_string($reason)) {
                return $reason;
            }
        }

        return 'workflow_unavailable';
    }
}
