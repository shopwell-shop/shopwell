<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\_fixtures;

use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
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
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalCondition;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowCompiler;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeCompilerRegistry;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ConditionApprovalNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\EndApprovalNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\StartApprovalNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\TaskApprovalNodeCompiler;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Translation\Loader\JsonFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

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

    /**
     * @param list<ApprovalNodeCompiler> $additional
     */
    public static function compiler(
        array $additional = [],
        ?EventDispatcher $dispatcher = null,
        ?ApprovalConfigurationCompiler $approvals = null,
        ?TranslatorInterface $translator = null,
    ): ApprovalWorkflowCompiler {
        $translator ??= self::translator();
        $extensions = new ExtensionDispatcher($dispatcher ?? new EventDispatcher());
        $approvals ??= new ApprovalConfigurationCompiler(self::sources(), self::modes(), $extensions);

        return new ApprovalWorkflowCompiler(new ApprovalNodeCompilerRegistry([
            new StartApprovalNodeCompiler(new WorkflowInputSchema(), $translator),
            new TaskApprovalNodeCompiler($approvals, $translator),
            new ConditionApprovalNodeCompiler(new ApprovalCondition(), $translator),
            new EndApprovalNodeCompiler(),
            ...$additional,
        ]), $extensions);
    }

    public static function translator(string $locale = 'en'): Translator
    {
        $translator = new Translator($locale);
        $translator->addLoader('json', new JsonFileLoader());
        $translator->setFallbackLocales(['en']);

        foreach (['en', 'zh'] as $language) {
            $translator->addResource('json', \dirname(__DIR__, 6) . '/src/Core/Framework/Resources/snippet/messages.' . $language . '.base.json', $language);
        }

        return $translator;
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
