<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Sso\Service;

use KnpU\OAuth2ClientBundle\DependencyInjection\KnpUOAuth2ClientExtension;
use KnpU\OAuth2ClientBundle\DependencyInjection\ProviderFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Channel\OAuth2Channel;
use Shopwell\Core\Framework\Sso\Channel\OAuth2ChannelRegistry;
use Shopwell\Core\Framework\Sso\DataAbstractionLayer\OAuth2ClientCollection;
use Shopwell\Core\Framework\Sso\DataAbstractionLayer\OAuth2ClientEntity;
use Shopwell\Core\Framework\Sso\DataAbstractionLayer\OAuth2IdentityCollection;
use Shopwell\Core\Framework\Sso\DataAbstractionLayer\OAuth2IdentityEntity;
use Shopwell\Core\Framework\Sso\Service\OAuth2ClientFactory;
use Shopwell\Core\Framework\Sso\Service\OAuth2IdentityFactory;
use Shopwell\Core\Framework\Sso\Service\OAuth2LoginService;
use Shopwell\Core\Framework\Sso\SsoException;
use Shopwell\Core\Framework\Sso\Struct\OAuth2Identity;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\System\User\UserEntity;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OAuth2LoginService::class)]
class OAuth2LoginServiceTest extends TestCase
{
    public function testGetEnabledChannelsSkipsChannelsTheShopNoLongerOffers(): void
    {
        $service = $this->createService(
            clientSearches: [
                new OAuth2ClientCollection([
                    $this->createClient('google'),
                    $this->createClient('retired'),
                ]),
            ],
        );

        $channels = $service->getEnabledChannels(Context::createDefaultContext());

        static::assertSame(['google'], array_keys($channels));
        static::assertInstanceOf(OAuth2Channel::class, $channels['google']);
    }

    public function testGetEnabledClientThrowsForAChannelThisShopDoesNotOffer(): void
    {
        $service = $this->createService();

        $this->expectExceptionObject(SsoException::oauth2ProviderNotAvailable('github'));

        $service->getEnabledClient('github', Context::createDefaultContext());
    }

    public function testGetEnabledClientThrowsForAChannelTheAdminDidNotEnable(): void
    {
        $service = $this->createService(clientSearches: [new OAuth2ClientCollection()]);

        $this->expectExceptionObject(SsoException::oauth2ChannelDisabled('google'));

        $service->getEnabledClient('google', Context::createDefaultContext());
    }

    public function testGetEnabledClientReturnsTheClientOfAnEnabledChannel(): void
    {
        $client = $this->createClient('google');

        $service = $this->createService(clientSearches: [new OAuth2ClientCollection([$client])]);

        static::assertSame($client, $service->getEnabledClient('google', Context::createDefaultContext()));
    }

    public function testResolveCustomerReturnsTheCustomerOfAnExistingBinding(): void
    {
        $service = $this->createService(
            identitySearches: [new OAuth2IdentityCollection([$this->createIdentity('google', 'provider-user', 'customer-id')])],
        );

        static::assertSame(
            'customer-id',
            $service->resolveCustomer($this->createStruct('google', 'provider-user'), Generator::generateSalesChannelContext())
        );
    }

    public function testResolveCustomerBindsTheCustomerMatchingTheVerifiedEmail(): void
    {
        $service = $this->createService(
            identitySearches: [
                // the lookup of the binding, then the lookup inside the binding creation
                new OAuth2IdentityCollection(),
                new OAuth2IdentityCollection(),
            ],
            customerSearches: [
                new CustomerCollection([$this->createCustomer('customer-id', 'jane@example.com')]),
            ],
        );

        $identity = $this->createStruct('google', 'provider-user', email: 'jane@example.com', emailVerified: true);

        static::assertSame('customer-id', $service->resolveCustomer($identity, Generator::generateSalesChannelContext()));
    }

    public function testResolveCustomerThrowsWithoutAVerifiedEmail(): void
    {
        $service = $this->createService(identitySearches: [new OAuth2IdentityCollection()]);

        $this->expectExceptionObject(SsoException::oauth2MissingVerifiedEmail('google'));

        $service->resolveCustomer(
            $this->createStruct('google', 'provider-user', email: 'jane@example.com'),
            Generator::generateSalesChannelContext()
        );
    }

    public function testResolveCustomerThrowsWhenNoCustomerUsesTheEmail(): void
    {
        $service = $this->createService(
            identitySearches: [new OAuth2IdentityCollection()],
            customerSearches: [new CustomerCollection()],
        );

        $this->expectExceptionObject(SsoException::oauth2IdentityNotBound('google'));

        $service->resolveCustomer(
            $this->createStruct('google', 'provider-user', email: 'jane@example.com', emailVerified: true),
            Generator::generateSalesChannelContext()
        );
    }

    public function testResolveCustomerThrowsWhenTheIdentityBelongsToAnAdminUser(): void
    {
        $identity = $this->createIdentity('google', 'provider-user');
        $identity->userId = 'admin-user-id';

        $service = $this->createService(identitySearches: [new OAuth2IdentityCollection([$identity])]);

        $this->expectExceptionObject(SsoException::oauth2IdentityAlreadyBound('google'));

        $service->resolveCustomer($this->createStruct('google', 'provider-user'), Generator::generateSalesChannelContext());
    }

    public function testBindToCustomerCreatesTheBinding(): void
    {
        $service = $this->createService(
            identitySearches: [
                new OAuth2IdentityCollection(),
            ],
        );

        $id = $service->bindToCustomer(
            $this->createStruct('google', 'provider-user'),
            'customer-id',
            Context::createDefaultContext()
        );

        static::assertTrue(Uuid::isValid($id));
    }

    public function testBindToCustomerRejectsAChannelAlreadyBoundToAnotherCustomer(): void
    {
        $service = $this->createService(
            identitySearches: [
                new OAuth2IdentityCollection([$this->createIdentity('google', 'provider-user', 'other-customer')]),
            ],
        );

        $this->expectExceptionObject(SsoException::oauth2IdentityAlreadyBound('google'));

        $service->bindToCustomer(
            $this->createStruct('google', 'provider-user'),
            'customer-id',
            Context::createDefaultContext()
        );
    }

    public function testUnbindFromCustomerRemovesTheBinding(): void
    {
        $identityRepository = StaticEntityRepository::of(OAuth2IdentityCollection::class, [
            new OAuth2IdentityCollection([$this->createIdentity('google', 'provider-user', 'customer-id')]),
        ]);

        $service = $this->createService(identityRepository: $identityRepository);

        $service->unbindFromCustomer('google', 'customer-id', Context::createDefaultContext());

        static::assertCount(1, $identityRepository->deletes);
    }

    public function testUnbindFromCustomerThrowsWithoutABinding(): void
    {
        $service = $this->createService(identitySearches: [new OAuth2IdentityCollection()]);

        $this->expectExceptionObject(SsoException::oauth2IdentityNotBound('google'));

        $service->unbindFromCustomer('google', 'customer-id', Context::createDefaultContext());
    }

    public function testGetCustomerIdentitiesIsKeyedByChannel(): void
    {
        $service = $this->createService(
            identitySearches: [
                new OAuth2IdentityCollection([
                    $this->createIdentity('google', 'google-user', 'customer-id'),
                    $this->createIdentity('wechat', 'wechat-user', 'customer-id'),
                ]),
            ],
        );

        $identities = $service->getCustomerIdentities('customer-id', Context::createDefaultContext());

        static::assertSame(['google', 'wechat'], array_keys($identities));
    }

    public function testGetEnabledAdminChannelsSkipsChannelsWithoutAdminSupport(): void
    {
        $service = $this->createService(
            clientSearches: [
                new OAuth2ClientCollection([
                    $this->createClient('google'),
                    $this->createClient('wechat'),
                ]),
            ],
        );

        $channels = $service->getEnabledAdminChannels(Context::createDefaultContext());

        static::assertSame(['google'], array_keys($channels));
    }

    public function testResolveAdminUserReturnsTheUserOfAnExistingBinding(): void
    {
        $identity = $this->createIdentity('google', 'provider-user');
        $identity->userId = 'admin-user-id';

        $service = $this->createService(identitySearches: [new OAuth2IdentityCollection([$identity])]);

        static::assertSame(
            'admin-user-id',
            $service->resolveAdminUser($this->createStruct('google', 'provider-user'), Context::createDefaultContext())
        );
    }

    public function testResolveAdminUserThrowsWhenTheIdentityBelongsToACustomer(): void
    {
        $service = $this->createService(
            identitySearches: [new OAuth2IdentityCollection([$this->createIdentity('google', 'provider-user', 'customer-id')])],
        );

        $this->expectExceptionObject(SsoException::oauth2IdentityAlreadyBound('google'));

        $service->resolveAdminUser($this->createStruct('google', 'provider-user'), Context::createDefaultContext());
    }

    public function testResolveAdminUserBindsTheUserMatchingTheVerifiedEmail(): void
    {
        $service = $this->createService(
            identitySearches: [
                // the lookup of the binding, then the lookup inside the binding creation
                new OAuth2IdentityCollection(),
                new OAuth2IdentityCollection(),
            ],
            userSearches: [
                new UserCollection([$this->createUser('admin-user-id', 'jane@example.com')]),
            ],
        );

        $identity = $this->createStruct('google', 'provider-user', email: 'jane@example.com', emailVerified: true);

        static::assertSame('admin-user-id', $service->resolveAdminUser($identity, Context::createDefaultContext()));
    }

    public function testResolveAdminUserThrowsWhenNoUserUsesTheEmail(): void
    {
        $service = $this->createService(
            identitySearches: [new OAuth2IdentityCollection()],
            userSearches: [new UserCollection()],
        );

        $this->expectExceptionObject(SsoException::oauth2IdentityNotBound('google'));

        $service->resolveAdminUser(
            $this->createStruct('google', 'provider-user', email: 'jane@example.com', emailVerified: true),
            Context::createDefaultContext()
        );
    }

    public function testGetUserIdentitiesIsKeyedByChannel(): void
    {
        $identity = $this->createIdentity('google', 'google-user');
        $identity->userId = 'admin-user-id';

        $service = $this->createService(identitySearches: [new OAuth2IdentityCollection([$identity])]);

        $identities = $service->getUserIdentities('admin-user-id', Context::createDefaultContext());

        static::assertSame(['google'], array_keys($identities));
    }

    public function testUnbindFromUserRemovesTheBinding(): void
    {
        $identity = $this->createIdentity('google', 'provider-user');
        $identity->userId = 'admin-user-id';

        $identityRepository = StaticEntityRepository::of(OAuth2IdentityCollection::class, [
            new OAuth2IdentityCollection([$identity]),
        ]);

        $service = $this->createService(identityRepository: $identityRepository);

        $service->unbindFromUser('google', 'admin-user-id', Context::createDefaultContext());

        static::assertCount(1, $identityRepository->deletes);
    }

    public function testUnbindFromUserThrowsWithoutABinding(): void
    {
        $service = $this->createService(identitySearches: [new OAuth2IdentityCollection()]);

        $this->expectExceptionObject(SsoException::oauth2IdentityNotBound('google'));

        $service->unbindFromUser('google', 'admin-user-id', Context::createDefaultContext());
    }

    /**
     * @param list<OAuth2ClientCollection> $clientSearches
     * @param list<OAuth2IdentityCollection> $identitySearches
     * @param list<CustomerCollection> $customerSearches
     * @param list<UserCollection> $userSearches
     * @param StaticEntityRepository<OAuth2IdentityCollection>|null $identityRepository
     */
    private function createService(
        array $clientSearches = [],
        array $identitySearches = [],
        array $customerSearches = [],
        array $userSearches = [],
        ?StaticEntityRepository $identityRepository = null,
    ): OAuth2LoginService {
        $channels = new OAuth2ChannelRegistry([
            new OAuth2Channel('google', 'google', 'sso.oauth2.channel.google', supportsAdminLogin: true),
            new OAuth2Channel('wechat', 'generic', 'sso.oauth2.channel.wechat'),
        ]);

        return new OAuth2LoginService(
            StaticEntityRepository::of(OAuth2ClientCollection::class, $clientSearches),
            $identityRepository ?? StaticEntityRepository::of(OAuth2IdentityCollection::class, $identitySearches),
            StaticEntityRepository::of(CustomerCollection::class, $customerSearches),
            StaticEntityRepository::of(UserCollection::class, $userSearches),
            $channels,
            new OAuth2ClientFactory(
                new ProviderFactory(static::createStub(UrlGeneratorInterface::class)),
                new KnpUOAuth2ClientExtension(false),
                $channels,
                new RequestStack(),
            ),
            new OAuth2IdentityFactory(),
            new EventDispatcher(),
        );
    }

    private function createClient(string $technicalName): OAuth2ClientEntity
    {
        $client = new OAuth2ClientEntity();
        $client->id = Uuid::randomHex();
        $client->setUniqueIdentifier($client->id);
        $client->technicalName = $technicalName;
        $client->enabled = true;
        $client->clientId = 'client-id';
        $client->clientSecret = 'client-secret';

        return $client;
    }

    private function createIdentity(string $technicalName, string $providerUserId, ?string $customerId = null): OAuth2IdentityEntity
    {
        $identity = new OAuth2IdentityEntity();
        $identity->id = Uuid::randomHex();
        $identity->setUniqueIdentifier($identity->id);
        $identity->technicalName = $technicalName;
        $identity->providerUserId = $providerUserId;
        $identity->customerId = $customerId;

        return $identity;
    }

    private function createCustomer(string $id, string $email): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId($id);
        $customer->setUniqueIdentifier($id);
        $customer->setEmail($email);
        $customer->setActive(true);
        $customer->setGuest(false);

        return $customer;
    }

    private function createUser(string $id, string $email): UserEntity
    {
        $user = new UserEntity();
        $user->setId($id);
        $user->setUniqueIdentifier($id);
        $user->setEmail($email);
        $user->setActive(true);

        return $user;
    }

    private function createStruct(
        string $technicalName,
        string $providerUserId,
        ?string $email = null,
        bool $emailVerified = false,
    ): OAuth2Identity {
        return new OAuth2Identity($technicalName, $providerUserId, $email, $emailVerified);
    }
}
