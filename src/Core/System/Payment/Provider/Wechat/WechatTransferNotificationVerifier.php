<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Wechat;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationAck;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Transfer\Notification\PaymentTransferNotificationVerifierInterface;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;

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
        $timestamp = self::headerOf($headers, WechatNotifyAction::HEADER_TIMESTAMP);
        $nonce = self::headerOf($headers, WechatNotifyAction::HEADER_NONCE);
        $signature = self::headerOf($headers, WechatNotifyAction::HEADER_SIGNATURE);
        $serial = self::headerOf($headers, WechatNotifyAction::HEADER_SERIAL);

        if ($timestamp === '' || $nonce === '' || $signature === '') {
            return false;
        }

        $certificate = WechatPlatformCertificates::certificateOf($credentials, $serial);

        if ($certificate === null) {
            return false;
        }

        try {
            return Rsa::verify(
                $timestamp . "\n" . $nonce . "\n" . $rawBody . "\n",
                $signature,
                Rsa::from($certificate, Rsa::KEY_TYPE_PUBLIC)
            );
        } catch (\Throwable) {
            // a verification that blows up is a verification that failed
            return false;
        }
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
        $resource = $message['resource'] ?? null;

        if (!\is_array($resource) || !\is_string($resource['ciphertext'] ?? null)) {
            throw PaymentException::notificationInvalid(WechatApi::PROVIDER, 'the notification carries no encrypted resource');
        }

        $apiV3Key = $credentials[WechatApi::API_V3_KEY] ?? null;

        if (!\is_string($apiV3Key) || $apiV3Key === '') {
            throw PaymentException::missingCredentials(WechatApi::PROVIDER, WechatApi::API_V3_KEY);
        }

        try {
            $plain = AesGcm::decrypt(
                $resource['ciphertext'],
                $apiV3Key,
                \is_string($resource['nonce'] ?? null) ? $resource['nonce'] : '',
                \is_string($resource['associated_data'] ?? null) ? $resource['associated_data'] : ''
            );
        } catch (\Throwable $exception) {
            // the key is wrong or the body is broken - which of the two can only be told from the
            // notification itself, so everything that identifies it goes to the log
            $this->logger->warning('A wechat payout notification could not be decrypted.', [
                'event_type' => $message['event_type'] ?? null,
                'resource_type' => $resource['original_type'] ?? null,
                'algorithm' => $resource['algorithm'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            throw PaymentException::notificationInvalid(WechatApi::PROVIDER, $exception->getMessage());
        }

        $decoded = json_decode($plain, true);

        if (!\is_array($decoded)) {
            throw PaymentException::notificationInvalid(WechatApi::PROVIDER, 'the decrypted resource is not a json object');
        }

        return $decoded;
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    private static function headerOf(array $headers, string $name): string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== $name) {
                continue;
            }

            if (\is_array($value)) {
                $value = reset($value);
            }

            return \is_scalar($value) ? (string) $value : '';
        }

        return '';
    }
}
