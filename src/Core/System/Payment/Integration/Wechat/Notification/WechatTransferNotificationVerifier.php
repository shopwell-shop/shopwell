<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Wechat\Notification;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Integration\Wechat\Api\WechatApi;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationAck;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierInterface;

/**
 * Verifies the `MCHTRANSFER.BILL.FINISHED` notification of a merchant transfer.
 *
 * Wechat signs the notification with the private key of the platform and encrypts the resource with
 * the api v3 key of the merchant. The signature covers the timestamp, the nonce and the raw body, and
 * it is verified with the platform certificate the `Wechatpay-Serial` header names - the same shape
 * the payment notification has.
 *
 * The payout number is *inside* the encrypted resource: the notification is account level and names
 * no payout in a readable part, which is why the resource is decrypted before the number can be read.
 * Only the number is read - the state of the payout is asked for with a query, never taken from a
 * notification.
 *
 * A resource that cannot be decrypted is an error of this installation (a rotated or wrong api v3 key)
 * and not a bad signature, so it throws instead of answering false, and it is logged with the event
 * type of the notification and the shape of the resource - without them a support case cannot tell a
 * wrong key from a truncated body.
 *
 * @internal
 */
#[Package('framework')]
final class WechatTransferNotificationVerifier implements PaymentTransferNotificationVerifierInterface
{
    /**
     * The payout number of the platform, as it is written in the decrypted resource.
     */
    private const OUT_NO = 'out_bill_no';

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function provider(): string
    {
        return WechatApi::PROVIDER;
    }

    public function verify(array $credentials, string $rawBody, array $headers): bool
    {
        return WechatNotificationCodec::verify($credentials, $rawBody, self::normalizedHeaders($headers));
    }

    public function outNoOf(array $credentials, string $rawBody, array $headers): ?string
    {
        $message = json_decode($rawBody, true);
        $message = \is_array($message) ? $message : [];

        $outNo = $this->decryptedResource($credentials, $message)[self::OUT_NO] ?? null;

        return \is_string($outNo) && $outNo !== '' ? $outNo : null;
    }

    /**
     * Wechat pay repeats a notification unless it is acknowledged with http 200 and this body.
     */
    public function acknowledge(): PaymentNotificationAck
    {
        return new PaymentNotificationAck(
            '{"code":"SUCCESS","message":"成功"}',
            'application/json; charset=utf-8'
        );
    }

    /**
     * @param array<string, mixed> $credentials
     * @param array<string, mixed> $message
     *
     * @throws PaymentException when the key is missing or the resource cannot be decrypted
     *
     * @return array<string, mixed>
     */
    private function decryptedResource(array $credentials, array $message): array
    {
        try {
            return WechatNotificationCodec::decryptResource($credentials, $message);
        } catch (PaymentException $exception) {
            $resource = $message['resource'] ?? null;

            if (\is_array($resource)) {
                // the key is wrong or the body is broken - which of the two can only be told from the
                // notification itself, so everything that identifies it goes to the log
                $this->logger->warning('A wechat payout notification could not be decrypted.', [
                    'event_type' => $message['event_type'] ?? null,
                    'resource_type' => $resource['original_type'] ?? null,
                    'algorithm' => $resource['algorithm'] ?? null,
                    'error' => $exception->getMessage(),
                ]);
            }

            throw $exception;
        }
    }

    /**
     * Header names are case insensitive and a header may arrive more than once, so they are folded to
     * the lower case form the codec reads, with the first value of a repeated header.
     *
     * @param array<string, string|list<string>> $headers
     *
     * @return array<string, string>
     */
    private static function normalizedHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            if (\is_array($value)) {
                $value = reset($value);
            }

            if (\is_scalar($value)) {
                $normalized[strtolower(str_replace('_', '-', (string) $name))] = (string) $value;
            }
        }

        return $normalized;
    }
}
