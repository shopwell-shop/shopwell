<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\SystemConfig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\UtilException;
use Shopwell\Core\System\SystemConfig\Util\ConfigReader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ConfigReader::class)]
class ConfigReaderTest extends TestCase
{
    private ConfigReader $configReader;

    protected function setUp(): void
    {
        $this->configReader = new ConfigReader();
    }

    public function testConfigReaderWithValidConfig(): void
    {
        $actualConfig = $this->configReader->read(__DIR__ . '/_fixtures/valid_config.xml');

        static::assertSame($this->getExpectedConfig(), $actualConfig);
    }

    public function testConfigReaderWithInvalidPath(): void
    {
        $this->expectException(UtilException::class);

        $this->configReader->read(__DIR__ . '/config.xml');
    }

    public function testConfigReaderWithInvalidConfig(): void
    {
        $this->expectException(UtilException::class);

        $this->configReader->read(__DIR__ . '/_fixtures/invalid_config.xml');
    }

    /**
     * @return array<mixed>
     */
    private function getExpectedConfig(): array
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
                            'zh-CN' => '请输入你的邮箱地址',
                        ],
                        'defaultValue' => '42',
                    ],
                    [
                        'type' => 'text',
                        'name' => 'stringWithQuoteDefaultValueRemovesQuotes',
                        'defaultValue' => '42',
                    ],
                    [
                        'type' => 'text',
                        'name' => 'nullDefault',
                        'defaultValue' => null,
                    ],
                    [
                        'type' => 'int',
                        'name' => 'int',
                        'defaultValue' => 42,
                    ],
                    [
                        'type' => 'float',
                        'name' => 'float',
                        'defaultValue' => 42.0,
                    ],
                    [
                        'type' => 'float',
                        'name' => 'floatWithStringValueExpectsValueIsCastedToFloat',
                        'defaultValue' => 42.5,
                    ],
                    [
                        'type' => 'bool',
                        'cacheRelevant' => true,
                        'name' => 'bool',
                        'defaultValue' => true,
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
                        'defaultValue' => 'smtp',
                    ],
                    [
                        'type' => 'single-select',
                        'name' => 'period',
                        'options' => [
                            [
                                'id' => '30',
                                'name' => [
                                    'en-GB' => '1 Month',
                                ],
                            ],
                            [
                                'id' => '60',
                                'name' => [
                                    'en-GB' => '2 Months',
                                ],
                            ],
                        ],
                        'defaultValue' => '30',
                    ],
                    [
                        'componentName' => 'sw-select',
                        'cacheRelevant' => true,
                        'name' => 'mailMethodComponent',
                        'disabled' => true,
                        'options' => [
                            [
                                'id' => 'smtp',
                                'name' => [
                                    'en-GB' => 'English smtp',
                                    'zh-CN' => '中文 smtp',
                                ],
                            ],
                            [
                                'id' => 'pop3',
                                'name' => [
                                    'en-GB' => 'English pop3',
                                    'zh-CN' => '中文 pop3',
                                ],
                            ],
                        ],
                        'defaultValue' => 'pop3',
                    ],
                ],
                'subtitle' => [
                    'en-GB' => 'Basic configuration subtitle',
                    'zh-CN' => '基础配置副标题',
                ],
            ],
        ];
    }
}
