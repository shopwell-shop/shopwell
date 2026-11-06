<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Provider\Config;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Product\PaymentProductRegistry;
use Shopwell\Core\System\Payment\Provider\Config\PaymentProviderConfigService;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;

/**
 * @internal
 */
#[Package('framework')]
class PaymentProviderConfigServiceTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testAdminProviderSaveRotatesTheExplicitPlatformAccountAndRetainsHistoricalKeysOnReset(): void
    {
        $service = static::getContainer()->get(PaymentProviderConfigService::class);
        $provider = static::getContainer()->get(PaymentProviderRegistry::class)->get('alipay');
        $original = $service->findProviderConfig('alipay');
        static::assertNotNull($original);
        $service->saveProvider($provider, true, ['app_id' => 'platform-merchant'], []);
        $stored = $service->findProviderConfig('alipay');
        static::assertNotNull($stored);
        static::assertSame($original->accountId, $stored->accountId);
        $account = $service->findAccount($stored->accountId);
        static::assertNotNull($account);
        static::assertArrayHasKey(PaymentCredentialCipher::ENCRYPTED_KEY, $account->credentials ?? []);
        static::assertSame('platform-merchant', static::getContainer()->get(PaymentCredentialCipher::class)->decrypt($account->credentials)['app_id']);
        $service->resetProvider('alipay');
        $retained = $service->findAccount($stored->accountId);
        static::assertNotNull($retained);
        static::assertFalse($retained->active);
        static::assertSame(static::getContainer()->get(PaymentCredentialCipher::class)->decrypt($account->credentials), static::getContainer()->get(PaymentCredentialCipher::class)->decrypt($retained->credentials));
    }

    public function testAdminSavesCreateExplicitPlatformAccountAndProductGrants(): void
    {
        $service = static::getContainer()->get(PaymentProviderConfigService::class);
        $provider = static::getContainer()->get(PaymentProviderRegistry::class)->get('alipay');
        $service->saveProvider($provider, true, [], []);
        $service->saveChannel(static::getContainer()->get(PaymentProductRegistry::class)->get('alipay_h5'), true, [], []);
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', PaymentAppEntity::PLATFORM_TECHNICAL_NAME));
        $criteria->addAssociation('accounts.account');
        $criteria->addAssociation('channels');
        $platform = static::getContainer()->get('payment_app.repository')->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(PaymentAppEntity::class, $platform);
        $config = $service->findProviderConfig('alipay');
        static::assertNotNull($config);
        $matching = array_filter($platform->accounts ?? [], static fn ($grant): bool => $grant->accountId === $config->accountId && $grant->enabled);
        static::assertCount(1, $matching);
        $products = array_filter($platform->channels ?? [], static fn ($grant): bool => $grant->channelTechnicalName === 'alipay_h5' && $grant->enabled);
        static::assertCount(1, $products);
    }
}
