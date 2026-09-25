<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Maintenance\System\Service;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Maintenance\System\Service\ShopConfigurator;
use Shopwell\Core\System\Currency\CurrencyCollection;
use Shopwell\Core\System\Language\LanguageCollection;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Intl\Currencies;

/**
 * @internal
 */
#[Package('framework')]
class ShopConfiguratorTest extends TestCase
{
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private ShopConfigurator $shopConfigurator;

    private SystemConfigService $systemConfigService;

    /**
     * @var EntityRepository<LanguageCollection>
     */
    private EntityRepository $langRepo;

    /**
     * @var EntityRepository<CurrencyCollection>
     */
    private EntityRepository $currencyRepo;

    protected function setUp(): void
    {
        $this->shopConfigurator = static::getContainer()->get(ShopConfigurator::class);
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $this->langRepo = static::getContainer()->get('language.repository');
        $this->currencyRepo = static::getContainer()->get('currency.repository');
    }

    public function testUpdateBasicInformation(): void
    {
        $this->shopConfigurator->updateBasicInformation('test-shop', 'shop@test.com');

        static::assertSame('test-shop', $this->systemConfigService->get('core.basicInformation.shopName'));
        static::assertSame('shop@test.com', $this->systemConfigService->get('core.basicInformation.email'));
    }

    public function testSwitchLanguageWithNewLanguage(): void
    {
        $this->shopConfigurator->setDefaultLanguage('es-ES');

        $lang = $this->langRepo->search(new Criteria([Defaults::LANGUAGE_SYSTEM]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($lang);
        static::assertSame('Spanish', $lang->getName());
    }

    public function testSwitchLanguageWithDefaultLocale(): void
    {
        $this->shopConfigurator->setDefaultLanguage('en-GB');

        $lang = $this->langRepo->search(new Criteria([Defaults::LANGUAGE_SYSTEM]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($lang);
        static::assertSame('English', $lang->getName());
    }

    public function testSwitchLanguageWithExistingLanguage(): void
    {
        $this->shopConfigurator->setDefaultLanguage('de-DE');

        $lang = $this->langRepo->search(new Criteria([Defaults::LANGUAGE_SYSTEM]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($lang);
        static::assertSame('Deutsch', $lang->getName());
    }

    public function testSwitchDefaultCurrencyWithNewCurrency(): void
    {
        $this->shopConfigurator->setDefaultCurrency('RUB');

        $currency = $this->currencyRepo->search(new Criteria([Defaults::CURRENCY]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($currency);
        static::assertSame('RUB', $currency->getSymbol());
        // Derived from the same source as ShopConfigurator::createNewCurrency() so the
        // expectation tracks CLDR data updates instead of pinning one spelling.
        static::assertSame(Currencies::getName('RUB', 'en_GB'), $currency->getName());
        static::assertSame('RUB', $currency->getShortName());
        static::assertSame('RUB', $currency->getIsoCode());
        static::assertSame(1.0, $currency->getFactor());
        static::assertSame(2, $currency->getItemRounding()->getDecimals());
        static::assertSame(0.01, $currency->getItemRounding()->getInterval());
        static::assertTrue($currency->getItemRounding()->roundForNet());
        static::assertSame(2, $currency->getTotalRounding()->getDecimals());
        static::assertSame(0.01, $currency->getTotalRounding()->getInterval());
        static::assertTrue($currency->getTotalRounding()->roundForNet());
    }

    public function testSwitchDefaultCurrencyWithDefaultCurrency(): void
    {
        $this->shopConfigurator->setDefaultCurrency('EUR');

        $currency = $this->currencyRepo->search(new Criteria([Defaults::CURRENCY]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($currency);
        static::assertSame('Euro', $currency->getName());
    }

    public function testSwitchDefaultCurrencyWithExistingCurrency(): void
    {
        $this->shopConfigurator->setDefaultCurrency('GBP');

        $currency = $this->currencyRepo->search(new Criteria([Defaults::CURRENCY]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($currency);
        static::assertSame('Pound', $currency->getName());
        static::assertSame(1.0, $currency->getFactor());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isoCode', 'EUR'));

        $oldDefault = $this->currencyRepo->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($oldDefault);
        static::assertSame('Euro', $oldDefault->getName());
        static::assertSame(1.1216169229561337, $oldDefault->getFactor());
    }
}
