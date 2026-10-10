<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Installer\Controller;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Installer\Configuration\AdminConfigurationService;
use Shopwell\Core\Installer\Configuration\EnvConfigWriter;
use Shopwell\Core\Installer\Configuration\ShopConfigurationService;
use Shopwell\Core\Installer\Controller\ShopConfigurationController;
use Shopwell\Core\Installer\Database\BlueGreenDeploymentService;
use Shopwell\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Shopwell\Core\Maintenance\System\Struct\DatabaseConnectionInformation;
use Shopwell\Core\System\Snippet\DataTransfer\Language\Language;
use Shopwell\Core\System\Snippet\DataTransfer\Language\LanguageCollection;
use Shopwell\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Shopwell\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShopConfigurationController::class)]
class ShopConfigurationControllerTest extends TestCase
{
    use EnvTestBehaviour;
    use InstallerControllerTestTrait;

    private MockObject&Environment $twig;

    private MockObject&RouterInterface $router;

    private Connection&Stub $connection;

    private MockObject&EnvConfigWriter $envConfigWriter;

    private MockObject&ShopConfigurationService $shopConfigService;

    private MockObject&AdminConfigurationService $adminConfigService;

    private ShopConfigurationController $controller;

    /**
     * @var TranslatorInterface&Stub
     */
    private TranslatorInterface $translator;

    protected function setUp(): void
    {
        $this->twig = $this->createMock(Environment::class);
        $this->router = $this->createMock(RouterInterface::class);

        $this->connection = static::createStub(Connection::class);
        $connectionFactory = static::createStub(DatabaseConnectionFactory::class);
        $connectionFactory->method('getConnection')->willReturn($this->connection);

        $this->envConfigWriter = $this->createMock(EnvConfigWriter::class);
        $this->shopConfigService = $this->createMock(ShopConfigurationService::class);
        $this->adminConfigService = $this->createMock(AdminConfigurationService::class);
        $this->translator = static::createStub(TranslatorInterface::class);

        $translationConfig = new TranslationConfig(
            new Uri('http://localhost:8000'),
            [],
            [],
            new LanguageCollection([
                new Language('en-US', 'English (US)'),
            ]),
            new PluginMappingCollection(),
            new Uri('http://localhost:8000/metadata.json'),
            []
        );
        $this->controller = new ShopConfigurationController(
            $connectionFactory,
            $this->envConfigWriter,
            $this->shopConfigService,
            $this->adminConfigService,
            $this->translator,
            $translationConfig,
            [
                'zh' => ['id' => 'zh-CN', 'label' => '简体中文'],
                'en-US' => ['id' => 'en-US', 'label' => 'English (US)'],
                'en' => ['id' => 'en-GB', 'label' => 'English (UK)'],
                'de-AT' => ['id' => 'de-AT', 'label' => 'Deutsch (Österreich)'],
                'de-CH' => ['id' => 'de-CH', 'label' => 'Deutsch (Schweiz)'],
            ],
            ['EUR', 'USD', 'GBP']
        );
        $this->controller->setContainer($this->getInstallerContainer($this->twig, ['router' => $this->router]));
    }

    #[DataProvider('shopConfigurationPresetProvider')]
    public function testGetConfigurationRoute(
        string $requestLocale,
        string $expectedShopLanguage,
        string $expectedPresetCurrency,
        string $expectedCountryIsoDefault
    ): void {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $session->set(DatabaseConnectionInformation::class, new DatabaseConnectionInformation());
        $session->set(BlueGreenDeploymentService::ENV_NAME, true);
        $request->setMethod('GET');
        $request->setSession($session);
        $request->attributes->set('_locale', $requestLocale);

        $this->router->expects($this->never())->method('generate');
        $this->envConfigWriter->expects($this->never())->method('writeConfig');
        $this->shopConfigService->expects($this->never())->method('updateShop');
        $this->adminConfigService->expects($this->never())->method('createAdmin');

        $this->connection->method('fetchAllAssociative')
            ->willReturn([
                ['iso3' => 'CHN', 'iso' => 'CN'],
                ['iso3' => 'DEU', 'iso' => 'DE'],
                ['iso3' => 'GBR', 'iso' => 'GB'],
                ['iso3' => 'USA', 'iso' => 'US'],
            ]);

        $this->translator->method('trans')->willReturnCallback(
            function (string $key): string {
                return $this->getLanguageTranslations()[$key] ?? $key;
            }
        );

        $this->twig->expects($this->once())->method('render')
            ->with(
                '@Installer/installer/shop-configuration.html.twig',
                array_merge($this->getDefaultViewParams(), [
                    'error' => null,
                    'countryIsos' => [
                        ['iso3' => 'CHN', 'default' => $expectedCountryIsoDefault === 'CHN', 'translated' => 'shopwell.installer.select_country_chn'],
                        ['iso3' => 'DEU', 'default' => $expectedCountryIsoDefault === 'DEU', 'translated' => 'shopwell.installer.select_country_deu'],
                        ['iso3' => 'GBR', 'default' => $expectedCountryIsoDefault === 'GBR', 'translated' => 'shopwell.installer.select_country_gbr'],
                        ['iso3' => 'USA', 'default' => $expectedCountryIsoDefault === 'USA', 'translated' => 'shopwell.installer.select_country_usa'],
                    ],
                    'currencyIsos' => ['EUR', 'USD', 'GBP'],
                    'languageIsos' => [
                        'zh' => ['id' => 'zh-CN', 'label' => '简体中文'],
                        'en-US' => ['id' => 'en-US', 'label' => 'English (US)'],
                        'en' => ['id' => 'en-GB', 'label' => 'English (UK)'],
                        'de-AT' => ['id' => 'de-AT', 'label' => 'Deutsch (Österreich)'],
                        'de-CH' => ['id' => 'de-CH', 'label' => 'Deutsch (Schweiz)'],
                    ],
                    'allAvailableLanguages' => [
                        'zh' => ['id' => 'zh-CN', 'label' => '简体中文'],
                        'en-GB' => ['id' => 'en-GB', 'label' => 'English'],
                        'en-US' => ['id' => 'en-US', 'label' => 'English (US)'],
                    ],
                    'parameters' => [
                        'config_shop_language' => $expectedShopLanguage,
                        'config_shop_currency' => $expectedPresetCurrency,
                    ],
                    'selectedLanguages' => [],
                ])
            )
            ->willReturn('config');

        $response = $this->controller->shopConfiguration($request);
        static::assertSame('config', $response->getContent());
    }

    public function testGetConfigurationRouteRedirectsIfSessionIsExpired(): void
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setMethod('GET');
        $request->setSession($session);

        $this->envConfigWriter->expects($this->never())->method('writeConfig');
        $this->shopConfigService->expects($this->never())->method('updateShop');
        $this->adminConfigService->expects($this->never())->method('createAdmin');

        $this->router->expects($this->once())->method('generate')
            ->with('installer.database-configuration', [], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/installer/database-configuration');

        $this->twig->expects($this->never())->method('render');

        $response = $this->controller->shopConfiguration($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/installer/database-configuration', $response->getTargetUrl());
    }

    public function testPostConfigurationRoute(): void
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setMethod('POST');
        $connectionInfo = new DatabaseConnectionInformation();
        $session->set(DatabaseConnectionInformation::class, $connectionInfo);
        $session->set(BlueGreenDeploymentService::ENV_NAME, true);
        $request->setSession($session);
        $request->attributes->set('_locale', 'zh');

        $request->request->set('config_admin_email', 'test@test.com');
        $request->request->set('config_admin_username', 'admin');
        $request->request->set('config_admin_name', 'first last');
        $request->request->set('config_admin_password', 'shopwell');

        $request->request->set('config_shop_language', 'zh-CN');
        $request->request->set('config_shop_currency', 'EUR');
        $request->request->set('config_shop_country', 'DEU');
        $request->request->set('config_shopName', 'shop');
        $request->request->set('config_mail', 'info@test.com');
        $request->request->set('available_currencies', ['EUR', 'USD', 'GBP']);

        $this->setEnvVars([
            'HTTPS' => 'on',
            'HTTP_HOST' => 'localhost',
            'SCRIPT_NAME' => '/shop/index.php',
        ]);

        $expectedShopInfo = [
            'name' => 'shop',
            'locale' => 'zh-CN',
            'currency' => 'EUR',
            'additionalCurrencies' => ['EUR', 'USD', 'GBP'],
            'country' => 'DEU',
            'email' => 'info@test.com',
            'host' => 'localhost',
            'schema' => 'https',
            'basePath' => '/shop',
            'blueGreenDeployment' => true,
        ];

        $this->envConfigWriter->expects($this->once())->method('writeConfig')->with($connectionInfo, $expectedShopInfo);
        $this->shopConfigService->expects($this->once())->method('updateShop')->with($expectedShopInfo, $this->connection);

        $localeId = Uuid::randomHex();
        $this->connection->method('fetchOne')->willReturn($localeId);

        $expectedAdmin = [
            'email' => 'test@test.com',
            'username' => 'admin',
            'name' => 'first last',
            'password' => 'shopwell',
            'localeId' => $localeId,
        ];
        $this->adminConfigService->expects($this->once())->method('createAdmin')->with($expectedAdmin, $this->connection);

        $this->translator->method('trans')->willReturnCallback(static fn (string $key): string => $key);

        $this->router->expects($this->once())->method('generate')
            ->with('installer.finish', ['completed' => true], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/installer/finish?completed=1');

        $this->twig->expects($this->never())->method('render');

        $response = $this->controller->shopConfiguration($request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/installer/finish?completed=1', $response->getTargetUrl());

        static::assertFalse($session->has(DatabaseConnectionInformation::class));
        static::assertSame($expectedAdmin, $session->get('ADMIN_USER'));
    }

    public function testPostConfigurationRouteOnError(): void
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $session->set(DatabaseConnectionInformation::class, new DatabaseConnectionInformation());
        $session->set(BlueGreenDeploymentService::ENV_NAME, true);
        $request->setMethod('POST');
        $request->setSession($session);
        $request->attributes->set('_locale', 'zh');

        $this->setEnvVars([
            'HTTPS' => 'on',
            'HTTP_HOST' => 'localhost',
            'SCRIPT_NAME' => '/shop/index.php',
        ]);

        $this->connection->method('fetchAllAssociative')
            ->willReturn([
                ['iso3' => 'CHN', 'iso' => 'CN'],
                ['iso3' => 'DEU', 'iso' => 'DE'],
                ['iso3' => 'GBR', 'iso' => 'GB'],
                ['iso3' => 'USA', 'iso' => 'US'],
            ]);
        $this->connection->method('fetchOne')->willReturn('not-relevant');

        $this->router->expects($this->never())->method('generate');
        $this->shopConfigService->expects($this->never())->method('updateShop');
        $this->adminConfigService->expects($this->never())->method('createAdmin');

        $this->envConfigWriter->expects($this->once())->method('writeConfig')->willThrowException(new \Exception('Test Exception'));

        $this->translator->method('trans')->willReturnCallback(
            function (string $key): string {
                return $this->getLanguageTranslations()[$key] ?? $key;
            }
        );
        $this->twig->expects($this->once())->method('render')
            ->with(
                '@Installer/installer/shop-configuration.html.twig',
                array_merge($this->getDefaultViewParams(), [
                    'error' => 'Test Exception',
                    'countryIsos' => [
                        ['iso3' => 'CHN', 'default' => true, 'translated' => 'shopwell.installer.select_country_chn'],
                        ['iso3' => 'DEU', 'default' => false, 'translated' => 'shopwell.installer.select_country_deu'],
                        ['iso3' => 'GBR', 'default' => false, 'translated' => 'shopwell.installer.select_country_gbr'],
                        ['iso3' => 'USA', 'default' => false, 'translated' => 'shopwell.installer.select_country_usa'],
                    ],
                    'currencyIsos' => ['EUR', 'USD', 'GBP'],
                    'languageIsos' => [
                        'zh' => ['id' => 'zh-CN', 'label' => '简体中文'],
                        'en-US' => ['id' => 'en-US', 'label' => 'English (US)'],
                        'en' => ['id' => 'en-GB', 'label' => 'English (UK)'],
                        'de-AT' => ['id' => 'de-AT', 'label' => 'Deutsch (Österreich)'],
                        'de-CH' => ['id' => 'de-CH', 'label' => 'Deutsch (Schweiz)'],
                    ],
                    'allAvailableLanguages' => [
                        'zh' => ['id' => 'zh-CN', 'label' => '简体中文'],
                        'en-GB' => ['id' => 'en-GB', 'label' => 'English'],
                        'en-US' => ['id' => 'en-US', 'label' => 'English (US)'],
                    ],
                    'parameters' => [
                        'config_shop_language' => 'zh-CN',
                        'config_shop_currency' => 'CNY',
                    ],
                    'selectedLanguages' => [],
                ])
            )
            ->willReturn('config');

        $response = $this->controller->shopConfiguration($request);
        static::assertSame('config', $response->getContent());
    }

    public function testGetConfigurationCountryIsosSortedByAlphabetical(): void
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $session->set(DatabaseConnectionInformation::class, new DatabaseConnectionInformation());
        $session->set(BlueGreenDeploymentService::ENV_NAME, true);
        $request->setMethod('POST');
        $request->setSession($session);
        $request->attributes->set('_locale', 'zh');

        $this->setEnvVars([
            'HTTPS' => 'on',
            'HTTP_HOST' => 'localhost',
            'SCRIPT_NAME' => '/shop/index.php',
        ]);

        // in non-alphabetical order
        $countries = [
            ['iso3' => 'GBR', 'iso' => 'GB'],
            ['iso3' => 'BGR', 'iso' => 'BG'],
            ['iso3' => 'EST', 'iso' => 'EE'],
            ['iso3' => 'HRV', 'iso' => 'HR'],
            ['iso3' => 'DEU', 'iso' => 'DE'],
        ];

        $this->connection->method('fetchAllAssociative')
            ->willReturn($countries);
        $this->connection->method('fetchOne')->willReturn('not-relevant');

        $this->router->expects($this->never())->method('generate');
        $this->shopConfigService->expects($this->never())->method('updateShop');
        $this->adminConfigService->expects($this->never())->method('createAdmin');

        $this->envConfigWriter->expects($this->once())->method('writeConfig')->willThrowException(new \Exception('Test Exception'));

        $this->translator->method('trans')->willReturnCallback(
            function (string $key): string {
                $allTranslations = array_merge(
                    $this->getLanguageTranslations(),
                    $this->getCountryTranslations()
                );

                return $allTranslations[$key] ?? $key;
            }
        );

        $this->twig->expects($this->once())->method('render')->willReturnCallback(static function (string $view, array $parameters): string {
            static::assertSame('@Installer/installer/shop-configuration.html.twig', $view);
            static::assertArrayHasKey('countryIsos', $parameters);

            $countryIsos = $parameters['countryIsos'];

            static::assertSame([
                'Bulgaria',
                'Croatia',
                'Estonia',
                'Germany',
                'Great Britain',
            ], array_column($countryIsos, 'translated'));

            return '';
        });

        $this->controller->shopConfiguration($request);
    }

    public static function shopConfigurationPresetProvider(): \Generator
    {
        yield ['zh', 'zh-CN', 'CNY', 'CHN'];
        yield ['en-US', 'en-US', 'USD', 'USA'];
        yield ['en', 'en-GB', 'GBP', 'GBR'];
    }

    /**
     * @return array<string, string>
     */
    private function getLanguageTranslations(): array
    {
        return [
            'shopwell.installer.select_language_zh' => '简体中文',
            'shopwell.installer.select_language_en-GB' => 'English',
            'shopwell.installer.select_language_en-US' => 'English (US)',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getCountryTranslations(): array
    {
        return [
            'shopwell.installer.select_country_gbr' => 'Great Britain',
            'shopwell.installer.select_country_bgr' => 'Bulgaria',
            'shopwell.installer.select_country_est' => 'Estonia',
            'shopwell.installer.select_country_hrv' => 'Croatia',
            'shopwell.installer.select_country_deu' => 'Germany',
        ];
    }
}
