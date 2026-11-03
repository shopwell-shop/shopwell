<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Webhook;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Exception\PaymentNotificationException;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Tells an app what happened to its payment.
 *
 * The call is not made while the app waits for the api answer and never inside the notification of a
 * channel: the delivery is queued as soon as the outcome is known and the queued deliveries are sent
 * by a scheduled task, which retries with a backoff. Each attempt is persisted, so a lost notification
 * is always visible and replayable.
 *
 * Delivery has at-least-once semantics: an expired lease may be claimed again after a worker died,
 * including when the app received the request just before that worker died. The stable delivery id
 * lets the app deduplicate that repeated request.
 *
 * The body is signed with the app secret, an app verifies it with the very same HMAC the api requests
 * are signed with, see {@see PaymentSignatureService::verifyPayload()}.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentWebhookDispatcher
{
    final public const EVENT_ORDER_PAID = 'payment.order.paid';
    final public const EVENT_ORDER_CLOSED = 'payment.order.closed';
    final public const EVENT_REFUND_FINISHED = 'payment.refund.finished';

    /**
     * The subject of an event that happens once per order, e.g. the payment of the order. A refund
     * is the exception: it uses its refund id, because a refund happens once per refund and an order
     * may be refunded more than once.
     */
    final public const SUBJECT_ORDER = 'order';

    final public const HEADER_EVENT = 'X-Payment-Event';
    final public const HEADER_SIGNATURE = 'X-Payment-Signature';
    final public const HEADER_DELIVERY = 'X-Payment-Delivery-Id';

    /**
     * The number of attempts before a delivery is given up.
     */
    final public const MAX_ATTEMPTS = 5;

    /**
     * The delay in seconds between the attempts.
     */
    final public const RETRY_DELAYS = [60, 300, 900, 3600, 10800];

    private const TIMEOUT = 10;

    /**
     * Longer than the HTTP timeout, so a healthy request cannot overlap with a recovered lease.
     */
    private const CLAIM_LEASE_SECONDS = 60;

    /**
     * @param EntityRepository<PaymentWebhookDeliveryCollection> $deliveryRepository
     * @param EntityRepository<PaymentAppCollection> $paymentAppRepository
     */
    public function __construct(
        private readonly EntityRepository $deliveryRepository,
        private readonly EntityRepository $paymentAppRepository,
        private readonly PaymentWebhookSigner $signer,
        private readonly HttpClientInterface $client,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        private readonly Connection $connection,
    ) {
    }

    /**
     * Queues the delivery of an event, or returns the one that is already queued for it.
     *
     * The triple of order, event and subject identifies the delivery, so queueing the same event of
     * the same subject twice is a no-op - and that no-op is the retry mechanism: the row itself is
     * retried by {@see retryDue()} as long as it is `pending` or `failed`, and a `success` means the
     * app already knows. Re-announcing is therefore constructionally harmless, which is what lets a
     * duplicate notification of the channel announce again.
     *
     * A `giving_up` delivery is returned untouched as well: giving up is the decision that was made
     * on purpose after the last attempt and it is logged as such, resetting it on every duplicate
     * callback of the channel would contradict that very log line.
     *
     * @param array<string, mixed> $data
     * @param string $subject what the event is about - {@see self::SUBJECT_ORDER} for the events
     *                        that happen once per order, the refund id for a refund
     */
    public function enqueue(
        PaymentOrderEntity $order,
        string $event,
        array $data = [],
        string $subject = self::SUBJECT_ORDER
    ): ?PaymentWebhookDeliveryEntity {
        $url = $order->notifyUrl;

        if ($url === null || $url === '') {
            return null;
        }

        $app = $this->loadApp($order->paymentAppId);
        if (!$app instanceof PaymentAppEntity) {
            return null;
        }

        $existing = $this->findDelivery($order->id, $event, $subject);
        if ($existing instanceof PaymentWebhookDeliveryEntity) {
            return $existing;
        }

        $id = Uuid::randomHex();

        // the id is part of the signed payload, so a replayed body is detectable by it - the app
        // remembers the delivery ids it already processed, see PaymentWebhookSigner
        $signed = $this->signer->sign($id, $order, $app, $event, $data);

        try {
            $this->deliveryRepository->create([[
                'id' => $id,
                'paymentOrderId' => $order->id,
                'paymentAppId' => $order->paymentAppId,
                'event' => $event,
                'subject' => $subject,
                'url' => $url,
                'payload' => $signed['payload'],
                'signature' => $signed['signature'],
                'status' => PaymentWebhookDeliveryEntity::STATUS_PENDING,
                'attemptCount' => 0,
                'nextRetryAt' => $this->clock->now(),
            ]], Context::createDefaultContext());
        } catch (UniqueConstraintViolationException $exception) {
            // the notification of a channel and the reconciliation are two independent actors on the
            // same order and may announce at the same moment. The unique key decides who wins; the
            // loser reports the delivery the winner wrote instead of failing the whole processing.
            $existing = $this->findDelivery($order->id, $event, $subject);

            if ($existing instanceof PaymentWebhookDeliveryEntity) {
                return $existing;
            }

            throw $exception;
        }

        return $this->getDelivery($id);
    }

    /**
     * Claims a queued delivery, sends it and persists the outcome of the attempt.
     *
     * @return bool whether this worker won the claim and made the attempt
     */
    public function deliver(PaymentWebhookDeliveryEntity $delivery): bool
    {
        $claimToken = Uuid::randomHex();
        $attempt = $delivery->attemptCount + 1;

        if (!$this->claim($delivery, $claimToken)) {
            return false;
        }

        $app = $this->loadApp($delivery->paymentAppId);

        if (!$app instanceof PaymentAppEntity) {
            $this->finish($delivery, $claimToken, $attempt, null, 'The payment app does not exist anymore.', true);

            return true;
        }

        $body = $this->signer->encode($delivery->payload ?? []);

        // The claim is committed by its atomic update: no database transaction stays open during HTTP.
        try {
            $response = $this->client->request('POST', $delivery->url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    self::HEADER_EVENT => $delivery->event,
                    self::HEADER_SIGNATURE => (string) $delivery->signature,
                    self::HEADER_DELIVERY => $delivery->id,
                ],
                'body' => $body,
                'timeout' => self::TIMEOUT,
            ]);

            $status = $response->getStatusCode();
            $content = mb_substr($response->getContent(false), 0, 2000);
        } catch (\Throwable $exception) {
            $this->finish($delivery, $claimToken, $attempt, null, $exception->getMessage(), false);

            return true;
        }

        $succeeded = $status >= 200 && $status < 300;

        $this->finish($delivery, $claimToken, $attempt, $status, $content, $succeeded);

        return true;
    }

    /**
     * Sends every delivery that is due, called by the scheduled task.
     *
     * @return int the number of deliveries that were attempted
     */
    public function retryDue(int $limit = 50): int
    {
        // a date range filter compares strings, so the clock is formatted the way the storage does
        $now = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $criteria = new Criteria();
        $criteria->addFilter(new OrFilter([
            new AndFilter([
                new EqualsAnyFilter('status', [
                    PaymentWebhookDeliveryEntity::STATUS_PENDING,
                    PaymentWebhookDeliveryEntity::STATUS_FAILED,
                ]),
                new RangeFilter('nextRetryAt', [RangeFilter::LTE => $now]),
            ]),
            new AndFilter([
                new EqualsFilter('status', PaymentWebhookDeliveryEntity::STATUS_PROCESSING),
                new RangeFilter('claimExpiresAt', [RangeFilter::LTE => $now]),
            ]),
        ]));
        $criteria->setLimit($limit);

        $deliveries = $this->deliveryRepository->search($criteria, Context::createDefaultContext())->getEntities();
        $attempted = 0;

        foreach ($deliveries as $delivery) {
            if ($this->deliver($delivery)) {
                ++$attempted;
            }
        }

        return $attempted;
    }

    public function getDelivery(string $id): PaymentWebhookDeliveryEntity
    {
        $delivery = $this->deliveryRepository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();

        if (!$delivery instanceof PaymentWebhookDeliveryEntity) {
            throw PaymentNotificationException::webhookDeliveryNotFound($id);
        }

        return $delivery;
    }

    /**
     * The delivery of one event of one subject of one order, if it was queued already.
     */
    private function findDelivery(string $paymentOrderId, string $event, string $subject): ?PaymentWebhookDeliveryEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('paymentOrderId', $paymentOrderId),
            new EqualsFilter('event', $event),
            new EqualsFilter('subject', $subject)
        );
        $criteria->setLimit(1);

        $delivery = $this->deliveryRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $delivery instanceof PaymentWebhookDeliveryEntity ? $delivery : null;
    }

    private function claim(PaymentWebhookDeliveryEntity $delivery, string $claimToken): bool
    {
        $now = $this->clock->now();
        $storedNow = $now->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        // The status, due time and attempt predicate make this single update the lock: only one of
        // several workers that selected the same row can change it and receive one affected row.
        return $this->connection->executeStatement(
            'UPDATE `payment_webhook_delivery`
                SET `status` = :claimedStatus,
                    `claim_token` = :claimToken,
                    `claim_expires_at` = :claimExpiresAt,
                    `attempt_count` = `attempt_count` + 1,
                    `updated_at` = :updatedAt
                WHERE `id` = :id
                    AND `attempt_count` = :previousAttemptCount
                    AND (
                        (`status` IN (:pendingStatus, :failedStatus) AND `next_retry_at` <= :retryNow)
                        OR (`status` = :processingStatus AND `claim_expires_at` <= :claimNow)
                    )',
            [
                'claimedStatus' => PaymentWebhookDeliveryEntity::STATUS_PROCESSING,
                'claimToken' => $claimToken,
                'claimExpiresAt' => $now->modify(\sprintf('+%d seconds', self::CLAIM_LEASE_SECONDS))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'updatedAt' => $storedNow,
                'id' => Uuid::fromHexToBytes($delivery->id),
                'previousAttemptCount' => $delivery->attemptCount,
                'pendingStatus' => PaymentWebhookDeliveryEntity::STATUS_PENDING,
                'failedStatus' => PaymentWebhookDeliveryEntity::STATUS_FAILED,
                'retryNow' => $storedNow,
                'processingStatus' => PaymentWebhookDeliveryEntity::STATUS_PROCESSING,
                'claimNow' => $storedNow,
            ]
        ) === 1;
    }

    private function finish(
        PaymentWebhookDeliveryEntity $delivery,
        string $claimToken,
        int $attempt,
        ?int $status,
        ?string $content,
        bool $succeeded
    ): void {
        $givingUp = !$succeeded && $attempt >= self::MAX_ATTEMPTS;
        $now = $this->clock->now();
        $nextRetryAt = $succeeded || $givingUp ? null : $now->modify(\sprintf('+%d seconds', self::RETRY_DELAYS[min($attempt - 1, \count(self::RETRY_DELAYS) - 1)]));

        // A recovered lease belongs to another worker now. Its token fails this predicate, so the
        // late response of the old worker cannot overwrite the outcome of the newer attempt.
        $finished = $this->connection->executeStatement(
            'UPDATE `payment_webhook_delivery`
                SET `status` = :status,
                    `claim_token` = NULL,
                    `claim_expires_at` = NULL,
                    `last_http_status` = :lastHttpStatus,
                    `last_response` = :lastResponse,
                    `succeeded_at` = :succeededAt,
                    `next_retry_at` = :nextRetryAt,
                    `updated_at` = :updatedAt
                WHERE `id` = :id
                    AND `status` = :processingStatus
                    AND `claim_token` = :claimToken',
            [
                'status' => $succeeded ? PaymentWebhookDeliveryEntity::STATUS_SUCCESS : ($givingUp ? PaymentWebhookDeliveryEntity::STATUS_GIVING_UP : PaymentWebhookDeliveryEntity::STATUS_FAILED),
                'lastHttpStatus' => $status,
                'lastResponse' => $content,
                'succeededAt' => $succeeded ? $now->format(Defaults::STORAGE_DATE_TIME_FORMAT) : null,
                'nextRetryAt' => $nextRetryAt?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'updatedAt' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => Uuid::fromHexToBytes($delivery->id),
                'processingStatus' => PaymentWebhookDeliveryEntity::STATUS_PROCESSING,
                'claimToken' => $claimToken,
            ]
        );

        if ($finished === 0) {
            return;
        }

        if ($succeeded) {
            return;
        }

        if ($givingUp) {
            $this->logger->error('Giving up the payment webhook delivery to the app.', [
                'deliveryId' => $delivery->id,
                'paymentOrderId' => $delivery->paymentOrderId,
                'event' => $delivery->event,
                'url' => $delivery->url,
                'httpStatus' => $status,
            ]);

            return;
        }

        $this->logger->warning('The payment webhook delivery failed, it will be retried.', [
            'deliveryId' => $delivery->id,
            'event' => $delivery->event,
            'url' => $delivery->url,
            'attempt' => $attempt,
            'httpStatus' => $status,
        ]);
    }

    private function loadApp(string $appId): ?PaymentAppEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $appId));
        $criteria->setLimit(1);

        $app = $this->paymentAppRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $app instanceof PaymentAppEntity ? $app : null;
    }
}
