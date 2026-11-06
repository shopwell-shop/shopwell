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
 * The rewards page renders on its own, and the address the loyalty page used to answer on redirects to
 * it permanently - a bookmark or an external link has to keep leading somewhere.
 *
 * @internal
 */
#[Package('checkout')]
class AccountRewardsControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    public function testTheRewardsPageRenders(): void
    {
        $browser = $this->login($this->createCustomer()->getEmail());

        $browser->request('GET', '/account/rewards');

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = (string) $response->getContent();
        static::assertStringContainsString('account-rewards-overview', $content);
        // the page states the earning rule from the settings instead of hard-coding it
        static::assertStringContainsString('account-rewards-earn', $content);
    }

    public function testTheOldAddressRedirectsPermanently(): void
    {
        $browser = $this->login($this->createCustomer()->getEmail());

        $browser->request('GET', '/account/loyalty');

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_MOVED_PERMANENTLY, $response->getStatusCode());
        static::assertStringEndsWith('/account/rewards', (string) $response->headers->get('Location'));
    }

    public function testTheDetailAnswersTheSnapshot(): void
    {
        $browser = $this->login($this->createCustomer()->getEmail());

        $browser->request('GET', '/account/rewards/detail');

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($payload['success']);
        static::assertArrayHasKey('pointsBalance', $payload['rewards']);
        static::assertArrayHasKey('redemptionLevels', $payload['rewards']);
    }

    public function testThePageIsGoneWhenTheProgrammeIsOff(): void
    {
        $browser = $this->login($this->createCustomer()->getEmail());

        static::getContainer()->get(SystemConfigService::class)
            ->set(LoyaltySettings::CONFIG_KEY_ENABLED, false, $this->getSalesChannelId());

        $browser->request('GET', '/account/rewards');

        static::assertSame(Response::HTTP_NOT_FOUND, $browser->getResponse()->getStatusCode());
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
                'email' => 'rewards-' . $customerId . '@example.com',
                'password' => TestDefaults::HASHED_PASSWORD,
                'name' => 'Max Mustermann',
                'gender' => $this->getValidGender(),
                'customerNumber' => 'SW' . substr($customerId, 0, 8),
            ],
        ], $context);

        $customer = $repository->search(new Criteria([$customerId]), $context)
            ->getEntities()
            ->first();

        static::assertInstanceOf(CustomerEntity::class, $customer);

        return $customer;
    }
}
