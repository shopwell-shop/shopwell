<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Diagnostics\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\Command\GraphDiagnoseCommand;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(GraphDiagnoseCommand::class)]
class GraphDiagnoseCommandTest extends TestCase
{
    public function testReadsAFileAndReportsTheGraphWithoutWritingIt(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->once())->method('readFile')->with('transfer.json')->willReturn(GraphFixture::json());
        $filesystem->expects($this->never())->method('dumpFile');
        $tester = new CommandTester(new GraphDiagnoseCommand($filesystem, GraphFixture::diagnostics()));

        static::assertSame(Command::SUCCESS, $tester->execute(['file' => 'transfer.json']));
        static::assertStringContainsString('Graph checks passed', $tester->getDisplay());
        static::assertStringContainsString('does not admit publication or execution', $tester->getDisplay());
    }

    public function testMalformedGraphCannotExitSuccessfully(): void
    {
        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('readFile')->willReturn('{broken');
        $tester = new CommandTester(new GraphDiagnoseCommand($filesystem, GraphFixture::diagnostics()));

        static::assertSame(Command::FAILURE, $tester->execute(['file' => 'invalid.json']));
        static::assertStringContainsString('invalid_json', $tester->getDisplay());
        static::assertStringContainsString('structure', $tester->getDisplay());
    }

    public function testIncompleteDraftReportsItsOwnCategoryAndFailsTheCheck(): void
    {
        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('readFile')->willReturn(str_replace('"outcome": "approved"', '', GraphFixture::json()));
        $tester = new CommandTester(new GraphDiagnoseCommand($filesystem, GraphFixture::diagnostics()));

        static::assertSame(Command::FAILURE, $tester->execute(['file' => 'unfinished.json']));
        static::assertStringContainsString('draft', $tester->getDisplay());
        static::assertStringContainsString('/nodes/2/config', $tester->getDisplay());
    }
}
