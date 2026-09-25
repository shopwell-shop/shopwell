<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\AppCollection;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\App\Command\AppPrinter;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Tests\Unit\Core\Framework\App\AppFixture;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppPrinter::class)]
class AppPrinterTest extends TestCase
{
    private BufferedOutput $output;

    private SymfonyStyle $io;

    protected function setUp(): void
    {
        $this->output = new BufferedOutput();
        $this->io = new SymfonyStyle(new ArrayInput([]), $this->output);
    }

    #[TestDox('The installed apps are printed as a table')]
    public function testPrintsInstalledApps(): void
    {
        $app = AppFixture::createAppEntity('TestApp');
        $app->setAuthor('Shopwell');

        $printer = new AppPrinter($this->createAppRepository([$app]));

        $printer->printInstalledApps($this->io, Context::createDefaultContext());

        $display = $this->output->fetch();
        static::assertStringContainsString('Installed apps', $display);
        static::assertStringContainsString('TestApp', $display);
        static::assertStringContainsString('1.0.0', $display);
        static::assertStringContainsString('Shopwell', $display);
    }

    #[TestDox('Nothing is printed when no apps are installed')]
    public function testPrintsNothingWithoutInstalledApps(): void
    {
        $printer = new AppPrinter($this->createAppRepository([]));

        $printer->printInstalledApps($this->io, Context::createDefaultContext());

        static::assertSame('', $this->output->fetch());
    }

    #[TestDox('Incomplete installations are printed with the failure reason')]
    public function testPrintsIncompleteInstallations(): void
    {
        $printer = new AppPrinter($this->createAppRepository([]));

        $printer->printIncompleteInstallations($this->io, [
            ['manifest' => $this->createManifest(), 'exception' => new \RuntimeException('registration failed')],
        ]);

        $display = $this->output->fetch();
        static::assertStringContainsString('Incomplete installations', $display);
        static::assertStringContainsString('test', $display);
        static::assertStringContainsString('registration failed', $display);
    }

    #[TestDox('Nothing is printed when all installations completed')]
    public function testPrintsNothingWithoutIncompleteInstallations(): void
    {
        $printer = new AppPrinter($this->createAppRepository([]));

        $printer->printIncompleteInstallations($this->io, []);

        static::assertSame('', $this->output->fetch());
    }

    #[TestDox('The requested permissions are printed per privilege')]
    public function testPrintsPermissions(): void
    {
        $printer = new AppPrinter($this->createAppRepository([]));

        $printer->printPermissions($this->createManifest(), $this->io, true);

        $display = $this->output->fetch();
        static::assertStringContainsString('[CAUTION] App "test" should be installed', $display);
        static::assertStringContainsString('product', $display);
        static::assertStringContainsString('write, delete', $display);
    }

    #[TestDox('Host access must be consented, declining aborts the command')]
    public function testCheckHostsThrowsWhenConsentIsDeclined(): void
    {
        $printer = new AppPrinter($this->createAppRepository([]));

        // non-interactive confirm falls back to the default answer, which is "no"
        $this->expectExceptionObject(AppException::userAborted());

        $printer->checkHosts($this->createManifest(), $this->io);
    }

    #[TestDox('Host access consent lets the command continue')]
    public function testCheckHostsPassesWithConsent(): void
    {
        $input = new ArrayInput([]);
        $input->setInteractive(true);
        $stream = fopen('php://memory', 'r+');
        static::assertIsResource($stream);
        fwrite($stream, "yes\n");
        rewind($stream);
        $input->setStream($stream);

        $io = new SymfonyStyle($input, $this->output);
        $printer = new AppPrinter($this->createAppRepository([]));

        $printer->checkHosts($this->createManifest(), $io);

        $display = $this->output->fetch();
        static::assertStringContainsString('[CAUTION] App "test" should be installed', $display);
        static::assertStringContainsString('swag-test.com', $display);
    }

    private function createManifest(): Manifest
    {
        return Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');
    }

    /**
     * @param list<AppEntity> $apps
     *
     * @return StaticEntityRepository<AppCollection>
     */
    private function createAppRepository(array $apps): StaticEntityRepository
    {
        $repository = new StaticEntityRepository([new AppCollection($apps)]);

        return $repository;
    }
}
