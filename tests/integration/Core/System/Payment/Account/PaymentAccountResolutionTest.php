<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Account;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Account\PaymentAccountCollection;
use Shopwell\Core\System\Payment\Api\Struct\PaymentTransferRequest;
use Shopwell\Core\System\Payment\App\PaymentAppAccountDto;
use Shopwell\Core\System\Payment\App\PaymentAppChannelDto;
use Shopwell\Core\System\Payment\App\PaymentAppDto;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentAppProvisioner;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Product\PaymentProductRegistry;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\Config\PaymentProviderConfigService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;

/**
 * @internal
 */
#[Package('framework')]
class PaymentAccountResolutionTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testMultipleAccountsOfTheSameProviderAreDistinctCandidates(): void
    {
        $app = $this->createApp();
        $resolver = static::getContainer()->get(PaymentConfigResolver::class);
        $candidates = $resolver->resolveCandidates($app, 'alipay_h5', 'CNY', null);
        static::assertCount(2, $candidates);
        static::assertNotSame($candidates[0]->accountId, $candidates[1]->accountId);
        static::assertSame(['merchant-a', 'merchant-b'], array_column(array_column($candidates, 'credentials'), 'app_id'));
        static::assertSame($candidates[1]->accountId, $resolver->resolve($app, 'alipay_h5', 'CNY', accountId: $candidates[1]->accountId)->accountId);
    }

    public function testAmbiguousMerchantSelectionRequiresAnExplicitAccount(): void
    {
        $app = $this->createApp();
        $this->expectExceptionObject(PaymentProviderException::accountSelectionRequired('alipay_h5'));
        static::getContainer()->get(PaymentConfigResolver::class)->resolve($app, 'alipay_h5', 'CNY');
    }

    public function testHistoricalExecutionKeepsItsMerchantAfterRevocationAndUsesRotatedKeys(): void
    {
        $app = $this->createApp();
        $resolver = static::getContainer()->get(PaymentConfigResolver::class);
        $selected = $resolver->resolveCandidates($app, 'alipay_h5', 'CNY', null)[0];
        $order = new PaymentOrderEntity();
        $order->id = Uuid::randomHex();
        $order->paymentAppId = $app->id;
        $attempt = new PaymentAttemptEntity();
        $attempt->accountId = $selected->accountId;
        $attempt->paymentOrderId = $order->id;
        $attempt->providerTechnicalName = 'alipay';
        $attempt->channelTechnicalName = 'alipay_h5';
        $attempt->configuration = ['params' => [], 'runtimeParams' => [], 'settings' => []];
        $order->activeAttempt = $attempt;
        static::getContainer()->get(PaymentAppProvisioner::class)->save(new PaymentAppDto(id: $app->id, technicalName: $app->technicalName, name: $app->name, accounts: []));
        /** @var EntityRepository<PaymentAccountCollection> $accounts */
        $accounts = static::getContainer()->get('payment_account.repository');
        $cipher = static::getContainer()->get(PaymentCredentialCipher::class);
        $accounts->update([['id' => $selected->accountId, 'active' => false, 'credentials' => $cipher->encrypt(['app_id' => 'merchant-a', 'private_key' => 'rotated'])]], Context::createDefaultContext());
        $resolved = $resolver->resolveForOrder($app, $order);
        static::assertSame($selected->accountId, $resolved->accountId);
        static::assertSame('rotated', $resolved->credentials['private_key']);
        static::assertSame([], $resolver->resolveCandidates($app, 'alipay_h5', 'CNY', null));
    }

    public function testPlatformProductSwitchDoesNotGateIndependentMerchantAccounts(): void
    {
        $app = $this->createApp();
        $product = static::getContainer()->get(PaymentProductRegistry::class)->get('alipay_h5');
        static::getContainer()->get(PaymentProviderConfigService::class)->saveChannel($product, false, [], []);
        static::assertCount(2, static::getContainer()->get(PaymentConfigResolver::class)->resolveCandidates($app, 'alipay_h5', 'CNY', null));
    }

    public function testPersistedPayoutKeepsItsExplicitMerchantAfterAuthorizationChanges(): void
    {
        $app = $this->createApp();
        $resolver = static::getContainer()->get(PaymentConfigResolver::class);
        $selected = $resolver->resolveCandidates($app, 'alipay_h5', 'CNY', null)[0];
        $recipientId = Uuid::randomHex();
        $cipher = static::getContainer()->get(PaymentCredentialCipher::class);
        static::getContainer()->get('payment_transfer_recipient.repository')->create([[
            'id' => $recipientId,
            'paymentAppId' => $app->id,
            'provider' => 'alipay',
            'identityType' => 'alipay_user_id',
            'identityValue' => $cipher->encryptValue('recipient'),
            'identityHash' => hash('sha256', $recipientId),
            'onboardingStatus' => PaymentTransferStates::ONBOARDING_REGISTERED,
            'payoutEnabled' => true,
        ]], Context::createDefaultContext());
        $service = static::getContainer()->get(PaymentTransferService::class);
        $transfer = $service->create($app, new PaymentTransferRequest(
            outNo: substr(Uuid::randomHex(), 0, 30),
            recipientId: $recipientId,
            provider: 'alipay',
            channel: 'alipay_h5',
            amount: 100,
            currency: 'CNY',
            accountId: $selected->accountId,
        ));
        static::assertSame($selected->accountId, $transfer->accountId);
        static::getContainer()->get(PaymentAppProvisioner::class)->save(new PaymentAppDto(id: $app->id, technicalName: $app->technicalName, name: $app->name, accounts: []));
        static::getContainer()->get('payment_account.repository')->update([[
            'id' => $selected->accountId,
            'active' => false,
            'credentials' => $cipher->encrypt(['app_id' => 'merchant-a', 'private_key' => 'rotated']),
        ]], Context::createDefaultContext());
        $resolved = $resolver->resolveForTransfer($app, $service->getById($transfer->id));
        static::assertSame($selected->accountId, $resolved->accountId);
        static::assertSame('rotated', $resolved->credentials['private_key']);
        static::assertSame([$selected->accountId], $service->accountIdsForProvider($app, 'alipay'));
    }

    private function createApp(): PaymentAppEntity
    {
        $product = static::getContainer()->get(PaymentProductRegistry::class)->get('alipay_h5');
        static::getContainer()->get(PaymentProviderConfigService::class)->saveChannel($product, true, [], []);

        $grants = [];
        foreach (['merchant-a', 'merchant-b'] as $position => $merchant) {
            $id = Uuid::randomHex();
            static::getContainer()->get('payment_account.repository')->create([[
                'id' => $id, 'providerTechnicalName' => 'alipay', 'name' => $merchant, 'active' => true, 'enabledProducts' => ['alipay_h5'],
                'credentials' => static::getContainer()->get(PaymentCredentialCipher::class)->encrypt(['app_id' => $merchant]),
            ]], Context::createDefaultContext());
            $grants[] = new PaymentAppAccountDto(accountId: $id, enabled: true, position: $position);
        }

        return static::getContainer()->get(PaymentAppProvisioner::class)->save(new PaymentAppDto(
            technicalName: 'account_test_' . Uuid::randomHex(),
            name: 'Two merchant accounts',
            active: true,
            accounts: $grants,
            channels: [new PaymentAppChannelDto(channelTechnicalName: 'alipay_h5', enabled: true)],
        ))['app'];
    }
}
