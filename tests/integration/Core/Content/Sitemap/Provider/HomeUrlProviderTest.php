<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Sitemap\Provider;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Sitemap\Provider\HomeUrlProvider;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Language\LanguageCollection;
use Shopwell\Core\System\Language\LanguageEntity;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('discovery')]
class HomeUrlProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private SalesChannelContext $salesChannelContext;

    /**
     * @var EntityRepository<LanguageCollection>
     */
    private EntityRepository $languageRepository;

    protected function setUp(): void
    {
        $this->languageRepository = static::getContainer()->get('language.repository');
        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create('', TestDefaults::SALES_CHANNEL);
    }

    public function testGetHomeUrlSalesChannelIsExistingTwoDomain(): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('locale');
        $languages = $this->languageRepository->search($criteria, $this->salesChannelContext->getContext())
            ->getEntities();

        $domain = new SalesChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl('https://test-sitemap.de');
        $domain->setHreflangUseOnlyLocale(false);
        $first = $languages->first();
        static::assertInstanceOf(LanguageEntity::class, $first);
        $domain->setLanguageId($first->getId());

        $domains = $this->salesChannelContext->getSalesChannel()->getDomains();
        static::assertInstanceOf(SalesChannelDomainCollection::class, $domains);
        $domains->add($domain);

        $domain = new SalesChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl('https://test-sitemap.de/en');
        $domain->setHreflangUseOnlyLocale(false);
        $last = $languages->last();
        static::assertInstanceOf(LanguageEntity::class, $last);
        $domain->setLanguageId($last->getId());

        $domains->add($domain);

        $homeUrlProvider = new HomeUrlProvider();

        static::assertCount(1, $homeUrlProvider->getUrls($this->salesChannelContext, 100)->getUrls());
    }

    public function testGetHomeUrlWithSalesChannelIsExistingOneDomain(): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('locale');
        $languages = $this->languageRepository->search($criteria, $this->salesChannelContext->getContext())
            ->getEntities();

        $languageId = $this->salesChannelContext->getLanguageId();
        $language = $languages->get($languageId);
        static::assertInstanceOf(LanguageEntity::class, $language);

        $domain = new SalesChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl('https://test-sitemap.de/en');
        $domain->setHreflangUseOnlyLocale(false);
        $domain->setLanguageId($language->getId());

        static::assertInstanceOf(SalesChannelDomainCollection::class, $this->salesChannelContext->getSalesChannel()->getDomains());
        $this->salesChannelContext->getSalesChannel()->getDomains()->add($domain);

        $homeUrlProvider = new HomeUrlProvider();

        static::assertCount(1, $homeUrlProvider->getUrls($this->salesChannelContext, 100)->getUrls());
    }

    public function testGetHomeUrlWithSalesChannelHaveNoDomain(): void
    {
        $results = (new HomeUrlProvider())->getUrls($this->salesChannelContext, 100);

        static::assertEmpty($results->getUrls()[0]->getLoc());
    }

    public function testProviderNameIsHome(): void
    {
        $homeUrlProvider = new HomeUrlProvider();

        static::assertSame('home', $homeUrlProvider->getName());
    }
}
