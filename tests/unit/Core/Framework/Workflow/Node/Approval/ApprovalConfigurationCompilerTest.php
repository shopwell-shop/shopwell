<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowViolation;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalConfigurationCompiler;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\ApprovalAssigneeRegistry;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\ApprovalAssigneeSource;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\ApprovalAssignment;
use Shopwell\Core\Framework\Workflow\Node\Approval\Extension\CompileApprovalConfigurationExtension;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Shopwell\Tests\Unit\Core\Framework\Workflow\_fixtures\ApprovalRegistryFixture;
use Shopwell\Tests\Unit\Core\Framework\Workflow\_fixtures\QuorumApprovalMode;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalConfigurationCompiler::class)]
class ApprovalConfigurationCompilerTest extends TestCase
{
    public function testCompilesAnInstalledPluginSourceAndMode(): void
    {
        $source = static::createStub(ApprovalAssigneeSource::class);
        $source->method('getName')->willReturn('example:reviewers');
        $source->method('compile')->willReturn(new ApprovalAssignment('example:reviewers', [], true, ['team' => 'finance']));
        $compiler = new ApprovalConfigurationCompiler(
            new ApprovalAssigneeRegistry([$source]),
            ApprovalRegistryFixture::modes([new QuorumApprovalMode()]),
            new ExtensionDispatcher(new EventDispatcher())
        );

        $compiled = $compiler->compile([
            'assignee' => ['source' => 'example:reviewers', 'config' => ['team' => 'finance']],
            'approval' => ['mode' => 'example:quorum', 'version' => 2, 'config' => ['required' => 2]],
        ], '/nodes/1/config', 'review');

        static::assertSame('example:reviewers', $compiled->assignment->type);
        static::assertSame(['team' => 'finance'], $compiled->assignment->configuration);
        static::assertSame('example:quorum', $compiled->mode->getType());
        static::assertSame(2, $compiled->mode->getVersion());
        static::assertSame(['required' => 2], $compiled->configuration);
    }

    public function testPublicationOverrideSelectsAPinnedImplementation(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(CompileApprovalConfigurationExtension::onPre(), static function (CompileApprovalConfigurationExtension $extension): void {
            $extension->configuration['approval'] = ['mode' => 'example:quorum', 'version' => 2, 'config' => ['required' => 2]];
        });
        $compiler = new ApprovalConfigurationCompiler(
            ApprovalRegistryFixture::sources(),
            ApprovalRegistryFixture::modes([new QuorumApprovalMode()]),
            new ExtensionDispatcher($dispatcher)
        );

        $compiled = $compiler->compile([
            'assignee' => ['source' => 'core:users', 'config' => ['userIds' => ['0123456789abcdef0123456789abcdef']]],
            'approval' => ['mode' => 'core:all', 'config' => []],
        ], '/nodes/1/config', 'review');

        static::assertSame('example:quorum', $compiled->mode->getType());
        static::assertSame(2, $compiled->mode->getVersion());
        static::assertSame(['required' => 2], $compiled->configuration);
    }

    public function testSourceDomainErrorWithoutAPathProducesALocatedDiagnostic(): void
    {
        $source = static::createStub(ApprovalAssigneeSource::class);
        $source->method('getName')->willReturn('example:reviewers');
        $source->method('compile')->willThrowException(WorkflowException::publicationRejected('example_team_unavailable'));
        $compiler = new ApprovalConfigurationCompiler(
            new ApprovalAssigneeRegistry([$source]),
            ApprovalRegistryFixture::modes(),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        try {
            $compiler->compile([
                'assignee' => ['source' => 'example:reviewers', 'config' => []],
                'approval' => ['mode' => 'core:all', 'config' => []],
            ], '/nodes/1/config', 'review');
            static::fail('Invalid source configuration must prevent publication.');
        } catch (WorkflowException $exception) {
            $violation = $exception->getParameters()['violations'][0];
            static::assertInstanceOf(WorkflowViolation::class, $violation);
            static::assertSame('/nodes/1/config/assignee/config', $violation->path);
        }
    }

    public function testExplicitNullVersionIsInvalidInsteadOfSelectingVersionOne(): void
    {
        $compiler = new ApprovalConfigurationCompiler(
            ApprovalRegistryFixture::sources(),
            ApprovalRegistryFixture::modes(),
            new ExtensionDispatcher(new EventDispatcher()),
        );
        $this->expectException(WorkflowException::class);

        $compiler->compile([
            'assignee' => ['source' => 'core:user', 'config' => ['userId' => '0123456789abcdef0123456789abcdef']],
            'approval' => ['mode' => 'core:single', 'version' => null, 'config' => []],
        ], '/nodes/1/config', 'review');
    }

    public function testUnknownVersionDoesNotUseTheLatestInstalledMode(): void
    {
        $compiler = new ApprovalConfigurationCompiler(
            ApprovalRegistryFixture::sources(),
            ApprovalRegistryFixture::modes([new QuorumApprovalMode()]),
            new ExtensionDispatcher(new EventDispatcher())
        );

        $this->expectException(WorkflowException::class);

        $compiler->compile([
            'assignee' => ['source' => 'core:users', 'config' => ['userIds' => ['0123456789abcdef0123456789abcdef']]],
            'approval' => ['mode' => 'example:quorum', 'version' => 1, 'config' => ['required' => 2]],
        ], '/nodes/1/config', 'review');
    }
}
