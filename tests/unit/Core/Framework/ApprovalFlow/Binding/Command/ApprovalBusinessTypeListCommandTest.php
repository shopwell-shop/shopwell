<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Binding\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeSourceDirectory;
use Shopwell\Core\Framework\ApprovalFlow\Binding\Command\ApprovalBusinessTypeListCommand;
use Shopwell\Core\Framework\ApprovalFlow\Binding\YamlApprovalBusinessTypeLoader;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalBusinessTypeListCommand::class)]
class ApprovalBusinessTypeListCommandTest extends TestCase
{
    public function testListsDeclaredBusinessTypesAndTheirSchemaSources(): void
    {
        $loader = new YamlApprovalBusinessTypeLoader(new Filesystem(), [new ApprovalBusinessTypeSourceDirectory('finance', __DIR__ . '/../_fixtures')]);
        $tester = new CommandTester(new ApprovalBusinessTypeListCommand(new ApprovalBusinessTypeRegistry([$loader])));

        static::assertSame(Command::SUCCESS, $tester->execute([]));
        foreach (['finance:expense', '1.0.0', 'urn:finance:expense:1', 'finance:expense.yaml'] as $value) {
            static::assertStringContainsString($value, $tester->getDisplay());
        }
    }

    public function testInvalidCatalogueCannotReportAPartialList(): void
    {
        $loader = new YamlApprovalBusinessTypeLoader(new Filesystem(), [new ApprovalBusinessTypeSourceDirectory('finance', __DIR__ . '/../_fixtures')]);
        $tester = new CommandTester(new ApprovalBusinessTypeListCommand(new ApprovalBusinessTypeRegistry([$loader, $loader])));
        try {
            $tester->execute([]);
            static::fail('Conflicting business declarations cannot report success.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::DUPLICATE_BUSINESS_TYPE, $exception->getErrorCode());
            static::assertSame('', $tester->getDisplay());
        }
    }
}
