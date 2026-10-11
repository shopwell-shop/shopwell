<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\Test\Integration\Traits\CustomerTestTrait;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('checkout')]
#[Group('store-api')]
class ListAddressRouteTest extends TestCase
{
    use CustomerTestTrait;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);

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

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }

    public function testListAddresses(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/list-address',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $response['total']);
        static::assertNotCount(0, $response['elements']);
        static::assertSame('Max Mustermann', $response['elements'][0]['name']);
        static::assertSame('Musterstraße 1', $response['elements'][0]['street']);
        static::assertSame('Schöppingen', $response['elements'][0]['city']);
        static::assertSame('12345', $response['elements'][0]['zipcode']);
        static::assertSame($this->getValidCountryId(), $response['elements'][0]['countryId']);
        static::assertSame($this->getValidSalutationId(), $response['elements'][0]['salutation']['id']);
        static::assertTrue($response['elements'][0]['isDefaultBillingAddress']);
        static::assertTrue($response['elements'][0]['isDefaultShippingAddress']);
    }

    public function testListAddressesIncludes(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/list-address',
                [
                    'includes' => [
                        'customer_address' => [
                            'name',
                        ],
                    ],
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $response['total']);
        static::assertNotCount(0, $response['elements']);
        static::assertSame([
            'name' => 'Max Mustermann',
            'apiAlias' => 'customer_address',
        ], $response['elements'][0]);
    }

    public function testListAddressForGuest(): void
    {
        $contextToken = $this->getLoggedInContextToken($this->createCustomer(null, true), $this->ids->get('sales-channel'));

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/list-address',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $response['total']);
        static::assertNotCount(0, $response['elements']);
        static::assertSame('Max Mustermann', $response['elements'][0]['name']);
        static::assertSame('Musterstraße 1', $response['elements'][0]['street']);
        static::assertSame('Schöppingen', $response['elements'][0]['city']);
        static::assertSame('12345', $response['elements'][0]['zipcode']);
        static::assertSame($this->getValidCountryId(), $response['elements'][0]['countryId']);
        static::assertSame($this->getValidSalutationId(), $response['elements'][0]['salutation']['id']);
    }
}
