<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Theme\Subscriber;

use Doctrine\DBAL\Exception as DBALException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SystemConfig\DTO\SystemConfigCard;
use Shopwell\Core\System\SystemConfig\DTO\SystemConfigElement;
use Shopwell\Core\System\SystemConfig\DTO\SystemConfigTab;
use Shopwell\Core\System\SystemConfig\Service\SystemConfigDefinitionService;
use Shopwell\Core\Test\Stub\Doctrine\TestExceptionFactory;
use Shopwell\Core\Test\TestDefaults;
use Shopwell\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopwell\Storefront\Theme\StorefrontPluginRegistry;
use Shopwell\Storefront\Theme\Subscriber\ThemeCompilerEnrichScssVarSubscriber;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeCompilerEnrichScssVarSubscriber::class)]
class ThemeCompilerEnrichScssVarSubscriberTest extends TestCase
{
    private SystemConfigDefinitionService&Stub $systemConfigDefinitionService;

    private StorefrontPluginRegistry&Stub $storefrontPluginRegistry;

    protected function setUp(): void
    {
        $this->systemConfigDefinitionService = static::createStub(SystemConfigDefinitionService::class);
        $this->storefrontPluginRegistry = static::createStub(StorefrontPluginRegistry::class);
    }

    public function testEnrichExtensionVarsReturnsNothingWithNoStorefrontPlugin(): void
    {
        $systemConfigDefinitionService = $this->createMock(SystemConfigDefinitionService::class);
        $systemConfigDefinitionService->expects($this->never())->method('getResolvedConfiguration');

        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($systemConfigDefinitionService, $this->storefrontPluginRegistry);

        $subscriber->enrichExtensionVars(
            new ThemeCompilerEnrichScssVariablesEvent(
                [],
                TestDefaults::SALES_CHANNEL,
                Context::createDefaultContext()
            )
        );
    }

    public function testOnlyDBExceptionIsSilenced(): void
    {
        $exception = new \InvalidArgumentException();
        $this->systemConfigDefinitionService->method('getResolvedConfiguration')->willThrowException($exception);
        $this->storefrontPluginRegistry->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection([
                new StorefrontPluginConfiguration('test'),
            ])
        );

        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($this->systemConfigDefinitionService, $this->storefrontPluginRegistry);
        $this->expectExceptionObject($exception);

        $subscriber->enrichExtensionVars(
            new ThemeCompilerEnrichScssVariablesEvent(
                [],
                TestDefaults::SALES_CHANNEL,
                Context::createDefaultContext()
            )
        );
    }

    public function testDBException(): void
    {
        $this->systemConfigDefinitionService->method('getResolvedConfiguration')->willThrowException(TestExceptionFactory::createException('test'));
        $this->storefrontPluginRegistry->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection([
                new StorefrontPluginConfiguration('test'),
            ])
        );
        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($this->systemConfigDefinitionService, $this->storefrontPluginRegistry);

        $exception = null;
        try {
            $subscriber->enrichExtensionVars(
                new ThemeCompilerEnrichScssVariablesEvent(
                    [],
                    TestDefaults::SALES_CHANNEL,
                    Context::createDefaultContext()
                )
            );
        } catch (DBALException $exception) {
        }

        static::assertNull($exception);
    }

    /**
     * EnrichScssVarSubscriber doesn't throw an exception if we have corrupted element values.
     * This can happen on updates from older version when the values in the administration where not checked before save
     */
    public function testOutputsPluginCssCorrupt(): void
    {
        $this->systemConfigDefinitionService->method('getResolvedConfiguration')->willReturn([
            new SystemConfigTab(
                [
                    new SystemConfigCard(
                        [],
                        []
                    ),
                ]
            ),
        ]);

        $this->storefrontPluginRegistry->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection([
                new StorefrontPluginConfiguration('test'),
            ])
        );
        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($this->systemConfigDefinitionService, $this->storefrontPluginRegistry);

        $event = new ThemeCompilerEnrichScssVariablesEvent(
            ['bla' => 'any'],
            TestDefaults::SALES_CHANNEL,
            Context::createDefaultContext()
        );

        $backupEvent = clone $event;

        $subscriber->enrichExtensionVars(
            $event
        );

        static::assertEquals($backupEvent, $event);
    }

    public function testGetSubscribedEventsReturnsOnlyOneTypeOfEvent(): void
    {
        static::assertSame(
            [
                ThemeCompilerEnrichScssVariablesEvent::class => 'enrichExtensionVars',
            ],
            ThemeCompilerEnrichScssVarSubscriber::getSubscribedEvents()
        );
    }

    public function testConfigurationNullValuesDefaultToEmptyString(): void
    {
        $this->systemConfigDefinitionService->method('getResolvedConfiguration')->willReturn([
            new SystemConfigTab(
                [
                    new SystemConfigCard(
                        [
                            new SystemConfigElement(
                                'test',
                                ['css' => 'test', 'defaultValue' => null],
                                'text'
                            ),
                        ],
                        []
                    ),
                ]
            ),
        ]);

        $this->storefrontPluginRegistry->method('getConfigurations')->willReturn(
            new StorefrontPluginConfigurationCollection([
                new StorefrontPluginConfiguration('test'),
            ])
        );
        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($this->systemConfigDefinitionService, $this->storefrontPluginRegistry);

        $event = new ThemeCompilerEnrichScssVariablesEvent(
            [],
            TestDefaults::SALES_CHANNEL,
            Context::createDefaultContext()
        );

        $subscriber->enrichExtensionVars(
            $event
        );

        static::assertSame(['test' => ''], $event->getVariables());
    }
}
