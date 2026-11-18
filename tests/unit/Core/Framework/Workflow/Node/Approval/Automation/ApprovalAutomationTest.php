<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Automation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Node\Approval\Automation\ApprovalAutomation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalAutomation::class)]
class ApprovalAutomationTest extends TestCase
{
    public function testExistingNodesRemainManualByDefault(): void
    {
        static::assertSame(['decision' => 'manual', 'approveInitiator' => false, 'deduplication' => 'none'], ApprovalAutomation::fromConfiguration([])->configuration());
    }

    #[DataProvider('invalidConfigurations')]
    public function testInvalidPresentValuesFailClosed(mixed $configuration): void
    {
        $this->expectException(WorkflowExecutionException::class);
        ApprovalAutomation::fromConfiguration($configuration);
    }

    public static function invalidConfigurations(): iterable
    {
        yield [null];
        yield [['decision' => null]];
        yield [['decision' => 'skip']];
        yield [['approveInitiator' => 1]];
        yield [['deduplication' => 'everything']];
        yield [['unknown' => true]];
    }
}
