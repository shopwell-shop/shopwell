<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\SalesChannel\LoginRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\LogoutRoute;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopwell\Core\Framework\Util\Random;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\ContextTokenResponse;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\Integration\Traits\CustomerTestTrait;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('checkout')]
#[Group('store-api')]
class LogoutRouteTest extends TestCase
{
    use CustomerTestTrait;
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);
    }

    public function testNotLoggedin(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/logout',
            );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame(RoutingException::CUSTOMER_NOT_LOGGED_IN_CODE, $response['errors'][0]['code']);
    }

    public function testValidLogout(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $this->createCustomer($email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'shopwell',
                ]
            );

        static::assertIsString($this->browser->getResponse()->getContent());

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/logout',
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser
            ->request(
                'POST',
                '/store-api/account/customer'
            );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
    }

    public function testLogoutKeepsCartToBeAbleToRestore(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $this->createCustomer($email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'shopwell',
                ]
            );

        static::assertIsString($this->browser->getResponse()->getContent());

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/logout',
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $tokens = static::getContainer()->get(Connection::class)
            ->fetchFirstColumn('SELECT token FROM sales_channel_api_context WHERE customer_id =  (SELECT id FROM customer where email = ?)', [$email]);

        static::assertCount(1, $tokens);
        static::assertNotContains($contextToken, $tokens, 'Old token should still exist');
    }

    public function testLoggedOutKeepCustomerContextWithoutReplaceTokenParameter(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.invalidateSessionOnLogOut', false);

        $email = Uuid::randomHex() . '@example.com';
        $this->createCustomer($email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'shopwell',
                ]
            );

        static::assertIsString($this->browser->getResponse()->getContent());

        $response = $this->browser->getResponse();

        $currentCustomerToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?: '';

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $currentCustomerToken);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/logout',
            );

        $customerIdWithOldToken = static::getContainer()->get(Connection::class)->fetchOne('SELECT customer_id FROM sales_channel_api_context WHERE token = ?', [$currentCustomerToken]);
        static::assertFalse($customerIdWithOldToken, 'The old token should be gone');
    }

    public function testLogoutRouteReturnContextTokenResponse(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.loginRegistration.invalidateSessionOnLogOut', false);

        $email = Uuid::randomHex() . '@example.com';
        $this->createCustomer($email);

        $contextToken = Random::getAlphanumericString(32);

        $salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            $contextToken,
            TestDefaults::SALES_CHANNEL,
            []
        );

        $request = new RequestDataBag(['email' => $email, 'password' => 'shopwell']);
        $loginResponse = static::getContainer()->get(LoginRoute::class)->login($request, $salesChannelContext);

        $customerId = $this->createCustomer();
        $customer = static::getContainer()
            ->get('customer.repository')
            ->search(new Criteria(), Context::createDefaultContext())->getEntities()
            ->get($customerId);
        static::assertInstanceOf(CustomerEntity::class, $customer);
        $customer->setGuest(false);
        $salesChannelContext->assign([
            'token' => $loginResponse->getToken(),
            'customer' => $customer,
        ]);

        $logoutResponse = static::getContainer()->get(LogoutRoute::class)->logout(
            $salesChannelContext,
            new RequestDataBag()
        );

        static::assertInstanceOf(ContextTokenResponse::class, $logoutResponse);
        static::assertNotSame($loginResponse->getToken(), $logoutResponse->getToken());
    }

    public function testLogoutForcedForGuestAccounts(): void
    {
        $config = static::getContainer()->get(SystemConfigService::class);
        $config->set('core.loginRegistration.invalidateSessionOnLogOut', false);

        $email = Uuid::randomHex() . '@example.com';
        $this->createCustomer($email);

        $context = static::getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, []);

        $request = new RequestDataBag(['email' => $email, 'password' => 'shopwell']);
        $login = static::getContainer()
            ->get(LoginRoute::class)
            ->login($request, $context);

        $customerId = $this->createCustomer();
        $customer = static::getContainer()
            ->get('customer.repository')
            ->search(new Criteria(), Context::createDefaultContext())->getEntities()
            ->get($customerId);
        static::assertInstanceOf(CustomerEntity::class, $customer);
        $customer->setGuest(true);
        $context->assign([
            'token' => $login->getToken(),
            'customer' => $customer,
        ]);

        $logout = static::getContainer()
            ->get(LogoutRoute::class)
            ->logout($context, $request);

        static::assertInstanceOf(ContextTokenResponse::class, $logout);
        static::assertNotSame($login->getToken(), $logout->getToken());

        $exists = static::getContainer()->get(Connection::class)
            ->fetchAllAssociative('SELECT * FROM sales_channel_api_context WHERE token = :token', ['token' => $login->getToken()]);

        static::assertCount(0, $exists);
    }

    public function testValidLogoutAsGuest(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $customerId = $this->createCustomer($email, true);
        $this->browser->setServerParameter(
            'HTTP_SW_CONTEXT_TOKEN',
            $this->getLoggedInContextToken($customerId, $this->ids->get('sales-channel'))
        );

        $this->browser
            ->request(
                'POST',
                '/store-api/account/logout',
            );

        static::assertIsString($this->browser->getResponse()->getContent());
        static::assertSame(
            200,
            $this->browser->getResponse()->getStatusCode(),
            $this->browser->getResponse()->getContent()
        );

        $this->browser
            ->request(
                'POST',
                '/store-api/account/customer'
            );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
    }
}
