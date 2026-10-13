<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso\Service;

use KnpU\OAuth2ClientBundle\Client\OAuth2Client;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\Channel\OAuth2Channel;
use Shopwell\Core\Framework\Sso\Channel\OAuth2ChannelRegistry;
use Shopwell\Core\Framework\Sso\DataAbstractionLayer\OAuth2ClientCollection;
use Shopwell\Core\Framework\Sso\DataAbstractionLayer\OAuth2ClientEntity;
use Shopwell\Core\Framework\Sso\DataAbstractionLayer\OAuth2IdentityCollection;
use Shopwell\Core\Framework\Sso\DataAbstractionLayer\OAuth2IdentityEntity;
use Shopwell\Core\Framework\Sso\Event\OAuth2LoginSucceededEvent;
use Shopwell\Core\Framework\Sso\SsoException;
use Shopwell\Core\Framework\Sso\Struct\OAuth2Identity;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The account side of the third party login.
 *
 * It answers which channels a shop offers, starts and completes the round trip to a platform, and
 * owns the bindings between an external account and a customer of this shop. The credentials and the
 * protocol live in {@see OAuth2ClientFactory} and the league provider of the channel, this service
 * only decides which shop account a successful authorization belongs to.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class OAuth2LoginService
{
    /**
     * @param EntityRepository<OAuth2ClientCollection> $clientRepository
     * @param EntityRepository<OAuth2IdentityCollection> $identityRepository
     * @param EntityRepository<CustomerCollection> $customerRepository
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        private readonly EntityRepository $clientRepository,
        private readonly EntityRepository $identityRepository,
        private readonly EntityRepository $customerRepository,
        private readonly EntityRepository $userRepository,
        private readonly OAuth2ChannelRegistry $channels,
        private readonly OAuth2ClientFactory $clientFactory,
        private readonly OAuth2IdentityFactory $identityFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * The channels a customer may log in with, i.e. every channel the admin enabled and that this
     * shop still offers. A client row of a channel that an extension removed is skipped.
     *
     * @return array<string, OAuth2Channel> keyed by the technical name of the channel
     */
    public function getEnabledChannels(Context $context): array
    {
        $channels = [];

        foreach ($this->searchEnabledClients($context) as $client) {
            if ($this->channels->has($client->technicalName)) {
                $channels[$client->technicalName] = $this->channels->get($client->technicalName);
            }
        }

        return $channels;
    }

    /**
     * The channels an admin user may log in with, i.e. the enabled ones that also support the
     * administration. The platform decides that per channel, so a channel that can not tell an
     * admin from any other account is never offered on the login page of the administration.
     *
     * @return array<string, OAuth2Channel> keyed by the technical name of the channel
     */
    public function getEnabledAdminChannels(Context $context): array
    {
        $channels = [];

        foreach ($this->getEnabledChannels($context) as $technicalName => $channel) {
            if ($channel->supportsAdminLogin) {
                $channels[$technicalName] = $channel;
            }
        }

        return $channels;
    }

    /**
     * @throws SsoException when the channel does not exist or the admin did not enable it
     */
    public function getEnabledClient(string $technicalName, Context $context): OAuth2ClientEntity
    {
        if (!$this->channels->has($technicalName)) {
            throw SsoException::oauth2ProviderNotAvailable($technicalName);
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('enabled', true))
            ->addFilter(new EqualsFilter('technicalName', $technicalName));

        $client = $this->clientRepository->search($criteria, $context)->getEntities()->first();

        if (!$client instanceof OAuth2ClientEntity) {
            throw SsoException::oauth2ChannelDisabled($technicalName);
        }

        return $client;
    }

    /**
     * Sends the browser to the platform the channel belongs to.
     *
     * @param string $callbackRouteName the route the platform redirects back to
     *
     * @throws SsoException
     */
    public function createAuthorizationRedirect(string $technicalName, string $callbackRouteName, Context $context): RedirectResponse
    {
        $client = $this->getEnabledClient($technicalName, $context);

        return $this->clientFactory
            ->create($client, $callbackRouteName)
            ->redirect($this->clientFactory->getScopes($client));
    }

    /**
     * Completes the round trip and returns the normalized external account.
     *
     * @throws SsoException
     */
    public function fetchIdentity(string $technicalName, string $callbackRouteName, Context $context): OAuth2Identity
    {
        $channel = $this->channels->get($technicalName);
        $client = $this->createClient($technicalName, $callbackRouteName, $context);

        return $this->identityFactory->fromResourceOwner($channel, $client->fetchUser());
    }

    /**
     * The customer a successful authorization belongs to: an existing binding or, when the platform
     * returned a verified email, the customer owning that email in this sales channel.
     *
     * @throws SsoException when the external account is not bound and can not be matched by email
     */
    public function resolveCustomer(OAuth2Identity $identity, SalesChannelContext $context): string
    {
        $binding = $this->findIdentity($identity->getTechnicalName(), $identity->getProviderUserId(), $context->getContext());

        if ($binding?->customerId !== null) {
            $this->dispatchLoginSucceeded($identity, 'storefront', 'customer', $binding->customerId, $context->getContext());

            return $binding->customerId;
        }

        // the identity exists but belongs to an admin user, it must not silently move to a customer
        if ($binding instanceof OAuth2IdentityEntity) {
            throw SsoException::oauth2IdentityAlreadyBound($identity->getTechnicalName());
        }

        $email = $identity->getEmail();
        if (!$identity->isEmailVerified() || $email === null) {
            throw SsoException::oauth2MissingVerifiedEmail($identity->getTechnicalName());
        }

        $customer = $this->findCustomerByEmail($email, $context);
        if (!$customer instanceof CustomerEntity) {
            throw SsoException::oauth2IdentityNotBound($identity->getTechnicalName());
        }

        $this->bindToCustomer($identity, $customer->getId(), $context->getContext());
        $this->dispatchLoginSucceeded($identity, 'storefront', 'customer', $customer->getId(), $context->getContext());

        return $customer->getId();
    }

    /**
     * The admin user a successful authorization belongs to, resolved like {@see self::resolveCustomer()}
     * but against the admin users of the shop.
     *
     * @throws SsoException when the external account is not bound and can not be matched by email
     */
    public function resolveAdminUser(OAuth2Identity $identity, Context $context): string
    {
        $binding = $this->findIdentity($identity->getTechnicalName(), $identity->getProviderUserId(), $context);

        if ($binding?->userId !== null) {
            $this->dispatchLoginSucceeded($identity, 'admin', 'user', $binding->userId, $context);

            return $binding->userId;
        }

        // the identity exists but belongs to a customer, it must not silently move to an admin user
        if ($binding instanceof OAuth2IdentityEntity) {
            throw SsoException::oauth2IdentityAlreadyBound($identity->getTechnicalName());
        }

        $email = $identity->getEmail();
        if (!$identity->isEmailVerified() || $email === null) {
            throw SsoException::oauth2MissingVerifiedEmail($identity->getTechnicalName());
        }

        $user = $this->findActiveUserByEmail($email, $context);
        if (!$user instanceof UserEntity) {
            throw SsoException::oauth2IdentityNotBound($identity->getTechnicalName());
        }

        $this->bindToUser($identity, $user->getId(), $context);
        $this->dispatchLoginSucceeded($identity, 'admin', 'user', $user->getId(), $context);

        return $user->getId();
    }

    /**
     * Links an external account to a customer.
     *
     * @throws SsoException when the external account is already bound to another customer
     *
     * @return string the id of the binding
     */
    public function bindToCustomer(OAuth2Identity $identity, string $customerId, Context $context): string
    {
        return $this->bindIdentity($identity, 'customer', $customerId, $context);
    }

    /**
     * Links an external account to an admin user.
     *
     * @throws SsoException when the external account is already bound to another account
     *
     * @return string the id of the binding
     */
    public function bindToUser(OAuth2Identity $identity, string $userId, Context $context): string
    {
        return $this->bindIdentity($identity, 'user', $userId, $context);
    }

    /**
     * @throws SsoException when the customer has no binding for the channel
     */
    public function unbindFromCustomer(string $technicalName, string $customerId, Context $context): void
    {
        $this->unbind($technicalName, 'customerId', $customerId, $context);
    }

    /**
     * @throws SsoException when the admin user has no binding for the channel
     */
    public function unbindFromUser(string $technicalName, string $userId, Context $context): void
    {
        $this->unbind($technicalName, 'userId', $userId, $context);
    }

    /**
     * The bindings of a customer, keyed by the technical name of the channel.
     *
     * @return array<string, OAuth2IdentityEntity>
     */
    public function getCustomerIdentities(string $customerId, Context $context): array
    {
        return $this->getIdentities('customerId', $customerId, $context);
    }

    /**
     * The bindings of an admin user, keyed by the technical name of the channel.
     *
     * @return array<string, OAuth2IdentityEntity>
     */
    public function getUserIdentities(string $userId, Context $context): array
    {
        return $this->getIdentities('userId', $userId, $context);
    }

    public function findIdentity(string $technicalName, string $providerUserId, Context $context): ?OAuth2IdentityEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('technicalName', $technicalName))
            ->addFilter(new EqualsFilter('providerUserId', $providerUserId));

        $identity = $this->identityRepository->search($criteria, $context)->getEntities()->first();

        return $identity instanceof OAuth2IdentityEntity ? $identity : null;
    }

    private function createClient(string $technicalName, string $callbackRouteName, Context $context): OAuth2Client
    {
        return $this->clientFactory->create($this->getEnabledClient($technicalName, $context), $callbackRouteName);
    }

    private function searchEnabledClients(Context $context): OAuth2ClientCollection
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('enabled', true));

        $clients = $this->clientRepository->search($criteria, $context)->getEntities();

        \assert($clients instanceof OAuth2ClientCollection);

        return $clients;
    }

    /**
     * A login is only allowed for an active, non guest account of the current sales channel, or one
     * that is not bound to any sales channel at all.
     */
    private function findCustomerByEmail(string $email, SalesChannelContext $context): ?CustomerEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('email', $email))
            ->addFilter(new EqualsFilter('active', true));

        $customers = $this->customerRepository->search($criteria, $context->getContext())->getEntities();

        $customers = $customers->filter(static function (CustomerEntity $customer) use ($context): bool {
            if ($customer->getGuest()) {
                return false;
            }

            return $customer->getBoundSalesChannelId() === null
                || $customer->getBoundSalesChannelId() === $context->getSalesChannelId();
        });

        return $customers->first();
    }

    private function bindIdentity(OAuth2Identity $identity, string $accountType, string $accountId, Context $context): string
    {
        $existing = $this->findIdentity($identity->getTechnicalName(), $identity->getProviderUserId(), $context);

        if ($existing instanceof OAuth2IdentityEntity) {
            $boundId = $accountType === 'customer' ? $existing->customerId : $existing->userId;

            if ($boundId !== $accountId) {
                throw SsoException::oauth2IdentityAlreadyBound($identity->getTechnicalName());
            }

            return $existing->id;
        }

        $id = Uuid::randomHex();

        $this->identityRepository->create([
            [
                'id' => $id,
                'technicalName' => $identity->getTechnicalName(),
                'providerUserId' => $identity->getProviderUserId(),
                'customerId' => $accountType === 'customer' ? $accountId : null,
                'userId' => $accountType === 'user' ? $accountId : null,
            ],
        ], $context);

        return $id;
    }

    /**
     * @param 'customerId'|'userId' $accountField
     */
    private function unbind(string $technicalName, string $accountField, string $accountId, Context $context): void
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('technicalName', $technicalName))
            ->addFilter(new EqualsFilter($accountField, $accountId));

        $identity = $this->identityRepository->search($criteria, $context)->getEntities()->first();

        if (!$identity instanceof OAuth2IdentityEntity) {
            throw SsoException::oauth2IdentityNotBound($technicalName);
        }

        $this->identityRepository->delete([['id' => $identity->id]], $context);
    }

    /**
     * @param 'customerId'|'userId' $accountField
     *
     * @return array<string, OAuth2IdentityEntity>
     */
    private function getIdentities(string $accountField, string $accountId, Context $context): array
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter($accountField, $accountId));

        $identities = $this->identityRepository->search($criteria, $context)->getEntities();

        \assert($identities instanceof OAuth2IdentityCollection);

        $grouped = [];
        foreach ($identities as $identity) {
            $grouped[$identity->technicalName] = $identity;
        }

        return $grouped;
    }

    private function findActiveUserByEmail(string $email, Context $context): ?UserEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('email', $email))
            ->addFilter(new EqualsFilter('active', true));

        return $this->userRepository->search($criteria, $context)->getEntities()->first();
    }

    /**
     * @param 'storefront'|'admin' $area
     * @param 'customer'|'user' $accountType
     */
    private function dispatchLoginSucceeded(OAuth2Identity $identity, string $area, string $accountType, string $accountId, Context $context): void
    {
        $this->eventDispatcher->dispatch(
            new OAuth2LoginSucceededEvent($identity, $area, $accountType, $accountId, $context)
        );
    }
}
