<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * The storefront writes full width spaces into the address fields, which must be treated as "not filled".
 *
 * @internal
 */
#[Package('checkout')]
#[Group('store-api')]
class FullWidthSpaceAddressValidationTest extends TestCase
{
    use CountryAddToSalesChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const FULL_WIDTH_SPACE = "\u{3000}";

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->addCountriesToSalesChannel([], $this->ids->get('sales-channel'));

        $this->assignSalesChannelContext($this->browser);
    }

    public function testStreetWithOnlyFullWidthSpacesIsRejected(): void
    {
        $this->registerCustomer();

        $response = $this->createAddress([
            'street' => self::FULL_WIDTH_SPACE,
            'city' => 'Shanghai',
        ]);

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());
        static::assertContains('VIOLATION::STREET_IS_BLANK_ERROR', $this->getErrorDetails($response));
    }

    public function testCityWithOnlyFullWidthSpacesIsRejected(): void
    {
        $this->registerCustomer();

        $response = $this->createAddress([
            'street' => 'Nanjing Road 1',
            'city' => self::FULL_WIDTH_SPACE,
        ]);

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());
        static::assertContains('VIOLATION::CITY_IS_BLANK_ERROR', $this->getErrorDetails($response));
    }

    public function testSurroundingFullWidthSpacesAreTrimmed(): void
    {
        $this->registerCustomer();

        $this->createAddress([
            'street' => self::FULL_WIDTH_SPACE . 'Nanjing Road 1' . self::FULL_WIDTH_SPACE,
            'city' => self::FULL_WIDTH_SPACE . 'Shanghai' . self::FULL_WIDTH_SPACE,
        ]);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function createAddress(array $overrides = []): array
    {
        $this->browser->request(
            'POST',
            '/store-api/account/address',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(array_merge([
                'name' => 'Max Mustermann',
                'countryId' => $this->getValidCountryId($this->ids->get('sales-channel')),
                'zipcode' => '200000',
            ], $overrides), \JSON_THROW_ON_ERROR)
        );

        return json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * A customer without any address, so that only the address validation itself can fail.
     */
    private function registerCustomer(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/account/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'qa.full-width@example.com',
                'password' => 'Password123!',
                'acceptedDataProtection' => true,
            ], \JSON_THROW_ON_ERROR)
        );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($this->browser->getResponse()->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));

        // the registration returns a token of the logged in customer, which is needed for the address route
        $contextToken = $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertIsString($contextToken);
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return list<string>
     */
    private function getErrorDetails(array $response): array
    {
        $details = [];

        foreach ($response['errors'] ?? [] as $error) {
            $details[] = (string) ($error['detail'] ?? '');
        }

        return $details;
    }
}
