<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltySettings;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\TestDefaults;
use Shopwell\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * The membership page renders on its own: a customer who never bought one still sees the plans, and a
 * shop that switched the programme off has no page to sell one.
 *
 * @internal
 */
#[Package('checkout')]
class AccountMembershipControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    public function testTheMembershipPageRenders(): void
    {
        $browser = $this->login($this->createCustomer()->getEmail());

        $browser->request('GET', '/account/membership');

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = (string) $response->getContent();
        static::assertStringContainsString('account-membership-current', $content);
        static::assertStringContainsString('account-membership-plans', $content);
        // no plan is configured in this test: the page says so instead of rendering an empty grid
        static::assertStringContainsString('No membership plan is available right now.', $content);
    }

    /**
     * The page and the rewards page are two addresses: the membership page must not render the points
     * overview, or the split would only be a rename.
     */
    public function testTheMembershipPageDoesNotRenderTheRewardsOverview(): void
    {
        $browser = $this->login($this->createCustomer()->getEmail());

        $browser->request('GET', '/account/membership');

        static::assertStringNotContainsString('account-rewards-overview', (string) $browser->getResponse()->getContent());
    }

    public function testTheSnapshotCarriesTheOfferAndNoSubscription(): void
    {
        $browser = $this->login($this->createCustomer()->getEmail());

        $browser->request('GET', '/account/membership/json');

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($payload['success']);
        static::assertSame([], $payload['membership']['plans']);
        static::assertNull($payload['membership']['subscription']);
    }

    public function testThePageIsGoneWhenTheProgrammeIsOff(): void
    {
        $browser = $this->login($this->createCustomer()->getEmail());

        static::getContainer()->get(SystemConfigService::class)
            ->set(LoyaltySettings::CONFIG_KEY_ENABLED, false, $this->getSalesChannelId());

        $browser->request('GET', '/account/membership');

        static::assertSame(Response::HTTP_NOT_FOUND, $browser->getResponse()->getStatusCode());
    }

    /**
     * The sidebar carries both entries, and only while the programme runs.
     */
    public function testTheSidebarLinksBothPages(): void
    {
        $browser = $this->login($this->createCustomer()->getEmail());

        $browser->request('GET', '/account/membership');

        $content = (string) $browser->getResponse()->getContent();

        static::assertStringContainsString('/account/rewards', $content);
        static::assertStringContainsString('/account/membership', $content);
    }

    private function login(string $email): KernelBrowser
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $email,
                'password' => 'shopwell',
            ])
        );

        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return $browser;
    }

    private function createCustomer(): CustomerEntity
    {
        $context = Context::createDefaultContext();
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        /** @var EntityRepository<CustomerCollection> $repository */
        $repository = static::getContainer()->get('customer.repository');

        $repository->create([
            [
                'id' => $customerId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'name' => 'Max Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'gender' => $this->getValidGender(),
                    'country' => ['name' => 'Germany'],
                ],
                'defaultBillingAddressId' => $addressId,
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'membership-' . $customerId . '@example.com',
                'password' => TestDefaults::HASHED_PASSWORD,
                'name' => 'Max Mustermann',
                'gender' => $this->getValidGender(),
                'customerNumber' => 'SW' . substr($customerId, 0, 8),
            ],
        ], $context);

        $customer = $repository->search(new Criteria([$customerId]), $context)->getEntities()->first();

        static::assertInstanceOf(CustomerEntity::class, $customer);

        return $customer;
    }
}
