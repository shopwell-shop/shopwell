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
use Shopwell\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopwell\Core\Framework\Util\UtilException;
use Shopwell\Core\System\SystemConfig\Service\AppConfigReader;
use Shopwell\Core\System\SystemConfig\Service\ConfigurationService;
use Shopwell\Core\System\SystemConfig\Service\SystemConfigDefinitionService;
use Shopwell\Core\System\SystemConfig\SystemConfigException;
use Shopwell\Core\System\SystemConfig\Util\ConfigReader;
use Shopwell\Core\Test\Annotation\DisabledFeatures;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - will be removed
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
#[Package('framework')]
#[CoversClass(ConfigurationService::class)]
class ConfigurationServiceTest extends TestCase
{
    use EnvTestBehaviour;

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testInvalidDomain(): void
    {
        $this->expectExceptionObject(SystemConfigException::invalidDomain());

        $appRepository = new StaticEntityRepository([]);
        $systemConfigService = new StaticSystemConfigService([]);
        $configDefinitionService = new SystemConfigDefinitionService(
            [],
            new ConfigReader(),
            static::createStub(AppConfigReader::class),
            $appRepository,
            $systemConfigService,
            new NullLogger()
        );
        $configService = new ConfigurationService(
            $systemConfigService,
            $configDefinitionService
        );

        $configService->getConfiguration('invalid!', Context::createDefaultContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCheckConfigurationWithInvalidDomain(): void
    {
        $appRepository = new StaticEntityRepository([]);
        $systemConfigService = new StaticSystemConfigService([]);
        $configDefinitionService = new SystemConfigDefinitionService(
            [],
            new ConfigReader(),
            static::createStub(AppConfigReader::class),
            $appRepository,
            $systemConfigService,
            new NullLogger()
        );
        $configService = new ConfigurationService(
            $systemConfigService,
            $configDefinitionService
        );

        static::assertFalse($configService->checkConfiguration('invalid!', Context::createDefaultContext()));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testMissingConfig(): void
    {
        $appRepository = new StaticEntityRepository([new AppCollection([])]);
        $systemConfigService = new StaticSystemConfigService([]);
        $configDefinitionService = new SystemConfigDefinitionService(
            [],
            new ConfigReader(),
            static::createStub(AppConfigReader::class),
            $appRepository,
            $systemConfigService,
            new NullLogger()
        );
        $configService = new ConfigurationService(
            $systemConfigService,
            $configDefinitionService
        );

        $this->expectExceptionObject(SystemConfigException::configurationNotFound('missing'));
        $configService->getConfiguration('missing', Context::createDefaultContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConfigurationFeatureFlag(): void
    {
        $this->setEnvVars([
            'FEATURE_NEXT_101' => '1',
            'FEATURE_NEXT_102' => '1',
        ]);

        static::assertTrue(Feature::isActive('FEATURE_NEXT_101'));
        static::assertTrue(Feature::isActive('FEATURE_NEXT_102'));

        $actualConfig = $this->getConfiguration($this->getAppConfig());

        $expectedConfigWithoutValues = $this->getConfigWithoutValues();

        static::assertEquals($expectedConfigWithoutValues, $actualConfig);
        static::assertEquals($expectedConfigWithoutValues[0]['elements'][0], $actualConfig[0]['elements'][0]);
        static::assertEquals($expectedConfigWithoutValues[0]['elements'][2], $actualConfig[0]['elements'][2]);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConfigurationIsSequentiallyIndexedWhenFeatureFlagNotEnabled(): void
    {
        $this->setEnvVars([
            'FEATURE_NEXT_101' => '0',
            'FEATURE_NEXT_102' => '0',
        ]);

        static::assertFalse(Feature::isActive('FEATURE_NEXT_101'));
        static::assertFalse(Feature::isActive('FEATURE_NEXT_102'));

        $config = $this->getAppConfig();

        unset($config[0]['cards'][0]['flag']); // make card not rely on feature flag (won't be removed)
        $config[0]['cards'][0]['elements'][0]['flag'] = 'FEATURE_NEXT_102'; // make first element rely on feature flag (will be removed)

        // create new card at position 0 and make it rely on feature flag (will be removed)
        array_unshift($config[0]['cards'], [
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

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConfigurationNoFeatureFlag(): void
    {
        $actualConfig = $this->getConfiguration($this->getAppConfig());

        static::assertEmpty($actualConfig);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testEmptyConfigThrowsError(): void
    {
        $this->expectExceptionObject(SystemConfigException::configurationNotFound('SwagExampleTestDeprecated'));

        $this->getConfiguration([]);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testElementWithFlag(): void
    {
        $config = [
            [
                'title' => null,
                'name' => null,
                'cards' => [
                    [
                        'title' => [
                            'en-GB' => 'Basic configuration',
                            'zh-CN' => '基础设置',
                        ],
                        'name' => null,
                        'elements' => [
                            [
                                'name' => 'SwagExampleTestDeprecated.email',
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
                                        'zh-CN' => '请输入你的邮箱地址',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $actualConfig = $this->getConfiguration($config);

        static::assertSame([], $actualConfig[0]['elements']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCacheRelevantMetadataIsExposedInElementConfig(): void
    {
        $config = [
            [
                'title' => null,
                'name' => null,
                'cards' => [
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
                ],
            ],
        ];

        $actualConfig = $this->getConfiguration($config);

        static::assertTrue($actualConfig[0]['elements'][0]['config']['cacheRelevant']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConfigFromPlugin(): void
    {
        $config = [
            [
                'title' => null,
                'name' => null,
                'cards' => [
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
                                        'zh-CN' => '请输入你的邮箱地址',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $configReader = static::createStub(ConfigReader::class);
        $configReader->method('getConfigFromBundle')->willReturn($config);

        $appRepository = new StaticEntityRepository([new AppCollection()]);
        $systemConfigService = new StaticSystemConfigService([]);
        $configDefinitionService = new SystemConfigDefinitionService(
            [
                new SwagExampleTestDeprecated(true, ''),
            ],
            $configReader,
            static::createStub(AppConfigReader::class),
            $appRepository,
            $systemConfigService,
            new NullLogger()
        );
        $service = new ConfigurationService(
            $systemConfigService,
            $configDefinitionService
        );

        $actualConfig = $service->getConfiguration('SwagExampleTestDeprecated', Context::createDefaultContext());

        static::assertCount(1, $actualConfig);
        static::assertCount(1, $actualConfig[0]['elements']);
        static::assertSame('SwagExampleTestDeprecated.email', $actualConfig[0]['elements'][0]['name']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testEnrichConfig(): void
    {
        $config = [
            [
                'title' => null,
                'name' => null,
                'cards' => [
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
                                        'zh-CN' => '请输入你的邮箱地址',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $configReader = static::createStub(ConfigReader::class);
        $configReader->method('getConfigFromBundle')->willReturn($config);

        $repository = new StaticEntityRepository([new AppCollection()]);

        $systemConfigService = new StaticSystemConfigService(['SwagExampleTestDeprecated.email' => 'foo']);
        $configDefinitionService = new SystemConfigDefinitionService(
            [
                new SwagExampleTestDeprecated(true, ''),
            ],
            $configReader,
            static::createStub(AppConfigReader::class),
            $repository,
            $systemConfigService,
            new NullLogger()
        );
        $service = new ConfigurationService(
            $systemConfigService,
            $configDefinitionService
        );

        $actualConfig = $service->getResolvedConfiguration('SwagExampleTestDeprecated', Context::createDefaultContext());

        static::assertCount(1, $actualConfig);
        static::assertCount(1, $actualConfig[0]['elements']);
        static::assertSame('SwagExampleTestDeprecated.email', $actualConfig[0]['elements'][0]['name']);
        static::assertSame('foo', $actualConfig[0]['elements'][0]['value']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCheckConfigurationReturnsFalseOnXmlParsingException(): void
    {
        $configReader = static::createStub(ConfigReader::class);
        $configReader->method('getConfigFromBundle')->willThrowException(
            UtilException::xmlParsingException('/path/to/config.xml', 'Invalid XML: element name contains underscores')
        );

        $appRepository = new StaticEntityRepository([new AppCollection([])]);
        $systemConfigService = new StaticSystemConfigService([]);
        $configDefinitionService = new SystemConfigDefinitionService(
            [
                new SwagExampleTestDeprecated(true, ''),
            ],
            $configReader,
            static::createStub(AppConfigReader::class),
            $appRepository,
            $systemConfigService,
            new NullLogger()
        );
        $configService = new ConfigurationService(
            $systemConfigService,
            $configDefinitionService
        );

        // checkConfiguration should return false instead of throwing the exception
        static::assertFalse($configService->checkConfiguration('SwagExampleTestDeprecated.config', Context::createDefaultContext()));
    }

    /**
     * @param array<mixed> $config
     *
     * @return array<mixed>
     */
    public function getConfiguration(array $config): array
    {
        $app = (new AppEntity())->assign(['name' => 'SwagExampleTestDeprecated', '_uniqueIdentifier' => 'test']);

        $appConfigReader = static::createStub(AppConfigReader::class);
        $appConfigReader->method('read')->willReturnMap([[$app, $config]]);

        $appRepository = new StaticEntityRepository([
            new AppCollection([$app]),
            new AppCollection([$app]),
        ]);
        $systemConfigService = new StaticSystemConfigService([]);
        $configDefinitionService = new SystemConfigDefinitionService(
            [],
            new ConfigReader(),
            $appConfigReader,
            $appRepository,
            $systemConfigService,
            new NullLogger()
        );
        $configService = new ConfigurationService(
            $systemConfigService,
            $configDefinitionService
        );

        if ($config !== []) {
            static::assertTrue($configService->checkConfiguration('SwagExampleTestDeprecated', Context::createDefaultContext()));
        }

        return $configService->getConfiguration('SwagExampleTestDeprecated', Context::createDefaultContext());
    }

    /**
     * @return array<mixed>
     */
    private function getConfigWithoutValues(): array
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
                        'name' => 'SwagExampleTestDeprecated.email',
                        'type' => 'text',
                        'config' => [
                            'copyable' => true,
                            'label' => [
                                'en-GB' => 'eMail',
                                'zh-CN' => '电子邮箱',
                            ],
                            'placeholder' => [
                                'en-GB' => 'Enter your eMail address',
                                'zh-CN' => '请输入你的邮箱地址',
                            ],
                        ],
                        'value' => null,
                    ],
                    [
                        'name' => 'SwagExampleTestDeprecated.withoutAnyConfig',
                        'type' => 'int',
                        'config' => [],
                        'value' => null,
                    ],
                    [
                        'name' => 'SwagExampleTestDeprecated.mailMethod',
                        'type' => 'single-select',
                        'config' => [
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
                                'zh-CN' => '请选择你偏好的发送协议',
                            ],
                            'flag' => 'FEATURE_NEXT_102',
                        ],
                        'value' => null,
                    ],
                ],
                'subtitle' => null,
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
                'title' => null,
                'name' => null,
                'cards' => [
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
                                    'zh-CN' => '请输入你的邮箱地址',
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
                                    'zh-CN' => '请选择你偏好的发送协议',
                                ],
                                'flag' => 'FEATURE_NEXT_102',
                            ],
                        ],
                        'flag' => 'FEATURE_NEXT_101',
                    ],
                ],
            ],
        ];
    }
}

/**
 * @internal
 */
class SwagExampleTestDeprecated extends Plugin
{
}
