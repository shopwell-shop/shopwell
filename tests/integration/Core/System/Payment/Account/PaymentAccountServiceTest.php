<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Account;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Account\Api\PaymentAccountController;
use Shopwell\Core\System\Payment\Account\PaymentAccountDto;
use Shopwell\Core\System\Payment\Account\PaymentAccountService;
use Shopwell\Core\System\Payment\App\PaymentAppAccountDto;
use Shopwell\Core\System\Payment\App\PaymentAppDto;
use Shopwell\Core\System\Payment\App\PaymentAppProvisioner;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\Struct\PaymentOrderRequest;
use Shopwell\Core\System\Payment\Provider\Config\PaymentProviderConfigService;

/**
 * @internal
 */
#[Package('framework')]
class PaymentAccountServiceTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testCentralAccountCrudSealsValuesAndRetainsEmptySecrets(): void
    {
        $service = static::getContainer()->get(PaymentAccountService::class);
        $controller = new PaymentAccountController($service);
        $context = Context::createDefaultContext();
        $created = $controller->create(new PaymentAccountDto('alipay', 'Independent merchant', credentials: ['app_id' => 'merchant-a', 'private_key' => 'original-key']), $context);
        static::assertSame(201, $created->getStatusCode());
        $data = json_decode((string) $created->getContent(), true, 512, \JSON_THROW_ON_ERROR)['account'];
        static::assertSame('merchant-a', $data['credentials']['app_id']);
        static::assertNull($data['credentials']['private_key']);
        static::assertSame('1', $data['credentials']['sandbox']);
        static::assertSame('https://openapi.alipay.com', $data['credentials']['gateway_url']);
        static::assertSame([], $data['enabledProducts']);
        static::assertFalse($data['isDefault']);
        $updated = $controller->update($data['id'], new PaymentAccountDto('alipay', 'Renamed merchant', credentials: ['private_key' => '']), $context);
        static::assertSame(200, $updated->getStatusCode());
        $account = $service->get($data['id'], $context);
        static::assertSame('Renamed merchant', $account->name);
        static::assertSame('original-key', static::getContainer()->get(PaymentCredentialCipher::class)->decrypt($account->credentials)['private_key']);
        static::assertArrayHasKey(PaymentCredentialCipher::ENCRYPTED_KEY, $account->credentials ?? []);
        static::assertSame(204, $controller->delete($data['id'], $context)->getStatusCode());
        static::assertNull(static::getContainer()->get('payment_account.repository')->search(new Criteria([$data['id']]), $context)->getEntities()->first());
    }

    public function testSandboxCanBeDisabledAndOrdinaryFieldsClearedWithoutReplacingBlankSecrets(): void
    {
        $service = static::getContainer()->get(PaymentAccountService::class);
        $context = Context::createDefaultContext();
        $account = $service->save(new PaymentAccountDto('alipay', 'Draft merchant', credentials: ['app_id' => 'merchant-a', 'private_key' => 'keep-key', 'sandbox' => '1', 'gateway_url' => 'https://merchant.example']), $context);
        $service->save(new PaymentAccountDto('alipay', 'Draft merchant', credentials: ['sandbox' => '0', 'gateway_url' => '', 'private_key' => '']), $context, $account->id);
        $values = $service->read($account->id, $context)['credentials'];
        static::assertSame('0', $values['sandbox']);
        static::assertSame('', $values['gateway_url']);
        static::assertSame('merchant-a', $values['app_id']);
        static::assertSame('keep-key', static::getContainer()->get(PaymentCredentialCipher::class)->decrypt($service->get($account->id, $context)->credentials)['private_key']);
    }

    public function testAccountWithAppGrantCannotBeDeleted(): void
    {
        $service = static::getContainer()->get(PaymentAccountService::class);
        $account = $service->save(new PaymentAccountDto('alipay', 'Granted merchant'), Context::createDefaultContext());
        static::getContainer()->get(PaymentAppProvisioner::class)->save(new PaymentAppDto(technicalName: 'grant_' . Uuid::randomHex(), name: 'App', accounts: [new PaymentAppAccountDto($account->id)]));
        $this->expectExceptionObject(PaymentProviderException::accountInUse($account->id));
        $service->delete($account->id, Context::createDefaultContext());
    }

    public function testHistoricalAttemptPreventsDeletionWithoutAnyAppGrant(): void
    {
        $context = Context::createDefaultContext();
        $service = static::getContainer()->get(PaymentAccountService::class);
        $account = $service->save(new PaymentAccountDto('alipay', 'Historical merchant'), $context);
        $app = static::getContainer()->get(PaymentAppProvisioner::class)->save(new PaymentAppDto(technicalName: 'history_' . Uuid::randomHex(), name: 'App'))['app'];
        $order = static::getContainer()->get(PaymentOrderService::class)->create($app, new PaymentOrderRequest('history-' . Uuid::randomHex(), 100, 'CNY', 'Historical payment'));
        static::getContainer()->get('payment_attempt.repository')->create([[
            'id' => Uuid::randomHex(), 'paymentOrderId' => $order->id, 'attemptNo' => 'p' . Uuid::randomHex(),
            'accountId' => $account->id, 'method' => 'alipay', 'providerTechnicalName' => 'alipay',
            'channelTechnicalName' => 'alipay_h5', 'interaction' => 'redirect', 'status' => 'pending',
            'context' => [], 'configuration' => [],
        ]], $context);
        $this->expectExceptionObject(PaymentProviderException::accountInUse($account->id));
        $service->delete($account->id, $context);
    }

    public function testPlatformDefaultAccountCannotBeDeleted(): void
    {
        $config = static::getContainer()->get(PaymentProviderConfigService::class)->findProviderConfig('alipay');
        static::assertNotNull($config);
        $this->expectExceptionObject(PaymentProviderException::accountInUse($config->accountId));
        static::getContainer()->get(PaymentAccountService::class)->delete($config->accountId, Context::createDefaultContext());
    }
}
