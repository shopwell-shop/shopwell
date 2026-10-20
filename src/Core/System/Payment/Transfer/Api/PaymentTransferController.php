<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Api;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Gateway\TransferResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Transfer\Api\Struct\PaymentTransferDraft;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferBooking;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientCollection;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferSubmitter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The administration side of a payout: book it, hand it to the channel, abandon it.
 *
 * The two moments of a payout are deliberately two endpoints. `POST /` books an intent and touches no
 * channel at all - the money does not move - while `POST /{id}/submit` is the one call that does move it.
 * Splitting them is what lets the platform require an approval step between "somebody wants to pay" and
 * "the platform pays", without inventing a second state machine.
 *
 * Three rules shape this controller:
 *
 *  1. **The submit request never names an app.** The app is derived from `transfer.paymentAppId` and from
 *     nowhere else, so "pay this payout out of another merchant account" is not a bug that can be written -
 *     the protocol has no field for it.
 *  2. **The outcome of a submission is read, never guessed.** An HTTP status can not tell "the channel
 *     refused" from "the platform never learned"; only the persisted attempt counter can. See
 *     {@see self::classifySubmitOutcome()}.
 *  3. **The controller writes nothing.** Every write goes through {@see PaymentTransferService}, the single
 *     writer of `payment_transfer`; this class only reads, orchestrates and presents.
 *
 * @internal
 */
#[Package('framework')]
class PaymentTransferController
{
    /**
     * The channel took the payout (or the state machine already booked it).
     */
    final public const OUTCOME_ACCEPTED = 'accepted';

    /**
     * The channel refused the payout and the refusal is booked on the row.
     */
    final public const OUTCOME_FAILED = 'failed';

    /**
     * The platform does not know whether the money moved. Only a human can resolve this at the channel.
     */
    final public const OUTCOME_UNKNOWN = 'unknown';

    final public const OUTCOME_CANCELED = 'canceled';

    /**
     * Nothing left the platform - the request is answered with the standard error envelope instead of
     * this outcome, it only exists inside {@see self::classifySubmitOutcome()}.
     */
    final public const OUTCOME_REJECTED = 'rejected';

    /**
     * How many recipients the create modal is offered. There is no recipient management page yet, and an
     * unbounded answer would be a denial of service against the administration itself.
     */
    private const RECIPIENT_LIMIT = 500;

    /**
     * The width of `payment_transfer.metadata.reject_reason`'s source, the same width the column of a
     * channel reason has.
     */
    private const REASON_LIMIT = 255;

    /**
     * @param EntityRepository<PaymentAppCollection> $appRepository
     * @param EntityRepository<PaymentTransferRecipientCollection> $recipientRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly EntityRepository $recipientRepository,
        private readonly PaymentTransferBooking $booking,
        private readonly PaymentTransferSubmitter $submitter,
        private readonly PaymentTransferService $transferService,
        private readonly PaymentTransferPresenter $presenter,
        private readonly PaymentProviderRegistry $providerRegistry,
    ) {
    }

    /**
     * Everything the "new transfer request" modal renders: the active apps of the shop, their payout
     * capable interfaces, the channels of those interfaces and the identity rules of each interface.
     *
     * The declarations are read on the server on purpose. Which identity type an interface can pay out to
     * is a property of the interface, and a hard coded copy in the administration would be a second truth
     * that silently rots as soon as a provider is added.
     */
    #[Route(
        path: '/api/_action/payment/transfers/options',
        name: 'api.action.payment.transfer.options',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'],
            PlatformRequest::ATTRIBUTE_ACL => ['payment_transfer:create'],
        ],
        methods: [Request::METHOD_GET]
    )]
    public function options(): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('providers');
        $criteria->addAssociation('channels');

        $apps = [];

        foreach ($this->appRepository->search($criteria, Context::createDefaultContext())->getEntities() as $app) {
            if ($app instanceof PaymentAppEntity) {
                $apps[] = $app;
            }
        }

        // "the tenant of the platform first" is not a field order: it is the account a shop pays out of
        // until somebody onboards an app of their own, and it is the one an operator looks for first
        usort($apps, static function (PaymentAppEntity $left, PaymentAppEntity $right): int {
            $leftIsPlatform = $left->type === PaymentAppEntity::TYPE_PLATFORM;
            $rightIsPlatform = $right->type === PaymentAppEntity::TYPE_PLATFORM;

            if ($leftIsPlatform !== $rightIsPlatform) {
                return $leftIsPlatform ? -1 : 1;
            }

            return strcmp($left->technicalName, $right->technicalName);
        });

        return new JsonResponse($this->presenter->options($apps, $this->providerRegistry));
    }

    /**
     * The recipients a payout can be booked to, of one app and one interface.
     *
     * This endpoint exists because the generic entity route must not be the source of this list: the
     * identity of a recipient is stored encrypted, and the entity endpoint would hand the ciphertext to the
     * browser. Here the value is decrypted, masked and the ciphertext dropped - the response never carries
     * an identity value.
     */
    #[Route(
        path: '/api/_action/payment/transfers/recipients',
        name: 'api.action.payment.transfer.recipients',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'],
            PlatformRequest::ATTRIBUTE_ACL => ['payment_transfer:create'],
        ],
        methods: [Request::METHOD_GET]
    )]
    public function recipients(Request $request): JsonResponse
    {
        $paymentAppId = trim((string) $request->query->get('paymentAppId', ''));
        $provider = trim((string) $request->query->get('provider', ''));

        if ($paymentAppId === '') {
            throw PaymentException::missingParameter('paymentAppId');
        }

        if ($provider === '') {
            throw PaymentException::missingParameter('provider');
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('paymentAppId', $paymentAppId),
            new EqualsFilter('provider', $provider),
            // a recipient the interface has not enabled for payouts must not be offered: the booking
            // would be refused later, after the operator filled the whole form
            new EqualsFilter('payoutEnabled', true)
        );
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit(self::RECIPIENT_LIMIT);

        $recipients = [];

        foreach ($this->recipientRepository->search($criteria, Context::createDefaultContext())->getEntities() as $recipient) {
            if ($recipient instanceof PaymentTransferRecipientEntity) {
                $recipients[] = $recipient;
            }
        }

        return new JsonResponse(['recipients' => $this->presenter->recipients($recipients)]);
    }

    /**
     * Books a payout in `created`. No channel is contacted, no money moves.
     *
     * The out number is the idempotency key: posting it twice returns the payout that is already booked
     * with `replayed: true` and 200, while the very first booking answers 201. A number that is already
     * taken by a payout with another recipient, amount or currency is a conflict (409) - never a silent
     * "here is somebody else's payout".
     */
    #[Route(
        path: '/api/_action/payment/transfers',
        name: 'api.action.payment.transfer.create',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'],
            PlatformRequest::ATTRIBUTE_ACL => ['payment_transfer:create'],
        ],
        methods: [Request::METHOD_POST]
    )]
    public function create(#[MapRequestPayload] PaymentTransferDraft $draft): JsonResponse
    {
        // the app is resolved before the booking so the answer can tell a created row from a replayed one
        // without loading the app a second time for the presentation below
        $app = $this->booking->resolveApp($draft->paymentAppId);
        $replayed = $draft->outNo !== null && $this->transferService->getByOutNo($app, $draft->outNo) !== null;

        $transfer = $this->booking->book($draft);

        return new JsonResponse(
            [
                'transfer' => $this->presenter->transfer($transfer, $app),
                'replayed' => $replayed,
            ],
            $replayed ? JsonResponse::HTTP_OK : JsonResponse::HTTP_CREATED
        );
    }

    /**
     * Hands a booked payout to its channel - the only call of the platform that moves money out.
     *
     * The body is not read at all: the app is `transfer.paymentAppId`, and a caller that sends an app in
     * the body is not honoured. The answer always says what the platform knows, and the three possible
     * states are distinguishable on purpose:
     *
     *  - `accepted`: the channel took the payout, the row is in the state the channel named;
     *  - `failed`: the channel refused it, the code and the reason are on the row;
     *  - `unknown`: the platform learned nothing. The row stays `created`, the attempt counter is one
     *    higher, and a human has to look at the channel before anything is retried.
     *
     * @throws \Throwable when the payout provably never left the platform - the standard error envelope
     *                    then carries the reason, and nothing was booked
     */
    #[Route(
        path: '/api/_action/payment/transfers/{id}/submit',
        name: 'api.action.payment.transfer.submit',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'],
            PlatformRequest::ATTRIBUTE_ACL => ['payment_transfer:update'],
        ],
        methods: [Request::METHOD_POST]
    )]
    public function submit(string $id): JsonResponse
    {
        $before = $this->transferService->getById($id);

        // the credentials of a payout come from the app, so the app has to be the one the payout was
        // booked for - and that is the only source this call accepts
        $app = $this->booking->resolveApp($before->paymentAppId);

        $result = null;
        $thrown = null;

        try {
            $result = $this->submitter->submit($app, $before);
        } catch (\Throwable $exception) {
            $thrown = $exception;
        }

        try {
            $after = $this->transferService->getById($id);
        } catch (\Throwable) {
            // R5 of the decision table: without the re-read the attempt counter can not be compared, so the
            // only honest answer is "the platform does not know"
            return $this->unknownResponse($before, $app);
        }

        $outcome = self::classifySubmitOutcome($result, $before, $after, $thrown);

        if ($outcome === self::OUTCOME_REJECTED && $thrown instanceof \Throwable) {
            // nothing was attempted, so nothing may be booked - the error envelope names the cause
            throw $thrown;
        }

        if ($outcome === self::OUTCOME_UNKNOWN && $result === null) {
            return $this->unknownResponse($after, $app);
        }

        return new JsonResponse([
            'outcome' => $outcome,
            'transfer' => $this->presenter->transfer($after, $app),
        ]);
    }

    /**
     * Abandons a payout that was never handed to a channel - the "reject" of the approval flow.
     *
     * The reason is mandatory: a dead row without one can not be told apart from a mistake, and this is the
     * only audit trail a rejection has (it is written to `metadata.rejectReason`, no column is added).
     *
     * Only a `created` payout can be abandoned. Anything else has been submitted, and "cancel" would mean
     * calling the channel - which is not what an approval workflow does with a mistake in the form.
     */
    #[Route(
        path: '/api/_action/payment/transfers/{id}/cancel',
        name: 'api.action.payment.transfer.cancel',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'],
            PlatformRequest::ATTRIBUTE_ACL => ['payment_transfer:update'],
        ],
        methods: [Request::METHOD_POST]
    )]
    public function cancel(string $id, Request $request): JsonResponse
    {
        $reason = $this->rejectReason($request);

        $transfer = $this->transferService->getById($id);
        $state = $transfer->statusMachineState?->getTechnicalName();

        if ($state !== PaymentTransferStates::STATE_CREATED) {
            throw PaymentException::transferAlreadyFinalized($transfer->outNo, $state ?? 'unknown');
        }

        $this->transferService->cancel($transfer, $reason);

        $after = $this->transferService->getById($id);

        return new JsonResponse([
            'outcome' => self::OUTCOME_CANCELED,
            'transfer' => $this->presenter->transfer($after),
        ]);
    }

    /**
     * What the platform learned from a submission - the truth table of the approval flow.
     *
     * Pure and static on purpose: the money critical knowledge has to be assertable without a database and
     * without a channel, and both `PaymentTransferService` and `PaymentTransferSubmitter` are final and can
     * not be doubled. A status code is explicitly **not** the input: `PaymentException::gatewayError()` is a
     * 502 by which the money may have moved, while `transferAlreadyFinalized` is a 409 by which it certainly
     * did not - only the persisted attempt counter separates the two.
     *
     * @param TransferResult|null $result the answer of the channel, `null` when the call threw
     * @param PaymentTransferEntity $before the row as it was read before the submission
     * @param PaymentTransferEntity $after the row as it was re-read afterwards
     * @param \Throwable|null $error the exception the submission threw, if any
     */
    public static function classifySubmitOutcome(
        ?TransferResult $result,
        PaymentTransferEntity $before,
        PaymentTransferEntity $after,
        ?\Throwable $error,
    ): string {
        $stateAfter = $after->statusMachineState?->getTechnicalName();

        // the payout was not submittable in the first place: the submitter refuses it before a gateway is
        // built, so nothing was attempted and the caller has to learn why - not "accepted"
        if ($before->statusMachineState?->getTechnicalName() !== PaymentTransferStates::STATE_CREATED) {
            return self::OUTCOME_REJECTED;
        }

        if ($result !== null) {
            if ($stateAfter === PaymentTransferStates::STATE_FAILED) {
                return self::OUTCOME_FAILED;
            }

            // the channel answered something the platform could not book (an unclassifiable answer without
            // an object reference): nothing moved as far as the platform knows
            if ($stateAfter === PaymentTransferStates::STATE_CREATED) {
                return self::OUTCOME_UNKNOWN;
            }

            return self::OUTCOME_ACCEPTED;
        }

        // the state machine already moved on, so the attempt reached the platform and was booked - whatever
        // failed afterwards happened after the money was handed over
        if ($stateAfter !== null && $stateAfter !== PaymentTransferStates::STATE_CREATED) {
            return self::OUTCOME_ACCEPTED;
        }

        if (!$error instanceof \Throwable) {
            return self::OUTCOME_UNKNOWN;
        }

        // the submitter records an attempt only after the gateway was built, i.e. only when the request may
        // have left the platform. An unchanged counter therefore means "provably nothing happened".
        if ($after->submitAttempts === $before->submitAttempts) {
            return self::OUTCOME_REJECTED;
        }

        return self::OUTCOME_UNKNOWN;
    }

    /**
     * The "the platform does not know" answer: 200 with an explicit outcome, because a 5xx would invite a
     * retry of a payout that may already have been paid out.
     */
    private function unknownResponse(PaymentTransferEntity $transfer, PaymentAppEntity $app): JsonResponse
    {
        return new JsonResponse([
            'outcome' => self::OUTCOME_UNKNOWN,
            'transfer' => $this->presenter->transfer($transfer, $app),
            'message' => 'The channel did not answer a final result. The money may already be on its way - check the out number at the channel before retrying.',
        ]);
    }

    /**
     * The reason a payout is abandoned. A body without a usable reason is refused: the reason is the only
     * audit trail of a rejection.
     */
    private function rejectReason(Request $request): string
    {
        $payload = $request->request->all();

        if ($payload === []) {
            $content = $request->getContent();
            $decoded = $content === '' ? null : json_decode($content, true);

            if (\is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $reason = $payload['reason'] ?? null;

        if (!\is_scalar($reason) || trim((string) $reason) === '' || mb_strlen(trim((string) $reason)) > self::REASON_LIMIT) {
            throw PaymentException::missingParameter('reason');
        }

        return trim((string) $reason);
    }
}
