<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Storefront\Test\Controller\StorefrontControllerTestBehaviour;

/**
 * Guards the conditional `required` state of the registration form, which differs per registration route.
 *
 * @internal
 */
#[Package('checkout')]
class RegisterAddressRequiredTemplateTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    /**
     * The account registration only asks for email and password, so no address or personal data is rendered.
     */
    public function testAccountRegistrationOnlyRendersMailAndPassword(): void
    {
        $this->assertRendersCredentialsOnly($this->getContent('GET', '/account/register'));
    }

    /**
     * The login page renders the same registration card, so it must not render the address or personal data either.
     */
    public function testLoginPageRegistrationOnlyRendersMailAndPassword(): void
    {
        $this->assertRendersCredentialsOnly($this->getContent('GET', '/account/login'));
    }

    /**
     * The guest checkout registration still collects the addresses, so the address form stays mandatory.
     */
    public function testCheckoutRegistrationKeepsAddressRequired(): void
    {
        $productNumber = 'qa-address-required-checkout';

        $this->createProduct(Uuid::randomHex(), $productNumber);

        $this->request(
            'POST',
            '/checkout/product/add-by-number',
            $this->tokenize('frontend.checkout.product.add-by-number', ['number' => $productNumber])
        );

        $content = $this->getContent('GET', '/checkout/register');

        static::assertStringNotContainsString('register-address-optional-hint', $content);
        static::assertNotNull($this->extractInputWithRequiredRule($content, 'billingAddress-AddressStreet'));
        static::assertNotNull($this->extractInputWithRequiredRule($content, 'billingAddressAddressCity'));
    }

    /**
     * The customer group registration reuses the same component but is not the account registration.
     */
    public function testCustomerGroupRegistrationKeepsAddressRequired(): void
    {
        $ids = new IdsCollection();
        $this->createCustomerGroup($ids);

        $content = $this->getContent('GET', 'customer-group-registration/' . $ids->get('group'));

        static::assertStringNotContainsString('register-address-optional-hint', $content);
        static::assertNotNull($this->extractInputWithRequiredRule($content, 'billingAddress-AddressStreet'));
        static::assertNotNull($this->extractInputWithRequiredRule($content, 'billingAddressAddressCity'));
    }

    private function getContent(string $method, string $url): string
    {
        $response = $this->request($method, $url, []);
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return (string) $response->getContent();
    }

    /**
     * Guards the registration card which is rendered by the account registration and by the login page.
     */
    private function assertRendersCredentialsOnly(string $content): void
    {
        static::assertStringContainsString('name="email"', $content);
        static::assertStringContainsString('name="password"', $content);
        static::assertStringContainsString('register-address-optional-hint', $content);

        // neither the address form nor the personal fields of the address form are rendered
        static::assertStringNotContainsString('name="billingAddress[', $content);
        static::assertStringNotContainsString('name="shippingAddress[', $content);
        static::assertStringNotContainsString('differentShippingAddress', $content);
        static::assertStringNotContainsString('register-billing', $content);
        static::assertStringNotContainsString('name="salutationId"', $content);
        static::assertStringNotContainsString('name="phoneNumber"', $content);
    }

    /**
     * Returns the rendered input tag when it is marked as required by the client side validation.
     */
    private function extractInputWithRequiredRule(string $content, string $id): ?string
    {
        if (!preg_match('/<input[^>]*id="' . preg_quote($id, '/') . '"[^>]*>/', $content, $matches)) {
            return null;
        }

        $input = $matches[0];

        return str_contains($input, 'data-validation="required"') || str_contains($input, 'required="required"') ? $input : null;
    }

    private function createCustomerGroup(IdsCollection $ids): void
    {
        $salesChannel = $this->getStorefrontSalesChannel();

        static::getContainer()->get('customer_group.repository')->create([
            [
                'id' => $ids->create('group'),
                'registrationActive' => true,
                'name' => 'qa address required',
                'registrationSalesChannels' => [['id' => $salesChannel->getId()]],
            ],
        ], Context::createDefaultContext());
    }

    private function createProduct(string $productId, string $productNumber): void
    {
        $context = Context::createDefaultContext();
        $salesChannel = $this->getStorefrontSalesChannel();

        static::getContainer()->get('product.repository')->create([
            [
                'id' => $productId,
                'name' => 'QA product',
                'productNumber' => $productNumber,
                'stock' => 5,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 15.99, 'net' => 10, 'linked' => false],
                ],
                'tax' => ['id' => Uuid::randomHex(), 'name' => 'qaTaxRate', 'taxRate' => 15],
                'categories' => [['id' => $productId, 'name' => 'QA category']],
                'visibilities' => [
                    [
                        'id' => $productId,
                        'salesChannelId' => $salesChannel->getId(),
                        'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
            ],
        ], $context);
    }

    private function getStorefrontSalesChannel(): SalesChannelEntity
    {
        $salesChannel = static::getContainer()->get('sales_channel.repository')->search(
            (new Criteria())->addFilter(
                new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
                new EqualsFilter('domains.url', $_SERVER['APP_URL'])
            ),
            Context::createDefaultContext()
        )->getEntities()->first();

        static::assertInstanceOf(SalesChannelEntity::class, $salesChannel);

        return $salesChannel;
    }
}
