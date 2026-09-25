<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer\EntityProtection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\PluginDefinition;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\EntityProtection\_fixtures\PluginProtectionExtension;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\EntityProtection\_fixtures\SystemConfigExtension;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\EntityProtection\_fixtures\UserAccessKeyExtension;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelCollection;
use Shopwell\Core\System\SystemConfig\SystemConfigDefinition;
use Shopwell\Core\System\User\Aggregate\UserAccessKey\UserAccessKeyDefinition;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
class EntityProtectionValidatorTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;
    use IntegrationTestBehaviour;

    protected function setUp(): void
    {
        $this->registerDefinitionWithExtensions(PluginDefinition::class, PluginProtectionExtension::class);
        $this->registerDefinitionWithExtensions(SystemConfigDefinition::class, SystemConfigExtension::class);
        $this->registerDefinitionWithExtensions(UserAccessKeyDefinition::class, UserAccessKeyExtension::class);
    }

    #[DataProvider('blockedApiRequest')]
    public function testItBlocksApiAccess(string $method, string $url): void
    {
        $this->getBrowser()
            ->jsonRequest(
                $method,
                '/api/' . $url
            );

        $response = $this->getBrowser()->getResponse();

        static::assertIsString($response->getContent());
        static::assertSame(403, $response->getStatusCode(), $response->getContent());
    }

    /**
     * @return array<array<string>>
     */
    public static function blockedApiRequest(): array
    {
        return [
            ['GET', 'plugin/' . Uuid::randomHex()], // detail
            ['GET', 'plugin'], // list
            ['POST', 'plugin'], // create
            ['PATCH', 'plugin/' . Uuid::randomHex()], // update
            ['DELETE', 'plugin/' . Uuid::randomHex()], // delete
            ['POST', 'search/plugin'], // search
            ['POST', 'search-ids/plugin'], // search ids

            // nested routes
            ['POST', 'search/user/' . Uuid::randomHex() . '/access-keys'], // search
            ['POST', 'search-ids/user/' . Uuid::randomHex() . '/access-keys'], // search ids
        ];
    }

    public function testItAllowsReadsOnEntitiesWithWriteProtectionOnly(): void
    {
        $this->getBrowser()
            ->jsonRequest(
                'GET',
                '/api/system-config'
            );

        $response = $this->getBrowser()->getResponse();

        static::assertIsString($response->getContent());
        static::assertNotSame(403, $response->getStatusCode(), $response->getContent());

        $this->getBrowser()
            ->jsonRequest(
                'GET',
                '/api/system-config/' . Uuid::randomHex()
            );

        $response = $this->getBrowser()->getResponse();

        static::assertIsString($response->getContent());
        static::assertNotSame(403, $response->getStatusCode(), $response->getContent());

        $this->getBrowser()
            ->jsonRequest(
                'POST',
                '/api/system-config'
            );

        $response = $this->getBrowser()->getResponse();

        static::assertIsString($response->getContent());
        static::assertSame(403, $response->getStatusCode(), $response->getContent());
    }

    public function testItBlocksReadsOnForbiddenAssociations(): void
    {
        $this->getBrowser()
            ->jsonRequest(
                'POST',
                '/api/search/user',
                [
                    'associations' => [
                        'accessKeys' => [],
                    ],
                ]
            );

        $response = $this->getBrowser()->getResponse();

        static::assertIsString($response->getContent());
        static::assertSame(403, $response->getStatusCode(), $response->getContent());

        $this->getBrowser()
            ->jsonRequest(
                'POST',
                '/api/search/user',
                [
                    'associations' => [
                        'avatarMedia' => [],
                    ],
                ]
            );

        $response = $this->getBrowser()->getResponse();

        static::assertIsString($response->getContent());
        static::assertNotSame(403, $response->getStatusCode(), $response->getContent());
    }

    public function testItBlocksReadsOnForbiddenNestedAssociations(): void
    {
        $this->getBrowser()
            ->jsonRequest(
                'POST',
                '/api/search/media',
                [
                    'associations' => [
                        'user' => [
                            'associations' => [
                                'accessKeys' => [],
                            ],
                        ],
                    ],
                ]
            );

        $response = $this->getBrowser()->getResponse();

        static::assertIsString($response->getContent());
        static::assertSame(403, $response->getStatusCode(), $response->getContent());

        $this->getBrowser()
            ->jsonRequest(
                'POST',
                '/api/search/media',
                [
                    'associations' => [
                        'user' => [
                            'associations' => [
                                'avatarMedia' => [],
                            ],
                        ],
                    ],
                ]
            );

        $response = $this->getBrowser()->getResponse();

        static::assertIsString($response->getContent());
        static::assertNotSame(403, $response->getStatusCode(), $response->getContent());
    }

    public function testItDoesNotValidateCascadeDeletes(): void
    {
        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $countBefore = $salesChannelRepository->search(new Criteria(), Context::createDefaultContext())->getTotal();

        // system_config has a cascade delete on sales_channel
        $this->getBrowser()
            ->jsonRequest(
                'DELETE',
                '/api/sales-channel/' . TestDefaults::SALES_CHANNEL
            );

        $response = $this->getBrowser()->getResponse();

        static::assertIsString($response->getContent());
        static::assertSame(204, $response->getStatusCode(), $response->getContent());

        static::assertSame(
            $countBefore - 1,
            $salesChannelRepository->search(new Criteria(), Context::createDefaultContext())->getTotal()
        );
    }
}
