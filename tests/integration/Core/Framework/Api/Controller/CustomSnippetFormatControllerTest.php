<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin;
use Shopwell\Core\Framework\Plugin\KernelPluginCollection;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class CustomSnippetFormatControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testGetSnippetsWithoutPlugins(): void
    {
        $url = '/api/_action/custom-snippet';
        $client = $this->getBrowser();
        $client->jsonRequest('GET', $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        $content = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('data', $content);

        static::assertSame([
            'address/city',
            'address/company',
            'address/country',
            'address/country_state',
            'address/department',
            'address/name',
            'address/phone_number',
            'address/salutation',
            'address/street',
            'address/title',
            'address/zipcode',
            'symbol/comma',
            'symbol/dash',
            'symbol/tilde',
        ], $content['data']);
    }

    public function testGetSnippetsWithPlugins(): void
    {
        $plugin = new BundleWithCustomSnippet(true, __DIR__ . '/Fixtures/BundleWithCustomSnippet');
        $pluginCollection = static::getContainer()->get(KernelPluginCollection::class);
        $pluginCollection->add($plugin);

        $url = '/api/_action/custom-snippet';
        $client = $this->getBrowser();
        $client->jsonRequest('GET', $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        $content = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('data', $content);

        static::assertSame([
            'address/city',
            'address/company',
            'address/country',
            'address/country_state',
            'address/department',
            'address/name',
            'address/phone_number',
            'address/salutation',
            'address/street',
            'address/title',
            'address/zipcode',
            'symbol/comma',
            'symbol/dash',
            'symbol/tilde',
            'custom-snippet/custom-snippet',
        ], $content['data']);

        $originalCollection = $pluginCollection->filter(static fn (Plugin $plugin) => $plugin->getName() !== 'BundleWithCustomSnippet');

        $pluginsProp = new \ReflectionProperty($pluginCollection, 'plugins');
        $pluginsProp->setValue($pluginCollection, $originalCollection->all());
    }

    /**
     * @param array{format: array<int, array<int, string>>, data: array<string, mixed>} $payload
     */
    #[DataProvider('renderProvider')]
    public function testRender(array $payload, string $expectedHtml): void
    {
        $url = '/api/_action/custom-snippet/render';
        $client = $this->getBrowser();
        $client->jsonRequest('POST', $url, $payload);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        $content = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('rendered', $content);
        static::assertSame($expectedHtml, $content['rendered']);
    }

    /**
     * @return iterable<string, array<string, string|array<string, array<mixed>>>>
     */
    public static function renderProvider(): iterable
    {
        yield 'without data and format' => [
            'payload' => [
                'format' => [],
                'data' => [],
            ],
            'expectedHtml' => '',
        ];

        yield 'without data' => [
            'payload' => [
                'format' => [],
                'data' => [
                    'address' => [
                        'name' => 'Vin Le',
                    ],
                ],
            ],
            'expectedHtml' => '',
        ];

        yield 'without format' => [
            'payload' => [
                'format' => [
                    [
                        'address/name',
                    ],
                ],
                'data' => [],
            ],
            'expectedHtml' => '',
        ];

        yield 'with data and format' => [
            'payload' => [
                'format' => [
                    [
                        'address/name',
                    ],
                ],
                'data' => [
                    'address' => [
                        'name' => 'Vin Le',
                    ],
                ],
            ],
            'expectedHtml' => 'Vin Le',
        ];

        yield 'render multiple lines' => [
            'payload' => [
                'format' => [
                    [
                        'address/name',
                    ],
                    [
                        'address/street',
                        'address/country',
                    ],
                ],
                'data' => [
                    'address' => [
                        'name' => 'Vin Le',
                        'street' => '123 Strt',
                        'country' => [
                            'translated' => [
                                'name' => 'VN',
                            ],
                        ],
                    ],
                ],
            ],
            'expectedHtml' => 'Vin Le<br/>123 Strt VN',
        ];

        yield 'render multiple lines with symbol' => [
            'payload' => [
                'format' => [
                    [
                        'address/name',
                        'symbol/comma',
                    ],
                    [
                        'address/street',
                        'address/country',
                    ],
                ],
                'data' => [
                    'address' => [
                        'name' => 'Vin Le',
                        'street' => '123 Strt',
                        'country' => [
                            'translated' => [
                                'name' => 'VN',
                            ],
                        ],
                    ],
                ],
            ],
            'expectedHtml' => 'Vin Le,<br/>123 Strt VN',
        ];

        yield 'render ignore empty snippet' => [
            'payload' => [
                'format' => [
                    [
                        'address/company',
                        'symbol/dash',
                        'address/department',
                        'symbol/dash',
                    ],
                    [
                        'symbol/dash',
                        'address/name',
                    ],
                ],
                'data' => [
                    'address' => [
                        'name' => 'Vin Le',
                        'company' => 'Shopwell',
                        'department' => '',
                    ],
                ],
            ],
            'expectedHtml' => 'Shopwell<br/>Vin Le',
        ];

        yield 'render ignore empty line' => [
            'payload' => [
                'format' => [
                    [
                        'address/name',
                    ],
                    [
                        'address/street',
                        'address/country',
                    ],
                    [
                        'address/name',
                    ],
                ],
                'data' => [
                    'address' => [
                        'name' => 'Vin Le',
                    ],
                ],
            ],
            'expectedHtml' => 'Vin Le<br/>Vin Le',
        ];

        yield 'render line with only concat symbol' => [
            'payload' => [
                'format' => [
                    [
                        'address/name',
                        'symbol/dash',
                    ],
                ],
                'data' => [
                    'address' => [],
                ],
            ],
            'expectedHtml' => '',
        ];

        yield 'render lines with symbol comma' => [
            'payload' => [
                'format' => [
                    [
                        'address/zipcode',
                        'symbol/comma',
                        'address/city',
                    ],
                ],
                'data' => [
                    'address' => [
                        'zipcode' => '550000',
                        'city' => 'Da Nang',
                    ],
                ],
            ],
            'expectedHtml' => '550000,  Da Nang',
        ];

        yield 'render lines with empty snippet' => [
            'payload' => [
                'format' => [
                    [
                        'address/name',
                        'address/country_state',
                    ],
                ],
                'data' => [
                    'address' => [
                        'name' => 'Vin Le',
                        'countryState' => null,
                    ],
                ],
            ],
            'expectedHtml' => 'Vin Le',
        ];
    }
}

/**
 * @internal
 */
class BundleWithCustomSnippet extends Plugin
{
    public function getPath(): string
    {
        $reflected = new \ReflectionObject($this);

        return \dirname($reflected->getFileName() ?: '') . '/Fixtures/BundleWithCustomSnippet';
    }
}
