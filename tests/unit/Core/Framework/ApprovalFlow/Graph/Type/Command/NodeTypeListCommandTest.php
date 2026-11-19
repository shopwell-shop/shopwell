<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\Type\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Command\NodeTypeListCommand;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeSourceDirectory;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\YamlNodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Registry\NodeTypeRegistry;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NodeTypeListCommand::class)]
class NodeTypeListCommandTest extends TestCase
{
    public function testListsTheActualCoreDeclarationsWithTheirSources(): void
    {
        $directory = \dirname(__DIR__, 8) . '/src/Core/Framework/ApprovalFlow/Resources/node-types';
        $loader = new YamlNodeTypeLoader(new Filesystem(), [new NodeTypeSourceDirectory('core', $directory)]);
        $tester = new CommandTester(new NodeTypeListCommand(new NodeTypeRegistry([$loader])));

        static::assertSame(Command::SUCCESS, $tester->execute([]));
        foreach (['core:start', 'core:end', 'core:approval', '1.0.0', 'core:start.yaml'] as $value) {
            static::assertStringContainsString($value, $tester->getDisplay());
        }
    }

    public function testInvalidAssemblyCannotReportSuccessOrAPartialCatalogue(): void
    {
        $directory = \dirname(__DIR__, 8) . '/src/Core/Framework/ApprovalFlow/Resources/node-types';
        $loader = new YamlNodeTypeLoader(new Filesystem(), [new NodeTypeSourceDirectory('core', $directory)]);
        $tester = new CommandTester(new NodeTypeListCommand(new NodeTypeRegistry([$loader, $loader])));

        $this->expectExceptionObject(ApprovalFlowException::duplicateNodeType('core:approval', '1.0.0', 'core:approval.yaml', 'core:approval.yaml'));
        $tester->execute([]);
    }
}
