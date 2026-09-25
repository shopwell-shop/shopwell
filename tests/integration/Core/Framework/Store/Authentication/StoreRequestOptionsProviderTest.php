<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Store\Authentication;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider;
use Shopwell\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopwell\Core\Framework\Store\StoreException;
use Shopwell\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
class StoreRequestOptionsProviderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    private AbstractStoreRequestOptionsProvider $storeRequestOptionsProvider;

    private Context $storeContext;

    protected function setUp(): void
    {
        $this->storeRequestOptionsProvider = static::getContainer()->get(StoreRequestOptionsProvider::class);
        $this->storeContext = $this->createAdminStoreContext();
    }

    public function testGetAuthenticationHeadersHasUserStoreTokenAndShopSecret(): void
    {
        $shopSecret = 'im-a-super-safe-secret';

        $this->setShopSecret($shopSecret);
        $headers = $this->storeRequestOptionsProvider->getAuthenticationHeader($this->storeContext);

        static::assertSame([
            'X-Shopwell-Platform-Token' => $this->getStoreTokenFromContext($this->storeContext),
            'X-Shopwell-Shop-Secret' => $shopSecret,
        ], $headers);
    }

    public function testGetAuthenticationHeadersUsesFirstStoreTokenFoundIfContextIsSystemSource(): void
    {
        $shopSecret = 'im-a-super-safe-secret';

        $this->setShopSecret($shopSecret);
        $headers = $this->storeRequestOptionsProvider->getAuthenticationHeader(Context::createDefaultContext());

        static::assertSame([
            'X-Shopwell-Platform-Token' => $this->getStoreTokenFromContext($this->storeContext),
            'X-Shopwell-Shop-Secret' => $shopSecret,
        ], $headers);
    }

    public function testGetAuthenticationHeadersThrowsForIntegrations(): void
    {
        $context = new Context(new AdminApiSource(null, Uuid::randomHex()));

        $this->expectExceptionObject(StoreException::invalidContextSourceUser(AdminApiSource::class));
        $this->storeRequestOptionsProvider->getAuthenticationHeader($context);
    }

    public function testGetDefaultQueriesReturnsLanguageFromContext(): void
    {
        $queries = $this->storeRequestOptionsProvider->getDefaultQueryParameters($this->storeContext);

        static::assertArrayHasKey('language', $queries);
        static::assertSame(
            $this->getLanguageFromContext($this->storeContext),
            $queries['language']
        );
    }

    public function testGetDefaultQueriesReturnsShopwellVersion(): void
    {
        $queries = $this->storeRequestOptionsProvider->getDefaultQueryParameters($this->storeContext);

        static::assertArrayHasKey('shopwellVersion', $queries);
        static::assertSame($this->getShopwellVersion(), $queries['shopwellVersion']);
    }

    public function testGetDefaultQueriesDoesHaveDomainSetEvenIfLicenseDomainIsNull(): void
    {
        $this->setLicenseDomain(null);

        $queries = $this->storeRequestOptionsProvider->getDefaultQueryParameters($this->storeContext);

        static::assertArrayHasKey('domain', $queries);
        static::assertSame('', $queries['domain']);
    }

    public function testGetDefaultQueriesDoesHaveDomainSetIfLicenseDomainIsSet(): void
    {
        $this->setLicenseDomain('shopwell.swag');

        $queries = $this->storeRequestOptionsProvider->getDefaultQueryParameters($this->storeContext);

        static::assertArrayHasKey('domain', $queries);
        static::assertSame('shopwell.swag', $queries['domain']);
    }

    public function testGetDefaultQueriesWithLicenseDomain(): void
    {
        $this->setLicenseDomain('new-license-domain');

        $queries = $this->storeRequestOptionsProvider->getDefaultQueryParameters($this->storeContext);

        static::assertArrayHasKey('domain', $queries);
        static::assertSame('new-license-domain', $queries['domain']);
    }

    private function getLanguageFromContext(Context $context): string
    {
        /** @var AdminApiSource $contextSource */
        $contextSource = $context->getSource();
        $userId = $contextSource->getUserId();

        static::assertIsString($userId);

        $criteria = (new Criteria([$userId]))->addAssociation('locale');

        $user = $this->getUserRepository()->search($criteria, $context)->getEntities()->first();
        static::assertNotNull($user);
        static::assertNotNull($user->getLocale());

        return $user->getLocale()->getCode();
    }
}
