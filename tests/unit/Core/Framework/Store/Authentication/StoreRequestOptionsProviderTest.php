<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Store\Authentication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Rule\InvokedCount;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopwell\Core\Framework\Api\Context\SystemSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Authentication\LocaleProvider;
use Shopwell\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopwell\Core\Framework\Store\Services\InstanceService;
use Shopwell\Core\Framework\Store\StoreException;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\System\User\UserDefinition;
use Shopwell\Core\System\User\UserEntity;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(StoreRequestOptionsProvider::class)]
class StoreRequestOptionsProviderTest extends TestCase
{
    public function testGetAuthenticationHeaderContainsShopSecretIfExists(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('getString')
            ->with('core.store.shopSecret')
            ->willReturn('store-secret');

        $provider = new StoreRequestOptionsProvider(
            $this->configureUserRepositorySearchMock(new UserCollection(), $this->once()),
            $systemConfigService,
            new InstanceService('sw-version', 'instance-id'),
            static::createStub(LocaleProvider::class)
        );

        $authHeaders = $provider->getAuthenticationHeader(
            Context::createDefaultContext(new AdminApiSource('user-id'))
        );

        static::assertArrayHasKey('X-Shopwell-Shop-Secret', $authHeaders);
        static::assertSame('store-secret', $authHeaders['X-Shopwell-Shop-Secret']);
    }

    public function testGetAuthenticationHeaderDoesNotContainsShopSecretIfNotExists(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('getString')
            ->with('core.store.shopSecret')
            ->willReturn('');

        $provider = new StoreRequestOptionsProvider(
            $this->configureUserRepositorySearchMock(new UserCollection(), $this->once()),
            $systemConfigService,
            new InstanceService('sw-version', 'instance-id'),
            static::createStub(LocaleProvider::class)
        );

        $authHeaders = $provider->getAuthenticationHeader(
            Context::createDefaultContext(new AdminApiSource('user-id'))
        );

        static::assertArrayNotHasKey('X-Shopwell-Shop-Secret', $authHeaders);
    }

    public function testGetAuthenticationHeaderReturnsUserToken(): void
    {
        $user = (new UserEntity())->assign([
            '_uniqueIdentifier' => 'user-id',
            'id' => 'user-id',
            'storeToken' => 'sbp-token',
        ]);

        $provider = new StoreRequestOptionsProvider(
            $this->configureUserRepositorySearchMock(new UserCollection([$user]), $this->once()),
            static::createStub(SystemConfigService::class),
            new InstanceService('sw-version', 'instance-id'),
            static::createStub(LocaleProvider::class)
        );

        $authHeaders = $provider->getAuthenticationHeader(
            Context::createDefaultContext(new AdminApiSource('user-id'))
        );

        static::assertArrayHasKey('X-Shopwell-Platform-Token', $authHeaders);
        static::assertSame('sbp-token', $authHeaders['X-Shopwell-Platform-Token']);
    }

    public function testGetAuthenticationHeaderThrowsIfUserIdIsMissingInAdminApiSource(): void
    {
        $provider = new StoreRequestOptionsProvider(
            $this->configureUserRepositorySearchMock(new UserCollection(), $this->never()),
            static::createStub(SystemConfigService::class),
            new InstanceService('sw-version', 'instance-id'),
            static::createStub(LocaleProvider::class)
        );

        $this->expectExceptionObject(StoreException::invalidContextSourceUser(AdminApiSource::class));

        $provider->getAuthenticationHeader(
            Context::createDefaultContext(new AdminApiSource(null, 'integration-id'))
        );
    }

    public function testGetAuthenticationHeaderReturnsNullIfUserWasNotFound(): void
    {
        $provider = new StoreRequestOptionsProvider(
            $this->configureUserRepositorySearchMock(new UserCollection(), $this->once()),
            static::createStub(SystemConfigService::class),
            new InstanceService('sw-version', 'instance-id'),
            static::createStub(LocaleProvider::class)
        );

        $authHeaders = $provider->getAuthenticationHeader(
            Context::createDefaultContext(new AdminApiSource('user-id'))
        );

        static::assertArrayNotHasKey('X-Shopwell-Platform-Token', $authHeaders);
    }

    public function testGetAuthenticationHeaderReturnsUserTokenInSystemSourceIfAUserHasToken(): void
    {
        $user = (new UserEntity())->assign([
            '_uniqueIdentifier' => 'user-id',
            'id' => 'user-id',
            'storeToken' => 'sbp-token',
        ]);

        $provider = new StoreRequestOptionsProvider(
            $this->configureUserRepositorySearchMock(new UserCollection([$user]), $this->once()),
            static::createStub(SystemConfigService::class),
            new InstanceService('sw-version', 'instance-id'),
            static::createStub(LocaleProvider::class)
        );

        $authHeaders = $provider->getAuthenticationHeader(
            Context::createDefaultContext(new SystemSource())
        );

        static::assertArrayHasKey('X-Shopwell-Platform-Token', $authHeaders);
        static::assertSame('sbp-token', $authHeaders['X-Shopwell-Platform-Token']);
    }

    public function testGetAuthenticationHeaderReturnsNullIfNoUserHasATokenSet(): void
    {
        $provider = new StoreRequestOptionsProvider(
            $this->configureUserRepositorySearchMock(new UserCollection(), $this->once()),
            static::createStub(SystemConfigService::class),
            new InstanceService('sw-version', 'instance-id'),
            static::createStub(LocaleProvider::class)
        );

        $authHeaders = $provider->getAuthenticationHeader(
            Context::createDefaultContext(new SystemSource())
        );

        static::assertArrayNotHasKey('X-Shopwell-Platform-Token', $authHeaders);
    }

    public function testGetAuthenticationHeaderThrowsIfContextIsNotSystemNorAdminApiSource(): void
    {
        $provider = new StoreRequestOptionsProvider(
            $this->configureUserRepositorySearchMock(new UserCollection(), $this->never()),
            static::createStub(SystemConfigService::class),
            new InstanceService('sw-version', 'instance-id'),
            static::createStub(LocaleProvider::class)
        );

        $this->expectExceptionObject(StoreException::invalidContextSource(SystemSource::class, SalesChannelApiSource::class));
        $provider->getAuthenticationHeader(
            Context::createDefaultContext(new SalesChannelApiSource('sales-channel-id'))
        );
    }

    public function testGetDefaultQueryParametersReturnsShopwellIdAndLicenseDomainFromServices(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('getString')
            ->with('core.store.licenseHost')
            ->willReturn('domain.shopware.store');

        $provider = new StoreRequestOptionsProvider(
            $this->configureUserRepositorySearchMock(new UserCollection(), $this->never()),
            $systemConfigService,
            new InstanceService('sw-version', 'instance-id'),
            static::createStub(LocaleProvider::class)
        );

        $queries = $provider->getDefaultQueryParameters(Context::createDefaultContext());

        static::assertArrayHasKey('domain', $queries);
        static::assertSame('domain.shopware.store', $queries['domain']);

        static::assertArrayHasKey('shopwareVersion', $queries);
        static::assertSame('sw-version', $queries['shopwareVersion']);
    }

    public function testGetDefaultQueryParametersDelegatesToLocaleProvider(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $localeProvider = $this->createMock(LocaleProvider::class);
        $localeProvider->expects($this->once())
            ->method('getLocaleFromContext')
            ->with($context)
            ->willReturn('locale-from-provider');

        $provider = new StoreRequestOptionsProvider(
            $this->configureUserRepositorySearchMock(new UserCollection(), $this->never()),
            static::createStub(SystemConfigService::class),
            new InstanceService('sw-version', 'instance-id'),
            $localeProvider
        );

        $queries = $provider->getDefaultQueryParameters($context);

        static::assertArrayHasKey('language', $queries);
        static::assertSame('locale-from-provider', $queries['language']);
    }

    /**
     * @return EntityRepository<UserCollection>&MockObject
     */
    private function configureUserRepositorySearchMock(
        UserCollection $collection,
        InvokedCount $invokedCount
    ): EntityRepository&MockObject {
        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->expects($invokedCount)
            ->method('search')
            ->willReturn(new EntitySearchResult(
                UserDefinition::ENTITY_NAME,
                $collection->count(),
                $collection,
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ));

        return $entityRepository;
    }
}
