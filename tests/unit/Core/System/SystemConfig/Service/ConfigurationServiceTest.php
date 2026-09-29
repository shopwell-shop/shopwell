<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\SystemConfig\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopwell\Core\Framework\App\AppCollection;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin;
use Shopwell\Core\Framework\Util\UtilException;
use Shopwell\Core\System\SystemConfig\Service\AppConfigReader;
use Shopwell\Core\System\SystemConfig\Service\ConfigurationService;
use Shopwell\Core\System\SystemConfig\SystemConfigException;
use Shopwell\Core\System\SystemConfig\Util\ConfigReader;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
#[Package('framework')]
#[CoversClass(ConfigurationService::class)]
class ConfigurationServiceTest extends TestCase
{
    /**
     * @var array<mixed>
     */
    private array $serverVarsBackup;

    /**
     * @var array<mixed>
     */
    private array $envVarsBackup;

    /**
     * @var array<string, FeatureFlagConfig>
     */
    private array $featureConfigBackup;

    protected function setUp(): void
    {
        $this->serverVarsBackup = $_SERVER;
        $this->envVarsBackup = $_ENV;
        $this->featureConfigBackup = Feature::getRegisteredFeatures();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverVarsBackup;
        $_ENV = $this->envVarsBackup;
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($this->featureConfigBackup);
    }

    public function testInvalidDomain(): void
    {
        $this->expectExceptionObject(SystemConfigException::invalidDomain());

        $appRepository = new StaticEntityRepository([]);
        $configService = new ConfigurationService(
            [],
            new ConfigReader(),
            static::createStub(AppConfigReader::class),
            $appRepository,
            new StaticSystemConfigService([]),
            new NullLogger()
        );

        $configService->getConfiguration('invalid!', Context::createDefaultContext());
    }

    public function testCheckConfigurationWithInvalidDomain(): void
    {
        $appRepository = new StaticEntityRepository([]);
        $configService = new ConfigurationService(
            [],
            new ConfigReader(),
            static::createStub(AppConfigReader::class),
            $appRepository,
            new StaticSystemConfigService([]),
            new NullLogger()
        );

        static::assertFalse($configService->checkConfiguration('invalid!', Context::createDefaultContext()));
    }

    public function testMissingConfig(): void
    {
        $appRepository = new StaticEntityRepository([new AppCollection([])]);
        $configService = new ConfigurationService(
            [],
            new ConfigReader(),
            static::createStub(AppConfigReader::class),
            $appRepository,
            new StaticSystemConfigService([]),
            new NullLogger()
        );

        $this->expectExceptionObject(SystemConfigException::configurationNotFound('missing'));
        $configService->getConfiguration('missing', Context::createDefaultContext());
    }

    public function testConfigurationFeatureFlag(): void
    {
        Feature::registerFeature('FEATURE_NEXT_101');
        Feature::registerFeature('FEATURE_NEXT_102');

        $_SERVER['FEATURE_NEXT_101'] = '1';
        $_SERVER['FEATURE_NEXT_102'] = '1';
        static::assertTrue(Feature::isActive('FEATURE_NEXT_101'));
        static::assertTrue(Feature::isActive('FEATURE_NEXT_102'));

        $actualConfig = $this->getConfiguration($this->getAppConfig());

        $expectedConfigWithoutValues = $this->getConfigWithoutValues();

        static::assertSame($expectedConfigWithoutValues, $actualConfig);
        static::assertSame($expectedConfigWithoutValues[0]['elements'][0], $actualConfig[0]['elements'][0]);
        static::assertSame($expectedConfigWithoutValues[0]['elements'][2], $actualConfig[0]['elements'][2]);
    }

    public function testConfigurationIsSequentiallyIndexedWhenFeatureFlagNotEnabled(): void
    {
        Feature::registerFeature('FEATURE_NEXT_101');
        Feature::registerFeature('FEATURE_NEXT_102');

        $_SERVER['FEATURE_NEXT_101'] = '0';
        $_SERVER['FEATURE_NEXT_102'] = '0';
        static::assertFalse(Feature::isActive('FEATURE_NEXT_101'));
        static::assertFalse(Feature::isActive('FEATURE_NEXT_102'));

        $config = $this->getAppConfig();

        unset($config[0]['flag']); // make card not rely on feature flag (won't be removed)
        $config[0]['elements'][0]['flag'] = 'FEATURE_NEXT_102'; // make first element rely on feature flag (will be removed)

        // create new card at position 0 and make it rely on feature flag (will be removed)
        array_unshift($config, [
            'title' => [
                'en-GB' => 'Advanced configuration',
                'zh-CN' => '基础设置',
            ],
            'name' => null,
            'elements' => [],
            'flag' => 'FEATURE_NEXT_101',
        ]);

        $actualConfig = $this->getConfiguration($config);

        static::assertIsList($actualConfig);
        static::assertCount(1, $actualConfig);
        static::assertIsList($actualConfig[0]['elements']);
        static::assertCount(1, $actualConfig[0]['elements']);
    }

    public function testConfigurationNoFeatureFlag(): void
    {
        $actualConfig = $this->getConfiguration($this->getAppConfig());

        static::assertEmpty($actualConfig);
    }

    public function testEmptyConfigThrowsError(): void
    {
        $this->expectExceptionObject(SystemConfigException::configurationNotFound('SwagExampleTest'));

        $this->getConfiguration([]);
    }

    public function testElementWithFlag(): void
    {
        $config = [
            0 => [
                'title' => [
                    'en-GB' => 'Basic configuration',
                    'zh-CN' => '基础设置',
                ],
                'name' => null,
                'elements' => [
                    [
                        'name' => 'SwagExampleTest.email',
                        'type' => 'text',
                        'flag' => 'FEATURE_NEXT_101',
                        'config' => [
                            'copyable' => true,
                            'label' => [
                                'en-GB' => 'eMail',
                                'zh-CN' => '电子邮箱',
                            ],
                            'placeholder' => [
                                'en-GB' => 'Enter your eMail address',
                                'zh-CN' => '请输入您的邮箱地址',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $actualConfig = $this->getConfiguration($config);
        static::assertSame([], $actualConfig[0]['elements']);
    }

    public function testCacheRelevantMetadataIsExposedInElementConfig(): void
    {
        $config = [
            [
                'title' => [
                    'en-GB' => 'Basic configuration',
                ],
                'name' => null,
                'elements' => [
                    [
                        'name' => 'storefrontVisibility',
                        'type' => 'bool',
                        'cacheRelevant' => true,
                    ],
                ],
            ],
        ];

        $actualConfig = $this->getConfiguration($config);

        static::assertTrue($actualConfig[0]['elements'][0]['config']['cacheRelevant']);
    }

    public function testConfigFromPlugin(): void
    {
        $config = [
            [
                'title' => [
                    'en-GB' => 'Basic configuration',
                    'zh-CN' => '基础设置',
                ],
                'name' => null,
                'elements' => [
                    [
                        'name' => 'email',
                        'type' => 'text',
                        'config' => [
                            'copyable' => true,
                            'label' => [
                                'en-GB' => 'eMail',
                                'zh-CN' => '电子邮箱',
                            ],
                            'placeholder' => [
                                'en-GB' => 'Enter your eMail address',
                                'zh-CN' => '请输入您的邮箱地址',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $configReader = static::createStub(ConfigReader::class);
        $configReader->method('getConfigFromBundle')->willReturn($config);

        $appRepository = new StaticEntityRepository([new AppCollection()]);
        $service = new ConfigurationService(
            [
                new SwagExampleTest(true, ''),
            ],
            $configReader,
            static::createStub(AppConfigReader::class),
            $appRepository,
            new StaticSystemConfigService([]),
            new NullLogger()
        );

        $actualConfig = $service->getConfiguration('SwagExampleTest', Context::createDefaultContext());

        static::assertCount(1, $actualConfig);
        static::assertCount(1, $actualConfig[0]['elements']);
        static::assertSame('SwagExampleTest.email', $actualConfig[0]['elements'][0]['name']);
    }

    public function testEnrichConfig(): void
    {
        $config = [
            [
                'title' => [
                    'en-GB' => 'Basic configuration',
                    'zh-CN' => '基础设置',
                ],
                'elements' => [
                    [
                        'name' => 'email',
                        'type' => 'text',
                        'config' => [
                            'copyable' => true,
                            'label' => [
                                'en-GB' => 'eMail',
                                'zh-CN' => '电子邮箱',
                            ],
                            'placeholder' => [
                                'en-GB' => 'Enter your eMail address',
                                'zh-CN' => '请输入您的邮箱地址',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => [
                    'en-GB' => 'Foo',
                ],
            ],
        ];

        $configReader = static::createStub(ConfigReader::class);
        $configReader->method('getConfigFromBundle')->willReturn($config);

        /** @var StaticEntityRepository<AppCollection> */
        $repository = new StaticEntityRepository([new AppCollection()]);

        $service = new ConfigurationService(
            [
                new SwagExampleTest(true, ''),
            ],
            $configReader,
            static::createStub(AppConfigReader::class),
            $repository,
            new StaticSystemConfigService(['SwagExampleTest.email' => 'foo']),
            new NullLogger()
        );

        $actualConfig = $service->getResolvedConfiguration('SwagExampleTest', Context::createDefaultContext());

        static::assertCount(2, $actualConfig);
        static::assertCount(1, $actualConfig[0]['elements']);
        static::assertSame('SwagExampleTest.email', $actualConfig[0]['elements'][0]['name']);
        static::assertSame('foo', $actualConfig[0]['elements'][0]['value']);
    }

    public function testCheckConfigurationReturnsFalseOnXmlParsingException(): void
    {
        $configReader = static::createStub(ConfigReader::class);
        $configReader->method('getConfigFromBundle')->willThrowException(
            UtilException::xmlParsingException('/path/to/config.xml', 'Invalid XML: element name contains underscores')
        );

        $appRepository = new StaticEntityRepository([new AppCollection([])]);
        $configService = new ConfigurationService(
            [new SwagExampleTest(true, '')],
            $configReader,
            static::createStub(AppConfigReader::class),
            $appRepository,
            new StaticSystemConfigService([]),
            new NullLogger()
        );

        // checkConfiguration should return false instead of throwing the exception
        static::assertFalse($configService->checkConfiguration('SwagExampleTest.config', Context::createDefaultContext()));
    }

    /**
     * @param array<mixed> $config
     *
     * @return array<mixed>
     */
    public function getConfiguration(array $config): array
    {
        $app = (new AppEntity())->assign(['name' => 'SwagExampleTest', '_uniqueIdentifier' => 'test']);

        $appConfigReader = static::createStub(AppConfigReader::class);
        $appConfigReader->method('read')->willReturnMap([[$app, $config]]);

        $appRepository = new StaticEntityRepository([
            new AppCollection([$app]),
            new AppCollection([$app]),
        ]);
        $configService = new ConfigurationService(
            [],
            new ConfigReader(),
            $appConfigReader,
            $appRepository,
            new StaticSystemConfigService([]),
            new NullLogger()
        );

        if ($config !== []) {
            static::assertTrue($configService->checkConfiguration('SwagExampleTest', Context::createDefaultContext()));
        }

        return $configService->getConfiguration('SwagExampleTest', Context::createDefaultContext());
    }

    /**
     * @return array<mixed>
     */
    private function getConfigWithoutValues(): array
    {
        return [
            0 => [
                'title' => [
                    'en-GB' => 'Basic configuration',
                    'zh-CN' => '基础设置',
                ],
                'name' => null,
                'elements' => [
                    0 => [
                        'name' => 'SwagExampleTest.email',
                        'type' => 'text',
                        'config' => [
                            'copyable' => true,
                            'label' => [
                                'en-GB' => 'eMail',
                                'zh-CN' => '电子邮箱',
                            ],
                            'placeholder' => [
                                'en-GB' => 'Enter your eMail address',
                                'zh-CN' => '请输入您的邮箱地址',
                            ],
                        ],
                    ],
                    [
                        'name' => 'SwagExampleTest.withoutAnyConfig',
                        'type' => 'int',
                        'config' => [],
                    ],
                    [
                        'name' => 'SwagExampleTest.mailMethod',
                        'type' => 'single-select',
                        'config' => [
                            'options' => [
                                0 => [
                                    'id' => 'smtp',
                                    'name' => [
                                        'en-GB' => 'SMTP',
                                    ],
                                ],
                                1 => [
                                    'id' => 'pop3',
                                    'name' => [
                                        'en-GB' => 'POP3',
                                    ],
                                ],
                            ],
                            'label' => [
                                'en-GB' => 'Mailing protocol',
                                'zh-CN' => '邮件发送协议',
                            ],
                            'placeholder' => [
                                'en-GB' => 'Choose your preferred transfer method',
                                'zh-CN' => '请选择您偏好的发送协议',
                            ],
                            'flag' => 'FEATURE_NEXT_102',
                        ],
                    ],
                ],
                'flag' => 'FEATURE_NEXT_101',
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    private function getAppConfig(): array
    {
        return [
            [
                'title' => [
                    'en-GB' => 'Basic configuration',
                    'zh-CN' => '基础设置',
                ],
                'name' => null,
                'elements' => [
                    [
                        'type' => 'text',
                        'name' => 'email',
                        'copyable' => true,
                        'label' => [
                            'en-GB' => 'eMail',
                            'zh-CN' => '电子邮箱',
                        ],
                        'placeholder' => [
                            'en-GB' => 'Enter your eMail address',
                            'zh-CN' => '请输入您的邮箱地址',
                        ],
                    ],
                    [
                        'type' => 'int',
                        'name' => 'withoutAnyConfig',
                    ],
                    [
                        'type' => 'single-select',
                        'name' => 'mailMethod',
                        'options' => [
                            [
                                'id' => 'smtp',
                                'name' => [
                                    'en-GB' => 'SMTP',
                                ],
                            ],
                            [
                                'id' => 'pop3',
                                'name' => [
                                    'en-GB' => 'POP3',
                                ],
                            ],
                        ],
                        'label' => [
                            'en-GB' => 'Mailing protocol',
                            'zh-CN' => '邮件发送协议',
                        ],
                        'placeholder' => [
                            'en-GB' => 'Choose your preferred transfer method',
                            'zh-CN' => '请选择您偏好的发送协议',
                        ],
                        'flag' => 'FEATURE_NEXT_102',
                    ],
                ],
                'flag' => 'FEATURE_NEXT_101',
            ],
        ];
    }
}

/**
 * @internal
 */
class SwagExampleTest extends Plugin
{
}
