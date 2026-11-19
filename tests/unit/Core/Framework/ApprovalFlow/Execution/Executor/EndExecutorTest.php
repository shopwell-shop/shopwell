<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Execution\Executor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\EndExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\EndNodeConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\NodeConfiguration;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EndExecutor::class)]
class EndExecutorTest extends TestCase
{
    public function testEndPreservesTheConfiguredRejection(): void
    {
        $executor = new EndExecutor();
        static::assertSame('core:end', $executor->getType());
        static::assertSame('1.0.0', $executor->getVersion());

        $effect = $executor->enter(new EndNodeConfiguration(ApprovalOutcome::Rejected));

        static::assertSame(ApprovalOutcome::Rejected, $effect->outcome);
    }

    public function testRejectsAConfigurationFromAnotherCompiler(): void
    {
        $executor = new EndExecutor();
        $configuration = static::createStub(NodeConfiguration::class);
        $this->expectExceptionObject(ApprovalFlowException::invalidNodeExecutor(EndExecutor::class, 'Expected EndNodeConfiguration.'));

        $executor->enter($configuration);
    }
}
