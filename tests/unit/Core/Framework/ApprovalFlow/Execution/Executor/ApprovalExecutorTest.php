<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Execution\Executor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\ApprovalExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\ApprovalNodeConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\NodeConfiguration;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalExecutor::class)]
class ApprovalExecutorTest extends TestCase
{
    public function testApprovalRequestsTaskPlanningWithTheCompiledRules(): void
    {
        $executor = new ApprovalExecutor();
        $configuration = new ApprovalNodeConfiguration(
            ['finance-user'],
            ApprovalNodeConfiguration::ALL,
            fields: [],
            emptyAssignees: ApprovalNodeConfiguration::EMPTY_INCIDENT,
        );
        static::assertSame('core:approval', $executor->getType());
        static::assertSame('1.0.0', $executor->getVersion());

        $effect = $executor->enter($configuration);

        static::assertSame($configuration, $effect->configuration);
    }

    public function testRejectsAConfigurationFromAnotherCompiler(): void
    {
        $executor = new ApprovalExecutor();
        $configuration = static::createStub(NodeConfiguration::class);
        $this->expectExceptionObject(ApprovalFlowException::invalidNodeExecutor(ApprovalExecutor::class, 'Expected ApprovalNodeConfiguration.'));

        $executor->enter($configuration);
    }
}
