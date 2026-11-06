<?php
declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Assert;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppAccount\PaymentAppAccountCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\App\PaymentSignatureService;
use Shopwell\Core\System\Payment\Integration\Alipay\Api\AlipayApi;
use Shopwell\Core\System\Payment\Integration\Alipay\Product\AlipayProducts;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderStates;
use Shopwell\Core\System\Payment\Product\PaymentProductRegistry;
use Shopwell\Core\System\Payment\Provider\Config\PaymentProviderConfigService;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
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

    private string $paymentAccountId = '';

    private string $alipayPrivateKey = '';

    private string $alipayPublicKey = '';

    /**
     * Uses locally generated signing keys; H5 capture builds a redirect without making an HTTP call.
     */
    private function setUpAlipayPlatform(): void
    {
        $this->generateAlipayKeyPair();
        $container = static::getContainer();
        $service = $container->get(PaymentProviderConfigService::class);
        $service->saveProvider($container->get(PaymentProviderRegistry::class)->get(AlipayApi::PROVIDER), true, [
            AlipayApi::APP_ID => '9021000149657375',
            AlipayApi::PRIVATE_KEY => $this->alipayPrivateKey,
            AlipayApi::PUBLIC_KEY => $this->alipayPublicKey,
            AlipayApi::GATEWAY_URL => AlipayApi::SANDBOX_GATEWAY_URL,
        ], []);
        $service->saveChannel($container->get(PaymentProductRegistry::class)->get(AlipayProducts::H5), true, [], []);
        $config = $service->findProviderConfig(AlipayApi::PROVIDER);
        Assert::assertNotNull($config);
        $this->paymentAccountId = $config->accountId;
    }

    /**
     * Creates an app with an explicit Alipay account grant and the H5 product enabled. Its technical name and plain secret become the signing defaults of
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
            // every app the contract tests onboard is an external one: the platform tenant is the
            // only row that may carry the other type, and it is written by the migration alone
            'type' => PaymentAppEntity::TYPE_EXTERNAL,
            'appSecret' => $cipher->encryptValue($this->paymentAppSecret),
            'active' => $active,
            'returnUrl' => $returnUrl,
        ]], $context);
        $this->paymentAppId = $appId;

        if ($withProvider) {
            /** @var EntityRepository<PaymentAppAccountCollection> $appProviderRepository */
            $appProviderRepository = static::getContainer()->get('payment_app_account.repository');
            $appProviderRepository->create([[
                'paymentAppId' => $appId,
                'accountId' => $this->paymentAccountId,
                'position' => 0,
                'enabled' => true,
            ]], $context);
        }

        if ($withChannel) {
            /** @var EntityRepository<PaymentAppChannelCollection> $appChannelRepository */
            $appChannelRepository = static::getContainer()->get('payment_app_channel.repository');
            $appChannelRepository->create([[
                'paymentAppId' => $appId,
                'channelTechnicalName' => AlipayProducts::H5,
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
     * @return array{id: string, paymentNo: string, outTradeNo: string, attemptNo: string}
     */
    private function seedPaymentOrder(array $overrides = []): array
    {
        $id = Uuid::randomHex();
        $paymentNo = 'PG' . date('YmdHis') . strtoupper(bin2hex(random_bytes(4)));
        $outTradeNo = 'OT' . bin2hex(random_bytes(8));
        $provider = (string) ($overrides['providerTechnicalName'] ?? AlipayApi::PROVIDER);
        $product = (string) ($overrides['channelTechnicalName'] ?? AlipayProducts::H5);
        $accountId = (string) ($overrides['accountId'] ?? $this->paymentAccountId);
        unset($overrides['providerTechnicalName'], $overrides['channelTechnicalName'], $overrides['accountId']);

        /** @var EntityRepository<PaymentOrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('payment_order.repository');
        // the overrides win: they are what makes a test seed an order in another state, on another
        // interface or on another channel
        $orderRepository->create([$overrides + [
            'id' => $id,
            'paymentNo' => $paymentNo,
            'outTradeNo' => $outTradeNo,
            'paymentAppId' => $this->paymentAppId,
            'amount' => 100,
            'paidAmount' => 0,
            'refundedAmount' => 0,
            'currency' => 'CNY',
            'subject' => '测试商品',
            'stateId' => $this->paymentOrderStateId(PaymentOrderStates::STATE_OPENED),
            'expireAt' => new \DateTimeImmutable('+30 minutes'),
        ]], Context::createDefaultContext());

        $attemptId = Uuid::randomHex();
        /** @var EntityRepository<PaymentAttemptCollection> $attemptRepository */
        $attemptRepository = static::getContainer()->get('payment_attempt.repository');
        $attemptRepository->create([[
            'id' => $attemptId,
            'paymentOrderId' => (string) ($overrides['id'] ?? $id),
            'attemptNo' => 'p' . $attemptId,
            'accountId' => $accountId,
            'method' => $provider,
            'providerTechnicalName' => $provider,
            'channelTechnicalName' => $product,
            'interaction' => 'redirect',
            'status' => PaymentAttemptEntity::STATUS_HANDOVER,
            'context' => ['scene' => 'browser', 'device' => 'wap', 'environment' => 'browser', 'currency' => 'CNY'],
            'configuration' => ['params' => [], 'settings' => ['flow' => 'redirect'], 'runtimeParams' => []],
        ]], Context::createDefaultContext());
        $orderRepository->update([['id' => (string) ($overrides['id'] ?? $id), 'activeAttemptId' => $attemptId]], Context::createDefaultContext());

        return [
            'attemptNo' => 'p' . $attemptId,
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
