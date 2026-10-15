<?php
declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Assert;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\App\PaymentSignatureService;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderStates;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayApi;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigCollection;
use Shopwell\Core\System\Payment\Provider\PaymentProviderConfigCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared fixtures and signature helpers of the payment api contract tests.
 *
 * The signature helper deliberately reimplements the algorithm of
 * {@see PaymentSignatureService} instead of calling it: the tests freeze the contract an external app
 * implements against, so a change of the production algorithm has to break these tests.
 */
trait PaymentApiFixtureTrait
{
    private string $paymentAppId = '';

    private string $paymentAppTechnicalName = '';

    private string $paymentAppSecret = '';

    private string $alipayPrivateKey = '';

    private string $alipayPublicKey = '';

    /**
     * The platform side of alipay: a provider config with a fresh rsa key pair and the h5 channel
     * switched on. Any seeded platform credentials are overwritten on purpose: the tests must sign
     * and verify locally and never talk to a real gateway. The h5 capture only builds the redirect
     * url, so no http call leaves the process.
     */
    private function setUpAlipayPlatform(): void
    {
        $this->generateAlipayKeyPair();

        $cipher = static::getContainer()->get(PaymentCredentialCipher::class);
        $context = Context::createDefaultContext();

        /** @var EntityRepository<PaymentProviderConfigCollection> $providerRepository */
        $providerRepository = static::getContainer()->get('payment_provider_config.repository');
        $existingProvider = $providerRepository->searchIds(
            (new Criteria())
                ->addFilter(new EqualsFilter('providerTechnicalName', AlipayApi::PROVIDER))
                ->setLimit(1),
            $context
        )->firstId();

        $providerData = [
            'providerTechnicalName' => AlipayApi::PROVIDER,
            'enabled' => true,
            'credentials' => $cipher->encrypt([
                AlipayApi::APP_ID => '9021000149657375',
                AlipayApi::PRIVATE_KEY => $this->alipayPrivateKey,
                AlipayApi::PUBLIC_KEY => $this->alipayPublicKey,
                AlipayApi::GATEWAY_URL => AlipayApi::SANDBOX_GATEWAY_URL,
            ]),
            'settings' => ['http_method' => 'GET'],
        ];

        if ($existingProvider !== null) {
            $providerRepository->update([['id' => $existingProvider] + $providerData], $context);
        } else {
            $providerRepository->create([$providerData], $context);
        }

        /** @var EntityRepository<PaymentChannelConfigCollection> $channelRepository */
        $channelRepository = static::getContainer()->get('payment_channel_config.repository');
        $existingChannel = $channelRepository->searchIds(
            (new Criteria())
                ->addFilter(
                    new EqualsFilter('providerTechnicalName', AlipayApi::PROVIDER),
                    new EqualsFilter('channelTechnicalName', AlipayApi::CHANNEL_H5)
                )
                ->setLimit(1),
            $context
        )->firstId();

        if ($existingChannel !== null) {
            $channelRepository->update([['id' => $existingChannel, 'enabled' => true]], $context);
        } else {
            $channelRepository->create([[
                'providerTechnicalName' => AlipayApi::PROVIDER,
                'channelTechnicalName' => AlipayApi::CHANNEL_H5,
                'enabled' => true,
            ]], $context);
        }
    }

    /**
     * Creates an app that is authorized for alipay with the platform credentials and has the h5
     * channel enabled. Its technical name and plain secret become the signing defaults of
     * {@see signedPayload()}.
     */
    private function createContractApp(
        bool $active = true,
        ?string $returnUrl = null,
        bool $withProvider = true,
        bool $withChannel = true
    ): void {
        $this->paymentAppTechnicalName = 'contract_app_' . bin2hex(random_bytes(6));
        $this->paymentAppSecret = 'secret_' . bin2hex(random_bytes(16));

        $cipher = static::getContainer()->get(PaymentCredentialCipher::class);
        $context = Context::createDefaultContext();

        /** @var EntityRepository<PaymentAppCollection> $appRepository */
        $appRepository = static::getContainer()->get('payment_app.repository');

        $appId = Uuid::randomHex();
        $appRepository->create([[
            'id' => $appId,
            'technicalName' => $this->paymentAppTechnicalName,
            'name' => 'Payment api contract test app',
            'appSecret' => $cipher->encryptValue($this->paymentAppSecret),
            'active' => $active,
            'returnUrl' => $returnUrl,
        ]], $context);
        $this->paymentAppId = $appId;

        if ($withProvider) {
            /** @var EntityRepository<PaymentAppProviderCollection> $appProviderRepository */
            $appProviderRepository = static::getContainer()->get('payment_app_provider.repository');
            $appProviderRepository->create([[
                'paymentAppId' => $appId,
                'providerTechnicalName' => AlipayApi::PROVIDER,
                'enabled' => true,
                'mode' => PaymentAppProviderEntity::MODE_PLATFORM,
            ]], $context);
        }

        if ($withChannel) {
            /** @var EntityRepository<PaymentAppChannelCollection> $appChannelRepository */
            $appChannelRepository = static::getContainer()->get('payment_app_channel.repository');
            $appChannelRepository->create([[
                'paymentAppId' => $appId,
                'channelTechnicalName' => AlipayApi::CHANNEL_H5,
                'enabled' => true,
            ]], $context);
        }
    }

    /**
     * Adds the four auth parameters to a business payload and signs it the way an app does.
     *
     * @param array<string, mixed> $businessParams
     *
     * @return array<string, mixed>
     */
    private function signedPayload(
        string $method,
        string $path,
        array $businessParams = [],
        ?string $appId = null,
        ?string $secret = null,
        ?int $timestamp = null,
        ?string $nonce = null
    ): array {
        $resolvedAppId = $appId ?? $this->paymentAppTechnicalName;
        $resolvedTimestamp = $timestamp ?? time();
        $resolvedNonce = $nonce ?? bin2hex(random_bytes(16));

        $params = $businessParams;
        $params[PaymentSignatureService::APP_ID] = $resolvedAppId;
        $params[PaymentSignatureService::TIMESTAMP] = $resolvedTimestamp;
        $params[PaymentSignatureService::NONCE] = $resolvedNonce;
        $params[PaymentSignatureService::SIGN] = $this->signature(
            $secret ?? $this->paymentAppSecret,
            $resolvedAppId,
            $resolvedTimestamp,
            $resolvedNonce,
            $method,
            $path,
            $params
        );

        return $params;
    }

    /**
     * The signature of the api: base64(hmac_sha256(secret, appId, timestamp, nonce, method, path and
     * the canonical json of the business parameters joined by newlines)).
     *
     * @param array<string, mixed> $params the full payload including the auth parameters
     */
    private function signature(
        string $secret,
        string $appId,
        int $timestamp,
        string $nonce,
        string $method,
        string $path,
        array $params
    ): string {
        foreach (PaymentSignatureService::authParameters() as $authParameter) {
            unset($params[$authParameter]);
        }
        ksort($params);

        $content = implode("\n", [
            $appId,
            (string) $timestamp,
            $nonce,
            strtoupper($method),
            $path,
            (string) json_encode($params, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
        ]);

        return base64_encode(hash_hmac('sha256', $content, $secret, true));
    }

    private function getBrowser(): KernelBrowser
    {
        return KernelLifecycleManager::createBrowser(KernelLifecycleManager::getKernel(), false);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postJson(KernelBrowser $client, string $path, array $payload): void
    {
        $client->request('POST', $path, [], [], ['CONTENT_TYPE' => 'application/json'], (string) json_encode($payload));
    }

    /**
     * Seeds a payment order of the contract app directly through the repository, for the states the
     * create endpoint cannot produce - an order without the capture attempt of the create, or one the
     * endpoint would refuse to answer. `paidAmount` and `refundedAmount` have to be part of the
     * payload: the dal marks them as required because the entity properties are not nullable.
     *
     * @param array<string, mixed> $overrides dal payload of the order, valid defaults are merged in
     *
     * @return array{id: string, paymentNo: string, outTradeNo: string}
     */
    private function seedPaymentOrder(array $overrides = []): array
    {
        $id = Uuid::randomHex();
        $paymentNo = 'PG' . date('YmdHis') . strtoupper(bin2hex(random_bytes(4)));
        $outTradeNo = 'OT' . bin2hex(random_bytes(8));

        /** @var EntityRepository<PaymentOrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('payment_order.repository');
        $orderRepository->create([[
            'id' => $id,
            'paymentNo' => $paymentNo,
            'outTradeNo' => $outTradeNo,
            'paymentAppId' => $this->paymentAppId,
            'providerTechnicalName' => AlipayApi::PROVIDER,
            'channelTechnicalName' => AlipayApi::CHANNEL_H5,
            'amount' => 100,
            'paidAmount' => 0,
            'refundedAmount' => 0,
            'currency' => 'CNY',
            'subject' => '测试商品',
            'stateId' => $this->paymentOrderStateId(PaymentOrderStates::STATE_OPENED),
            'expireAt' => new \DateTimeImmutable('+30 minutes'),
        ] + $overrides], Context::createDefaultContext());

        return [
            'id' => (string) ($overrides['id'] ?? $id),
            'paymentNo' => (string) ($overrides['paymentNo'] ?? $paymentNo),
            'outTradeNo' => (string) ($overrides['outTradeNo'] ?? $outTradeNo),
        ];
    }

    private function paymentOrderStateId(string $state): string
    {
        $id = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(s.id)) FROM state_machine_state s
             INNER JOIN state_machine m ON m.id = s.state_machine_id
             WHERE m.technical_name = :machine AND s.technical_name = :state',
            ['machine' => PaymentOrderStates::STATE_MACHINE, 'state' => $state]
        );
        Assert::assertIsString($id);

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeResponse(Response $response): array
    {
        $content = $response->getContent();
        Assert::assertIsString($content);

        $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        Assert::assertIsArray($decoded);

        return $decoded;
    }

    private static function assertError(Response $response, int $status, string $code): void
    {
        Assert::assertSame($status, $response->getStatusCode(), (string) $response->getContent());

        $decoded = self::decodeResponse($response);
        Assert::assertArrayHasKey('errors', $decoded);
        Assert::assertSame($code, $decoded['errors'][0]['code'] ?? null);
    }

    private function generateAlipayKeyPair(): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => \OPENSSL_KEYTYPE_RSA,
        ]);
        Assert::assertNotFalse($key);

        $privateKey = null;
        Assert::assertTrue(openssl_pkey_export($key, $privateKey));
        Assert::assertIsString($privateKey);
        $this->alipayPrivateKey = $privateKey;

        $details = openssl_pkey_get_details($key);
        Assert::assertNotFalse($details);
        $this->alipayPublicKey = $details['key'];
    }
}
