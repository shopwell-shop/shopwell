<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Store\Authentication;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Authentication\FrwRequestOptionsProvider;
use Shopwell\Core\Framework\Store\Services\FirstRunWizardService;
use Shopwell\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\User\Aggregate\UserConfig\UserConfigCollection;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class FrwRequestOptionsProviderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    private Context $context;

    private FrwRequestOptionsProvider $optionsProvider;

    /**
     * @var EntityRepository<UserConfigCollection>
     */
    private EntityRepository $userConfigRepository;

    protected function setUp(): void
    {
        $this->context = $this->createAdminStoreContext();
        $this->optionsProvider = static::getContainer()->get(FrwRequestOptionsProvider::class);
        $this->userConfigRepository = static::getContainer()->get('user_config.repository');
    }

    public function testSetsFrwUserTokenIfPresentInUserConfig(): void
    {
        $frwUserToken = 'a84a653a57dc43a48ded4275524893cf';

        $source = $this->context->getSource();
        static::assertInstanceOf(AdminApiSource::class, $source);

        $this->userConfigRepository->create([
            [
                'userId' => $source->getUserId(),
                'key' => FirstRunWizardService::USER_CONFIG_KEY_FRW_USER_TOKEN,
                'value' => [
                    FirstRunWizardService::USER_CONFIG_VALUE_FRW_USER_TOKEN => $frwUserToken,
                ],
            ],
        ], Context::createDefaultContext());

        $headers = $this->optionsProvider->getAuthenticationHeader($this->context);

        static::assertArrayHasKey('X-Shopwell-Token', $headers);
        static::assertSame($frwUserToken, $headers['X-Shopwell-Token']);
    }

    public function testRemovesEmptyAuthenticationHeaderIfFrwUserTokenIsNotSet(): void
    {
        $headers = $this->optionsProvider->getAuthenticationHeader($this->context);

        static::assertEmpty($headers);
    }

    public function testThrowsInvalidContextSourceExceptionIfNotAdminApiSource(): void
    {
        static::expectException(InvalidContextSourceException::class);

        $this->optionsProvider->getAuthenticationHeader(Context::createDefaultContext());
    }
}
