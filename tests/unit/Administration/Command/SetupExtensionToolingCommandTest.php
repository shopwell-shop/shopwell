<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Administration\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Administration\Command\SetupExtensionToolingCommand;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SetupExtensionToolingCommand::class)]
class SetupExtensionToolingCommandTest extends TestCase
{
    use ExtensionToolingCommandTestBehaviour;

    public function testSetupCommandRunsTheSetupEntryScript(): void
    {
        $administrationRoot = $this->createAdministrationRoot(withToolingStub: true);

        $tester = new CommandTester(new SetupExtensionToolingCommand($this->kernel(), $administrationRoot));
        $tester->execute(['tooling-args' => ['--check']]);

        $capture = $this->readToolingCapture($administrationRoot);
        static::assertStringEndsWith('scripts/extensionTooling/setup.ts', $capture['argv'][1]);
        static::assertContains('--check', $capture['argv']);

        $this->removeAdministrationRoot($administrationRoot);
    }

    public function testCommandDescriptionMarksTheToolingExperimental(): void
    {
        $command = new SetupExtensionToolingCommand($this->kernel(), null);

        static::assertStringContainsString('[EXPERIMENTAL]', $command->getDescription());
    }
}
