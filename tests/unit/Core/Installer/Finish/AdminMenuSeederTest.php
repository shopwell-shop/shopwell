<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Installer\Finish;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Installer\Finish\AdminMenuSeeder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AdminMenuSeeder::class)]
class AdminMenuSeederTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/shopwell-admin-menu-seeder-' . bin2hex(random_bytes(6));
        mkdir($this->projectDir . '/bin', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (['/bin/console', '/ran'] as $file) {
            if (is_file($this->projectDir . $file)) {
                unlink($this->projectDir . $file);
            }
        }

        if (is_dir($this->projectDir . '/bin')) {
            rmdir($this->projectDir . '/bin');
        }

        if (is_dir($this->projectDir)) {
            rmdir($this->projectDir);
        }
    }

    public function testRunsTheSynchronizationCommandOfTheInstalledProject(): void
    {
        $this->writeConsole("<?php file_put_contents(__DIR__ . '/../ran', implode(' ', array_slice(\$argv, 1)));");

        (new AdminMenuSeeder($this->projectDir))->seed();

        static::assertFileExists($this->projectDir . '/ran');
        static::assertSame('admin-menu:sync --no-interaction', file_get_contents($this->projectDir . '/ran'));
    }

    public function testSwallowsAFailingCommand(): void
    {
        // a locked installation cannot be finished twice: the finish page must not turn
        // a failed seeding attempt into an error page
        $this->writeConsole('<?php fwrite(STDERR, "no database"); exit(1);');

        (new AdminMenuSeeder($this->projectDir))->seed();

        $this->expectNotToPerformAssertions();
    }

    private function writeConsole(string $code): void
    {
        file_put_contents($this->projectDir . '/bin/console', $code . PHP_EOL);
    }
}
