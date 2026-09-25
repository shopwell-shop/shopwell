<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Migration\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\Command\CreateMigrationCommand;
use Shopwell\Core\Framework\Migration\MigrationException;
use Shopwell\Core\Framework\Plugin\KernelPluginCollection;
use Shopwell\Tests\Integration\Storefront\Theme\fixtures\SimplePlugin\SimplePlugin as SimplePluginIntegration;
use Shopwell\Tests\Unit\Storefront\Theme\fixtures\SimplePlugin\SimplePlugin;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CreateMigrationCommand::class)]
class CreateMigrationCommandTest extends TestCase
{
    public function testExecuteThrowsExceptionIfNameContainsForbiddenCharacters(): void
    {
        $command = new CreateMigrationCommand(
            new KernelPluginCollection(),
            'coreDir',
            'shopwellVersion'
        );
        $commandTester = new CommandTester($command);

        $input = ['--name' => '%%%%'];

        $this->expectExceptionObject(MigrationException::invalidArgument('Migration name contains forbidden characters!'));

        $commandTester->execute($input);
    }

    public function testExecuteThrowsExceptionWhenDirectoryIsSpecifiedButNoNamespace(): void
    {
        $command = new CreateMigrationCommand(
            new KernelPluginCollection(),
            'coreDir',
            'shopwellVersion'
        );
        $commandTester = new CommandTester($command);

        $input = ['directory' => 'test-dir'];

        $this->expectExceptionObject(MigrationException::invalidArgument('Please specify both dir and namespace or none.'));

        $commandTester->execute($input);
    }

    public function testExecuteThrowsExceptionWhenPluginIsNotFound(): void
    {
        $command = new CreateMigrationCommand(
            new KernelPluginCollection(),
            'coreDir',
            'shopwellVersion'
        );
        $commandTester = new CommandTester($command);

        $input = ['--plugin' => 'test-plugin'];

        $this->expectExceptionObject(MigrationException::pluginNotFound('test-plugin'));

        $commandTester->execute($input);
    }

    public function testExecuteThrowsExceptionWhenMoreThanOnePluginIsFound(): void
    {
        $kernelPluginCollection = new KernelPluginCollection();
        $plugin1 = new SimplePlugin(true, '');
        $plugin2 = new SimplePluginIntegration(true, '');
        $kernelPluginCollection->addList([$plugin1, $plugin2]);

        $command = new CreateMigrationCommand(
            $kernelPluginCollection,
            'coreDir',
            'shopwellVersion'
        );
        $commandTester = new CommandTester($command);

        $input = ['--plugin' => 'SimplePlugin'];

        $this->expectExceptionObject(MigrationException::moreThanOnePluginFound(
            'SimplePlugin',
            array_keys($kernelPluginCollection->all())
        ));

        $commandTester->execute($input);
    }
}
