<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Wechat\Notification;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatNotifyAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Api\WechatApi;
use Shopwell\Core\System\Payment\Integration\Wechat\Api\WechatPlatformCertificates;
use Shopwell\Core\System\Payment\PaymentException;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;

/**
 * The two things every wechat notification needs before it may be read: proof that it comes from
 * wechat, and the plain text of the resource it carries.
 *
 * The payment notification ({@see WechatNotifyAction}) and the payout notification
 * ({@see WechatTransferNotificationVerifier}) both need exactly this pair, and until now each of them
 * implemented it on its own. There is one implementation now, on the credentials of the merchant
 * account, so the two can never drift apart - and the order cannot drift either: verify first,
 * decrypt only what verified.
 *
 * @internal
 */
#[Package('framework')]
final class WechatNotificationCodec
{
    private function __construct()
    {
    }

    /**
     * The signature covers the timestamp, the nonce and the raw body, see the wechat pay api v3 docs.
     *
     * A verification that blows up is a verification that failed, and a certificate that cannot be
     * determined proves nothing either - the platform rotates them, so the serial of the header picks
     * the one to verify against.
     *
     * @param array<string, mixed> $credentials
     * @param array<string, string> $headers the header names folded to lower case
     */
    public static function verify(array $credentials, string $rawBody, array $headers): bool
    {
        $timestamp = $headers[WechatNotifyAction::HEADER_TIMESTAMP] ?? '';
        $nonce = $headers[WechatNotifyAction::HEADER_NONCE] ?? '';
        $signature = $headers[WechatNotifyAction::HEADER_SIGNATURE] ?? '';

        if ($timestamp === '' || $nonce === '' || $signature === '') {
            return false;
        }

        $certificate = WechatPlatformCertificates::certificateOf($credentials, $headers[WechatNotifyAction::HEADER_SERIAL] ?? null);

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
            return false;
        }
    }

    /**
     * The resource of a notification, decrypted with the api v3 key of the merchant.
     *
     * A resource that cannot be decrypted is an error of this installation (a rotated or wrong key)
     * and not a bad signature, so it throws instead of answering false.
     *
     * @param array<string, mixed> $credentials
     * @param array<string, mixed> $message
     *
     * @throws PaymentException when the key is missing or the resource cannot be decrypted
     *
     * @return array<string, mixed>
     */
    public static function decryptResource(array $credentials, array $message): array
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
            throw PaymentException::notificationInvalid(WechatApi::PROVIDER, $exception->getMessage());
        }

        $decoded = json_decode($plain, true);

        if (!\is_array($decoded)) {
            throw PaymentException::notificationInvalid(WechatApi::PROVIDER, 'the decrypted resource is not a json object');
        }

        return $decoded;
    }
}
