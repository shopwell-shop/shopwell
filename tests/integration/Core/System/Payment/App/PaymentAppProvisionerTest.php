<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\App;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\PaymentAppAccountDto;
use Shopwell\Core\System\Payment\App\PaymentAppChannelDto;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppDto;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentAppProvisioner;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;

/**
 * @internal
 */
#[Package('framework')]
class PaymentAppProvisionerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testRejectedChannelRollsBackTheAppAndProviderWrites(): void
    {
        $provisioner = static::getContainer()->get(PaymentAppProvisioner::class);
        $app = $this->createApp('original-secret', 'merchant-a');

        try {
            $provisioner->save(new PaymentAppDto(
                id: $app->id,
                technicalName: $app->technicalName,
                name: 'Changed name',
                active: false,
                appSecret: 'new-secret',
                accounts: [new PaymentAppAccountDto(
                    accountId: array_values($app->accounts ?? [])[0]->accountId,
                    enabled: false,
                )],
                channels: [new PaymentAppChannelDto(channelTechnicalName: 'unknown-channel')],
            ));
            static::fail('The unknown channel must reject the complete save.');
        } catch (PaymentProviderException $exception) {
            static::assertEquals(PaymentProviderException::unknownChannel('unknown-channel'), $exception);
        }

        $stored = $this->loadApp($app->id);
        static::assertSame('Original name', $stored->name);
        static::assertTrue($stored->active);
        static::assertSame('original-secret', static::getContainer()->get(PaymentCredentialCipher::class)->decryptValue($stored->appSecret));
        static::assertNull($stored->previousAppSecret);
        static::assertCount(1, $stored->accounts ?? []);
        $authorization = array_values($stored->accounts ?? [])[0];
        static::assertTrue($authorization->enabled);
        static::assertSame('merchant-a', static::getContainer()->get(PaymentCredentialCipher::class)->decrypt($authorization->account?->credentials)['app_id']);
    }

    public function testRevokingAccountGrantCannotDeleteTheBoundMerchant(): void
    {
        $source = $this->createApp('source-secret', 'merchant-b');
        $app = $this->createApp('app-secret', 'merchant-a');
        $original = array_values($app->accounts ?? [])[0]->accountId;
        $replacement = array_values($source->accounts ?? [])[0]->accountId;
        static::getContainer()->get(PaymentAppProvisioner::class)->save(new PaymentAppDto(
            id: $app->id,
            technicalName: $app->technicalName,
            name: $app->name,
            active: true,
            accounts: [new PaymentAppAccountDto(accountId: $replacement, enabled: true)],
        ));

        $historical = new PaymentOrderEntity();
        $historical->id = Uuid::randomHex();
        $historical->paymentAppId = $app->id;
        $attempt = new PaymentAttemptEntity();
        $attempt->accountId = $original;
        $attempt->paymentOrderId = $historical->id;
        $attempt->providerTechnicalName = 'alipay';
        $attempt->channelTechnicalName = 'alipay_h5';
        $attempt->configuration = ['params' => [], 'runtimeParams' => [], 'settings' => []];
        $historical->activeAttempt = $attempt;
        $resolved = static::getContainer()->get(PaymentConfigResolver::class)->resolveForOrder($app, $historical);
        static::assertSame($original, $resolved->accountId);
        static::assertSame('merchant-a', $resolved->credentials['app_id']);
        $stored = $this->loadApp($app->id);
        static::assertSame($replacement, array_values($stored->accounts ?? [])[0]->accountId);
    }

    private function createApp(string $secret, string $merchant): PaymentAppEntity
    {
        $accountId = Uuid::randomHex();
        static::getContainer()->get('payment_account.repository')->create([[
            'id' => $accountId, 'providerTechnicalName' => 'alipay', 'name' => $merchant, 'active' => true, 'enabledProducts' => ['alipay_h5'],
            'credentials' => static::getContainer()->get(PaymentCredentialCipher::class)->encrypt(['app_id' => $merchant]),
        ]], Context::createDefaultContext());

        return static::getContainer()->get(PaymentAppProvisioner::class)->save(new PaymentAppDto(
            technicalName: 'app_' . Uuid::randomHex(),
            name: 'Original name',
            active: true,
            appSecret: $secret,
            accounts: [new PaymentAppAccountDto(
                accountId: $accountId,
                enabled: true,
            )],
        ))['app'];
    }

    private function loadApp(string $id): PaymentAppEntity
    {
        /** @var EntityRepository<PaymentAppCollection> $repository */
        $repository = static::getContainer()->get('payment_app.repository');
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('accounts.account');
        $app = $repository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(PaymentAppEntity::class, $app);

        return $app;
    }
}
