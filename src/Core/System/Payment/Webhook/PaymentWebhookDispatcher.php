<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Webhook;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Tells an app what happened to its payment.
 *
 * The call is not made while the app waits for the api answer and never inside the notification of a
 * channel: the delivery is queued as soon as the outcome is known and the queued deliveries are sent
 * by a scheduled task, which retries with a backoff. Each attempt is persisted, so a lost notification
 * is always visible and replayable.
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
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function enqueue(PaymentOrderEntity $order, string $event, array $data = []): ?PaymentWebhookDeliveryEntity
    {
        $url = $order->notifyUrl;

        if ($url === null || $url === '') {
            return null;
        }

        $app = $this->loadApp($order->paymentAppId);
        if (!$app instanceof PaymentAppEntity) {
            return null;
        }

        $id = Uuid::randomHex();

        // the id is part of the signed payload, so a replayed body is detectable by it - the app
        // remembers the delivery ids it already processed, see PaymentWebhookSigner
        $signed = $this->signer->sign($id, $order, $app, $event, $data);

        $this->deliveryRepository->create([[
            'id' => $id,
            'paymentOrderId' => $order->id,
            'paymentAppId' => $order->paymentAppId,
            'event' => $event,
            'url' => $url,
            'payload' => $signed['payload'],
            'signature' => $signed['signature'],
            'status' => PaymentWebhookDeliveryEntity::STATUS_PENDING,
            'attemptCount' => 0,
            'nextRetryAt' => $this->clock->now(),
        ]], Context::createDefaultContext());

        return $this->getDelivery($id);
    }

    /**
     * Sends a queued delivery and persists the outcome of the attempt.
     */
    public function deliver(PaymentWebhookDeliveryEntity $delivery): void
    {
        $app = $this->loadApp($delivery->paymentAppId);
        $attempt = $delivery->attemptCount + 1;

        if (!$app instanceof PaymentAppEntity) {
            $this->finish($delivery, $attempt, null, 'The payment app does not exist anymore.', true);

            return;
        }

        $body = $this->signer->encode($delivery->payload ?? []);

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
            $this->finish($delivery, $attempt, null, $exception->getMessage(), false);

            return;
        }

        $succeeded = $status >= 200 && $status < 300;

        $this->finish($delivery, $attempt, $status, $content, $succeeded);
    }

    /**
     * Sends every delivery that is due, called by the scheduled task.
     *
     * @return int the number of deliveries that were attempted
     */
    public function retryDue(int $limit = 50): int
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('status', [
            PaymentWebhookDeliveryEntity::STATUS_PENDING,
            PaymentWebhookDeliveryEntity::STATUS_FAILED,
        ]));
        // a date range filter compares strings, so the clock is formatted the way the storage does
        $criteria->addFilter(new RangeFilter('nextRetryAt', [RangeFilter::LTE => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT)]));
        $criteria->setLimit($limit);

        $deliveries = $this->deliveryRepository->search($criteria, Context::createDefaultContext())->getEntities();

        foreach ($deliveries as $delivery) {
            $this->deliver($delivery);
        }

        return $deliveries->count();
    }

    public function getDelivery(string $id): PaymentWebhookDeliveryEntity
    {
        $delivery = $this->deliveryRepository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();

        if (!$delivery instanceof PaymentWebhookDeliveryEntity) {
            throw PaymentException::webhookDeliveryNotFound($id);
        }

        return $delivery;
    }

    private function finish(
        PaymentWebhookDeliveryEntity $delivery,
        int $attempt,
        ?int $status,
        ?string $content,
        bool $succeeded
    ): void {
        $givingUp = !$succeeded && $attempt >= self::MAX_ATTEMPTS;

        $this->deliveryRepository->update([[
            'id' => $delivery->id,
            'status' => $succeeded ? PaymentWebhookDeliveryEntity::STATUS_SUCCESS : ($givingUp ? PaymentWebhookDeliveryEntity::STATUS_GIVING_UP : PaymentWebhookDeliveryEntity::STATUS_FAILED),
            'attemptCount' => $attempt,
            'lastHttpStatus' => $status,
            'lastResponse' => $content,
            'succeededAt' => $succeeded ? $this->clock->now() : null,
            'nextRetryAt' => $succeeded || $givingUp ? null : $this->clock->now()->modify(\sprintf('+%d seconds', self::RETRY_DELAYS[min($attempt - 1, \count(self::RETRY_DELAYS) - 1)])),
        ]], Context::createDefaultContext());

        if ($succeeded) {
            return;
        }

        if ($givingUp) {
            $this->logger->error('Giving up the payment webhook delivery to the app.', [
                'deliveryId' => $delivery->id,
                'paymentNo' => $delivery->id,
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
