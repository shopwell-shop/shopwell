<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Wechat;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\PaymentException;
use WeChatPay\Builder;
use WeChatPay\BuilderChainable;
use WeChatPay\Crypto\Rsa;

/**
 * The wechat pay api of one payment, built on the api v3.
 *
 * The sdk signs every request with the merchant private key and verifies the signature of every answer
 * with the platform certificates, so a payment is only ever accepted when it really comes from wechat.
 * The same platform certificates verify the incoming notifications, the api v3 key decrypts them.
 *
 * Everything the merchant account consists of lives in the credentials - the appid does not: it is
 * bound to the merchant id, but differs between the products of wechat, so it is a parameter of the
 * channel and read through {@see appId()}.
 *
 * @internal
 */
#[Package('framework')]
final class WechatApi extends AbstractPaymentApi
{
    final public const MCH_ID = 'mch_id';

    /**
     * The serial number of the merchant certificate.
     */
    final public const MCH_SERIAL = 'mch_serial';

    final public const MCH_PRIVATE_KEY = 'mch_private_key';

    /**
     * The wechat platform certificates as `[serial => certificate]`, needed to verify answers.
     */
    final public const PLATFORM_CERTS = 'platform_certs';

    /**
     * A single platform certificate, the counterpart of `platform_serial`.
     */
    final public const PLATFORM_CERT = 'platform_cert';

    final public const PLATFORM_SERIAL = 'platform_serial';

    /**
     * The api v3 key, needed to decrypt the notifications.
     */
    final public const API_V3_KEY = 'apiv3_key';

    private ?BuilderChainable $builder = null;

    /**
     * The appid of the channel the payment was opened with - see
     * {@see WechatGatewayFactory::PARAM_APP_ID}.
     */
    public function appId(): string
    {
        return $this->requireParam(WechatGatewayFactory::PARAM_APP_ID);
    }

    public function builder(): BuilderChainable
    {
        if ($this->builder instanceof BuilderChainable) {
            return $this->builder;
        }

        return $this->builder = Builder::factory([
            'mchid' => $this->requireCredential(self::MCH_ID),
            'serial' => $this->requireCredential(self::MCH_SERIAL),
            'privateKey' => $this->privateKey(),
            'certs' => $this->platformCerts(),
        ]);
    }

    /**
     * Calls an api v3 endpoint and returns the decoded answer.
     *
     * @param array<string, mixed> $payload
     *
     * @throws \Throwable when the channel rejects the call
     *
     * @return array<string, mixed>
     */
    public function post(string $uri, array $payload): array
    {
        $response = $this->builder()->chain($uri)->post(['json' => $payload]);
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * The platform certificate of a serial number, used to verify a notification.
     */
    public function platformCertOf(?string $serial): ?string
    {
        $certs = $this->platformCerts();

        if ($serial === null || !isset($certs[$serial])) {
            // a channel may rotate its certificates, so an unknown serial is not necessarily an attack
            return \count($certs) === 1 ? (string) reset($certs) : null;
        }

        return (string) $certs[$serial];
    }

    public function publicKeyOf(string $certificate): \OpenSSLAsymmetricKey
    {
        $key = Rsa::from($certificate, Rsa::KEY_TYPE_PUBLIC);

        if (!$key instanceof \OpenSSLAsymmetricKey) {
            throw PaymentException::invalidProviderDeclaration('The wechat platform certificate does not contain a usable public key.');
        }

        return $key;
    }

    public function apiV3Key(): string
    {
        return $this->requireCredential(self::API_V3_KEY);
    }

    /**
     * Signs a message with the merchant private key, which is what the sdk of the app verifies before
     * it hands the payment over to wechat: the jsapi and the app channel answer parameters the app
     * signs nothing about, they are signed here.
     */
    public function sign(string $message): string
    {
        return Rsa::sign($message, $this->merchantPrivateKey());
    }

    public function merchantPrivateKey(): \OpenSSLAsymmetricKey
    {
        $key = Rsa::from($this->privateKey(), Rsa::KEY_TYPE_PRIVATE);

        if (!$key instanceof \OpenSSLAsymmetricKey) {
            throw PaymentException::invalidProviderDeclaration('The wechat merchant private key does not contain a usable private key.');
        }

        return $key;
    }

    /**
     * @return array<string, string>
     */
    private function platformCerts(): array
    {
        $certs = $this->credentials()[self::PLATFORM_CERTS] ?? null;

        if (\is_string($certs)) {
            $decoded = json_decode($certs, true);
            $certs = \is_array($decoded) ? $decoded : [];
        }

        $normalized = [];
        if (\is_array($certs)) {
            foreach ($certs as $serial => $certificate) {
                if (\is_scalar($certificate) && (string) $certificate !== '') {
                    $normalized[(string) $serial] = (string) $certificate;
                }
            }
        }

        $single = $this->credentials()[self::PLATFORM_CERT] ?? null;
        $singleSerial = $this->credential(self::PLATFORM_SERIAL);

        if (\is_scalar($single) && (string) $single !== '') {
            $normalized[$singleSerial ?? 'platform'] = (string) $single;
        }

        if ($normalized === []) {
            throw PaymentException::missingCredentials($this->provider(), self::PLATFORM_CERTS);
        }

        return $normalized;
    }

    /**
     * The private key of the merchant certificate as pem, the sdk signs the requests with it.
     */
    private function privateKey(): string
    {
        $key = $this->requireCredential(self::MCH_PRIVATE_KEY);

        if (str_contains($key, '-----BEGIN')) {
            return $key;
        }

        // the key of the merchant certificate is a pkcs#8 key, only the envelope is missing
        return "-----BEGIN PRIVATE KEY-----\n" . wordwrap($key, 64, "\n", true) . "\n-----END PRIVATE KEY-----";
    }
}
