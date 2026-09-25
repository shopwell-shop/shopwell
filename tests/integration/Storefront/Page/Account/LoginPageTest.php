<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Page\Account;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopwell\Storefront\Page\Account\Login\AccountLoginPageLoadedEvent;
use Shopwell\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Shopwell\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Shopwell\Tests\Integration\Storefront\Page\StorefrontPageTestConstants;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class LoginPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItLoadsWithACustomer(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $event = null;
        $this->catchEvent(AccountLoginPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertCount(StorefrontPageTestConstants::COUNTRY_COUNT, $page->getCountries());
        static::assertInstanceOf(CountryStateCollection::class, $page->getCountries()->first()?->getStates());
        self::assertPageEvent(AccountLoginPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItLoadsWithoutACustomer(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithNavigation();
        $page = $this->getPageLoader()->load($request, $context);

        static::assertCount(StorefrontPageTestConstants::COUNTRY_COUNT, $page->getCountries());
        static::assertInstanceOf(CountryStateCollection::class, $page->getCountries()->first()?->getStates());
    }

    protected function getPageLoader(): AccountLoginPageLoader
    {
        return static::getContainer()->get(AccountLoginPageLoader::class);
    }
}
