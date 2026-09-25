<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Update\Steps;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Services\ExtensionLifecycleService;
use Shopwell\Core\Framework\Store\Struct\ExtensionStruct;
use Shopwell\Core\Framework\Update\Services\ExtensionCompatibility;
use Shopwell\Core\Framework\Update\Steps\DeactivateExtensionsStep;
use Shopwell\Core\Framework\Update\Struct\Version;
use Shopwell\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeactivateExtensionsStep::class)]
class DeactivateExtensionsStepTest extends TestCase
{
    public function testRunWithEmptyPlugins(): void
    {
        $version = new Version();
        $version->assign([
            'version' => '6.6.0.0',
        ]);

        $deactivateExtensionsStep = new DeactivateExtensionsStep(
            $version,
            ExtensionCompatibility::PLUGIN_DEACTIVATION_FILTER_ALL,
            static::createStub(ExtensionCompatibility::class),
            static::createStub(ExtensionLifecycleService::class),
            static::createStub(SystemConfigService::class),
            Context::createDefaultContext()
        );

        $result = $deactivateExtensionsStep->run(0);

        static::assertSame($result->getTotal(), $result->getOffset());
    }

    public function testRunShouldDeactivateOneAndFinishDirectly(): void
    {
        $version = new Version();
        $version->assign([
            'version' => '6.6.0.0',
        ]);

        $extension = new ExtensionStruct();
        $extension->setId(1);
        $extension->setName('TestApp');
        $extension->setType(ExtensionStruct::EXTENSION_TYPE_APP);

        $pluginCompatibility = static::createStub(ExtensionCompatibility::class);
        $pluginCompatibility
            ->method('getExtensionsToDeactivate')
            ->willReturn([$extension]);

        $systemConfigService = $this->createMock(SystemConfigService::class);

        $systemConfigService
            ->expects($this->once())
            ->method('set')
            ->with(DeactivateExtensionsStep::UPDATE_DEACTIVATED_PLUGINS, [1]);

        $extensionLifecycleService = $this->createMock(ExtensionLifecycleService::class);

        $extensionLifecycleService
            ->expects($this->once())
            ->method('deactivate')
            ->with('app', 'TestApp');

        $deactivateExtensionsStep = new DeactivateExtensionsStep(
            $version,
            ExtensionCompatibility::PLUGIN_DEACTIVATION_FILTER_ALL,
            $pluginCompatibility,
            $extensionLifecycleService,
            $systemConfigService,
            Context::createDefaultContext()
        );

        $result = $deactivateExtensionsStep->run(0);

        static::assertSame($result->getTotal(), $result->getOffset());
    }

    public function testRunShouldDeactivateMultiple(): void
    {
        $version = new Version();
        $version->assign([
            'version' => '6.6.0.0',
        ]);

        $extension = new ExtensionStruct();
        $extension->setId(1);
        $extension->setName('TestApp');
        $extension->setType(ExtensionStruct::EXTENSION_TYPE_APP);

        $pluginCompatibility = static::createStub(ExtensionCompatibility::class);
        $pluginCompatibility
            ->method('getExtensionsToDeactivate')
            ->willReturn([$extension, $extension]);

        $systemConfigService = $this->createMock(SystemConfigService::class);

        $systemConfigService
            ->expects($this->once())
            ->method('set')
            ->with(DeactivateExtensionsStep::UPDATE_DEACTIVATED_PLUGINS, [1]);

        $extensionLifecycleService = $this->createMock(ExtensionLifecycleService::class);

        $extensionLifecycleService
            ->expects($this->once())
            ->method('deactivate')
            ->with('app', 'TestApp');

        $deactivateExtensionsStep = new DeactivateExtensionsStep(
            $version,
            ExtensionCompatibility::PLUGIN_DEACTIVATION_FILTER_ALL,
            $pluginCompatibility,
            $extensionLifecycleService,
            $systemConfigService,
            Context::createDefaultContext()
        );

        $result = $deactivateExtensionsStep->run(0);
        static::assertSame(1, $result->getOffset());
    }
}
