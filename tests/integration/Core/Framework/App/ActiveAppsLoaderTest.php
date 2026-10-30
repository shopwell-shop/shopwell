<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\App;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\ActiveAppsLoader;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Test\AppSystemTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class ActiveAppsLoaderTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    private ActiveAppsLoader $activeAppsLoader;

    private AppFixture $appFixture;

    protected function setUp(): void
    {
        $this->activeAppsLoader = static::getContainer()->get(ActiveAppsLoader::class);

        $appFixture = static::getContainer()->get(AppFixture::class);
        static::assertInstanceOf(AppFixture::class, $appFixture);
        $this->appFixture = $appFixture;
    }

    public function testGetActiveAppsWithActiveApp(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/Manifest/_fixtures/test');

        $activeApps = $this->activeAppsLoader->getActiveApps();
        static::assertCount(1, $activeApps);
        static::assertSame('test', $activeApps[0]['name']);
    }

    public function testGetActiveAppsWithInactiveApp(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/Manifest/_fixtures/test', false);

        $activeApps = $this->activeAppsLoader->getActiveApps();
        static::assertCount(0, $activeApps);
    }

    public function testAnAppWithASecretIsRegistered(): void
    {
        $this->appFixture->createAppFromData(['appSecret' => 's3cr3t']);

        static::assertTrue($this->activeAppsLoader->hasRegisteredApps());
    }

    public function testAnAppWithoutASecretIsNotRegistered(): void
    {
        $this->appFixture->createAppFromData();

        static::assertFalse($this->activeAppsLoader->hasRegisteredApps());
    }
}
