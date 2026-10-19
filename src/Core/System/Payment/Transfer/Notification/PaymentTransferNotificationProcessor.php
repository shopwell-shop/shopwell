<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer\Notification;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Gateway\TransferQueryResult;
use Shopwell\Core\System\Payment\Gateway\TransferResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationCollection;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationEntity;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferReconciler;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;

/**
 * Processes the payout notifications of the interfaces.
 *
 * A payout notification is a **trigger**, not a source of truth. It is verified, the payout number is
 * read from the signed body, and then the interface is asked what became of that payout through
 * {@see \Shopwell\Core\System\Payment\Transfer\PaymentTransferReconciler}. Nothing of the body is
 * mapped into a state here: the mapping lives in the query actions of the four interfaces, is tested
 * there, and would drift the moment it was written a second time. Payouts are rare enough that the
 * extra query costs nothing next to a second, divergent interpretation of the money.
 *
 * The order of the steps is the one of the payment notification and matters for the same reasons:
 *
 *  1. the notification is stored before it is looked at, so it can never be lost,
 *  2. the credentials of the app are resolved and the interface verifies the body,
 *  3. only then is the payout number read and the payout looked up,
 *  4. and only then is anything written to the payout - by the reconciler, never here.
 *
 * The url names the app and not the payout: stripe, paypal and wechat notify a merchant account, so
 * the payout number has to come from the body. Wechat encrypts that body, which is the second reason
 * the app has to be known before anything can be read.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentTransferNotificationProcessor
{
    /**
     * The headers that are never stored.
     */
    private const HIDDEN_HEADERS = ['authorization', 'cookie', 'proxy-authorization'];

    /**
     * @param EntityRepository<PaymentNotificationCollection> $notificationRepository
     * @param EntityRepository<PaymentAppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $notificationRepository,
        private readonly EntityRepository $appRepository,
        private readonly PaymentTransferNotificationVerifierRegistry $verifierRegistry,
        private readonly PaymentConfigResolver $configResolver,
        private readonly PaymentTransferService $transferService,
        private readonly PaymentTransferReconciler $reconciler,
    ) {
    }

    /**
     * @param array<string, string|list<string>> $headers
     *
     * @throws PaymentException
     */
    public function process(string $provider, string $appId, string $rawBody, array $headers, ?string $remoteIp): void
    {
        $app = $this->app($appId);

        $notificationId = $this->recordReceived($provider, $rawBody, $headers, $remoteIp);

        $state = [
            'result' => PaymentNotificationEntity::RESULT_ERROR,
            'signatureValid' => false,
            'transferId' => null,
            'message' => null,
        ];

        try {
            $this->handle($app, $provider, $rawBody, $headers, $state);
        } catch (\Throwable $exception) {
            $state['message'] = $exception->getMessage();

            if ($exception instanceof PaymentException) {
                throw $exception;
            }

            throw PaymentException::gatewayError($provider, $exception->getMessage(), $exception);
        } finally {
            $this->conclude($notificationId, $state);
        }
    }

    /**
     * What a payout answer means for the notification row. `unknown` is the answer of a channel that
     * said nothing usable - it is no success and no failure, so the row records that it was ignored
     * instead of claiming an outcome the platform does not have.
     */
    public static function resultOf(TransferQueryResult $result): string
    {
        return $result->state === TransferResult::STATE_UNKNOWN
            ? PaymentNotificationEntity::RESULT_IGNORED
            : PaymentNotificationEntity::RESULT_OK;
    }

    /**
     * @param array<string, string|list<string>> $headers
     * @param array{result: string, signatureValid: bool, transferId: string|null, message: string|null} $state
     *
     * @throws PaymentException
     */
    private function handle(PaymentAppEntity $app, string $provider, string $rawBody, array $headers, array &$state): void
    {
        $verifier = $this->verifierRegistry->get($provider);
        $credentials = $this->configResolver->resolveProviderCredentials($app, $provider);

        if (!$verifier->verify($credentials, $rawBody, $headers)) {
            $state['result'] = PaymentNotificationEntity::RESULT_INVALID_SIGNATURE;

            return;
        }

        $state['signatureValid'] = true;

        $outNo = $verifier->outNoOf($credentials, $rawBody, $headers);

        if ($outNo === null) {
            $state['result'] = PaymentNotificationEntity::RESULT_UNMATCHED;

            return;
        }

        $transfer = $this->transferService->getByOutNo($app, $outNo);

        if (!$transfer instanceof PaymentTransferEntity) {
            // a valid notification of a payout this app does not have: the business number is the
            // caller's, so either it was deleted or it belongs to another merchant account
            $state['result'] = PaymentNotificationEntity::RESULT_UNMATCHED;

            return;
        }

        $state['transferId'] = $transfer->id;

        $state['result'] = self::resultOf($this->reconciler->reconcile($app, $transfer));
    }

    /**
     * @throws PaymentException
     */
    private function app(string $appId): PaymentAppEntity
    {
        $app = $this->appRepository
            ->search(new Criteria([$appId]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        if (!$app instanceof PaymentAppEntity) {
            throw PaymentException::transferNotificationAppUnknown($appId);
        }

        return $app;
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    private function recordReceived(string $provider, string $rawBody, array $headers, ?string $remoteIp): string
    {
        $id = Uuid::randomHex();

        $this->notificationRepository->create([[
            'id' => $id,
            'providerTechnicalName' => $provider,
            'rawBody' => $rawBody,
            'headers' => $this->sanitizeHeaders($headers),
            'remoteIp' => $remoteIp,
            'signatureValid' => false,
            'processed' => false,
        ]], Context::createDefaultContext());

        return $id;
    }

    /**
     * @param array{result: string, signatureValid: bool, transferId: string|null, message: string|null} $state
     */
    private function conclude(string $notificationId, array $state): void
    {
        $this->notificationRepository->update([[
            'id' => $notificationId,
            'paymentTransferId' => $state['transferId'],
            'signatureValid' => $state['signatureValid'],
            // the same meaning the payment notifications give the flag: nothing is left to do with
            // this row. A rejected signature or an unmatched payout stays false, so support can find
            // them the way it finds the payment ones
            'processed' => \in_array($state['result'], [
                PaymentNotificationEntity::RESULT_OK,
                PaymentNotificationEntity::RESULT_DUPLICATE,
                PaymentNotificationEntity::RESULT_IGNORED,
            ], true),
            'processResult' => mb_substr($state['result'], 0, 32),
            'errorMessage' => $state['message'] === null ? null : mb_substr($state['message'], 0, 512),
        ]], Context::createDefaultContext());
    }

    /**
     * @param array<string, string|list<string>> $headers
     *
     * @return array<string, string|list<string>>
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sanitized = [];

        foreach ($headers as $name => $value) {
            $key = strtolower((string) $name);
            $sanitized[$key] = \in_array($key, self::HIDDEN_HEADERS, true) ? '[hidden]' : $value;
        }

        return $sanitized;
    }
}
