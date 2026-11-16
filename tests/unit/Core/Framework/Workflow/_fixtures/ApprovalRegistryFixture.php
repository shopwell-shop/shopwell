<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\_fixtures;

use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalConfigurationCompiler;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\ApprovalAssigneeRegistry;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\DepartmentLeaderApprovalAssigneeSource;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\UserApprovalAssigneeSource;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\AllApprovalMode;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\AnyApprovalMode;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalMode;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalModeRegistry;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\FirstDecisionApprovalMode;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\SequentialApprovalMode;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\SingleApprovalMode;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowCompiler;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
final class ApprovalRegistryFixture
{
    public static function sources(): ApprovalAssigneeRegistry
    {
        return new ApprovalAssigneeRegistry([
            new UserApprovalAssigneeSource(multiple: false),
            new UserApprovalAssigneeSource(multiple: true),
            new DepartmentLeaderApprovalAssigneeSource(),
        ]);
    }

    public static function compiler(): ApprovalWorkflowCompiler
    {
        return new ApprovalWorkflowCompiler(new ApprovalConfigurationCompiler(
            self::sources(),
            self::modes(),
            new ExtensionDispatcher(new EventDispatcher())
        ));
    }

    /**
     * @param list<ApprovalMode> $additional
     */
    public static function modes(array $additional = []): ApprovalModeRegistry
    {
        return new ApprovalModeRegistry([
            new SingleApprovalMode(),
            new AllApprovalMode(),
            new FirstDecisionApprovalMode(),
            new AnyApprovalMode(),
            new SequentialApprovalMode(),
            ...$additional,
        ]);
    }
}
