<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Execution\Executor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\AdvanceEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\StartExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\NodeConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\StartNodeConfiguration;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StartExecutor::class)]
class StartExecutorTest extends TestCase
{
    public function testStartRequestsAdvanceWithoutBusinessWork(): void
    {
        $executor = new StartExecutor();
        static::assertSame('core:start', $executor->getType());
        static::assertSame('1.0.0', $executor->getVersion());

        static::assertEquals(new AdvanceEffect(), $executor->enter(new StartNodeConfiguration('payment:transfer')));
    }

    public function testRejectsAConfigurationFromAnotherCompiler(): void
    {
        $executor = new StartExecutor();
        $configuration = static::createStub(NodeConfiguration::class);
        $this->expectExceptionObject(ApprovalFlowException::invalidNodeExecutor(StartExecutor::class, 'Expected StartNodeConfiguration.'));

        $executor->enter($configuration);
    }
}
