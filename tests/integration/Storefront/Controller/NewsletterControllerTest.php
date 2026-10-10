<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopwell\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\TestDefaults;
use Shopwell\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @internal
 */
#[Package('after-sales')]
class NewsletterControllerTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;
    use StorefrontControllerTestBehaviour;

    /**
     * @var array<string, mixed>
     */
    private array $customerData = [];

    public function testRegisterNewsletterForCustomerDirect(): void
    {
        $browser = $this->login();
        $data = [
            'option' => 'direct',
        ];

        $browser->request(
            'POST',
            '/widgets/account/newsletter',
            $this->tokenize('frontend.account.newsletter', $data)
        );

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        static::assertSame(200, $response->getStatusCode());

        $repo = static::getContainer()->get('newsletter_recipient.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', 'nltest@example.com'));
        /** @var NewsletterRecipientEntity $recipientEntry */
        $recipientEntry = $repo->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertSame('direct', (string) $recipientEntry->getStatus());
        $this->validateRecipientData($recipientEntry);
    }

    public function testRegisterNewsletterForCustomerDoi(): void
    {
        $container = static::getContainer();

        $systemConfigService = $container->get(SystemConfigService::class);
        static::assertNotNull($systemConfigService);
        $systemConfigService->set('core.newsletter.doubleOptInRegistered', true);

        $browser = $this->login();
        $data = [
            'option' => 'subscribe',
        ];

        $browser->request(
            'POST',
            '/widgets/account/newsletter',
            $this->tokenize('frontend.account.newsletter', $data)
        );

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        static::assertSame(200, $response->getStatusCode());

        $repo = $container->get('newsletter_recipient.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', 'nltest@example.com'));
        $context = Context::createDefaultContext();
        /** @var NewsletterRecipientEntity $recipientEntry */
        $recipientEntry = $repo->search($criteria, $context)->getEntities()->first();

        $params = [
            'em' => Hasher::hash('nltest@example.com', 'sha1'),
            'hash' => $recipientEntry->getHash(),
        ];

        $browser->request(
            'GET',
            '/newsletter-subscribe?' . http_build_query($params)
        );

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        static::assertSame(200, $response->getStatusCode());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', 'nltest@example.com'));
        /** @var NewsletterRecipientEntity $recipientEntry */
        $recipientEntry = $repo->search($criteria, $context)->getEntities()->first();

        static::assertSame(NewsletterSubscribeRoute::STATUS_OPT_IN, (string) $recipientEntry->getStatus());
        $this->validateRecipientData($recipientEntry);

        // Reset status and test again with redirect
        $repo->update([
            [
                'id' => $recipientEntry->getId(),
                'status' => NewsletterSubscribeRoute::STATUS_NOT_SET,
            ],
        ], $context);

        $params['redirectTo'] = 'frontend.home.page';

        $browser->request(
            'GET',
            '/newsletter-subscribe?' . http_build_query($params)
        );

        $response = $browser->getResponse();

        static::assertSame(302, $response->getStatusCode());
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/', $response->getTargetUrl());

        $session = $this->getSession();
        static::assertInstanceOf(Session::class, $session);
        $success = $session->getFlashBag()->get('success');

        static::assertNotEmpty($success);
        static::assertSame($container->get('translator')->trans('newsletter.subscriptionCompleted'), $success[0]);
    }

    private function login(): KernelBrowser
    {
        $customer = $this->createCustomer();

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $customer->getEmail(),
                'password' => 'shopwell',
            ])
        );
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return $browser;
    }

    private function createCustomer(): CustomerEntity
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $this->customerData = [
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'name' => 'Max Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => 'nltest@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'title' => 'Dr.',
            'name' => 'Max Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => '12345',
        ];

        /** @var EntityRepository<CustomerCollection> $repo */
        $repo = static::getContainer()->get('customer.repository');

        $repo->create([$this->customerData], Context::createDefaultContext());

        $customer = $repo->search(new Criteria([$customerId]), Context::createDefaultContext())->getEntities()->first();

        static::assertNotNull($customer);

        return $customer;
    }

    private function validateRecipientData(NewsletterRecipientEntity $recipientEntry): void
    {
        static::assertSame($this->customerData['email'], $recipientEntry->getEmail());
        static::assertSame($this->customerData['salutationId'], $recipientEntry->getSalutationId());
        static::assertSame($this->customerData['title'], $recipientEntry->getTitle());
        static::assertSame($this->customerData['name'], $recipientEntry->getName());
        static::assertSame($this->customerData['defaultShippingAddress']['zipcode'], $recipientEntry->getZipCode());
        static::assertSame($this->customerData['defaultShippingAddress']['city'], $recipientEntry->getCity());
        static::assertSame($this->customerData['defaultShippingAddress']['street'], $recipientEntry->getStreet());
    }
}
