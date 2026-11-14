<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Node\WorkflowNode;
use Shopwell\Core\Framework\Workflow\Definition\WorkflowDocument;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowViolation;
use Shopwell\Core\Framework\Workflow\Event\WorkflowValidationEvent;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalWorkflowSubscriber::class)]
class ApprovalWorkflowSubscriberTest extends TestCase
{
    public function testIncompleteDraftReportsBothMissingFieldsWithoutChangingTheDocument(): void
    {
        $document = new WorkflowDocument([new WorkflowNode('review', 'core:approval', 1)], []);
        $event = new WorkflowValidationEvent($document);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ApprovalWorkflowSubscriber());

        $dispatcher->dispatch($event);

        static::assertEquals([
            new WorkflowViolation('approval_assignee_required', '/nodes/0/config/assignee', 'review'),
            new WorkflowViolation('approval_invalid_configuration', '/nodes/0/config/approval', 'review'),
        ], $event->getViolations());
        static::assertSame([], $document->nodes[0]->config);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('validConfigurations')]
    public function testAcceptsValidConfigurationWithoutNormalizingIt(array $config): void
    {
        $node = new WorkflowNode('review', 'core:approval', 1, $config);
        $event = new WorkflowValidationEvent(new WorkflowDocument([$node], []));

        (new ApprovalWorkflowSubscriber())->validate($event);

        static::assertSame([], $event->getViolations());
        static::assertSame($config, $node->config);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function validConfigurations(): iterable
    {
        yield 'one built-in user and single approval' => [[
            'assignee' => ['source' => 'core:user', 'config' => ['userId' => '0123456789abcdef0123456789abcdef']],
            'approval' => ['mode' => 'core:single', 'config' => []],
        ]];
        yield 'all reviewers approve with preserved selection order and case' => [[
            'assignee' => ['source' => 'core:users', 'config' => ['userIds' => ['FEDCBA9876543210FEDCBA9876543210', '0123456789abcdef0123456789abcdef']]],
            'approval' => ['mode' => 'core:all', 'config' => []],
        ]];
        yield 'any reviewer decides with a single selected user' => [[
            'assignee' => (object) ['source' => 'core:users', 'config' => (object) ['userIds' => ['0123456789abcdef0123456789abcdef']]],
            'approval' => (object) ['mode' => 'core:any', 'config' => new \stdClass()],
        ]];
        yield 'extension source can use built-in single mode' => [[
            'assignee' => ['source' => 'example:team', 'config' => []],
            'approval' => ['mode' => 'core:single', 'config' => []],
        ]];
        yield 'extension source can use built-in all mode' => [[
            'assignee' => ['source' => 'example:team', 'config' => []],
            'approval' => ['mode' => 'core:all', 'config' => []],
        ]];
        yield 'extension source can use built-in any mode' => [[
            'assignee' => ['source' => 'example:team', 'config' => []],
            'approval' => ['mode' => 'core:any', 'config' => []],
        ]];
        yield 'single user can use an extension mode' => [[
            'assignee' => ['source' => 'core:user', 'config' => ['userId' => '0123456789abcdef0123456789abcdef']],
            'approval' => ['mode' => 'example:quorum', 'config' => ['votes' => 1]],
        ]];
        yield 'multiple users can use an extension mode' => [[
            'assignee' => ['source' => 'core:users', 'config' => ['userIds' => ['0123456789abcdef0123456789abcdef']]],
            'approval' => ['mode' => 'example:quorum', 'config' => ['votes' => 1]],
        ]];
        yield 'object metadata is accepted' => [[
            'assignee' => (object) ['source' => 'core:user', 'config' => (object) ['userId' => '0123456789abcdef0123456789abcdef']],
            'approval' => (object) ['mode' => 'core:single', 'config' => new \stdClass()],
        ]];
        yield 'extension metadata belongs to extension validators' => [[
            'assignee' => ['source' => 'example:team', 'config' => ['teamId' => 'finance']],
            'approval' => ['mode' => 'example:quorum', 'config' => ['votes' => 3]],
        ]];
        yield 'extension configuration may be empty' => [[
            'assignee' => ['source' => 'example:team', 'config' => []],
            'approval' => ['mode' => 'example:quorum', 'config' => []],
        ]];
    }

    #[DataProvider('invalidAssignees')]
    public function testReportsInvalidAssignee(mixed $assignee, string $code, string $path): void
    {
        $event = new WorkflowValidationEvent(new WorkflowDocument([
            new WorkflowNode('review', 'core:approval', 1, [
                'assignee' => $assignee,
                'approval' => ['mode' => 'core:single', 'config' => []],
            ]),
        ], []));

        (new ApprovalWorkflowSubscriber())->validate($event);

        static::assertEquals([new WorkflowViolation($code, '/nodes/0/config/assignee' . $path, 'review')], $event->getViolations());
    }

    /**
     * @return iterable<string, array{mixed, string, string}>
     */
    public static function invalidAssignees(): iterable
    {
        yield 'null assignment is missing' => [null, 'approval_assignee_required', ''];
        yield 'assignment must be an object' => ['core:user', 'approval_invalid_configuration', ''];
        yield 'assignment cannot be a list' => [['core:user'], 'approval_invalid_configuration', ''];
        yield 'source is required' => [['config' => []], 'approval_invalid_configuration', ''];
        yield 'source cannot be empty' => [['source' => '', 'config' => []], 'approval_invalid_configuration', ''];
        yield 'source must be namespaced' => [['source' => 'user', 'config' => []], 'approval_invalid_configuration', ''];
        yield 'source must be a string' => [['source' => 1, 'config' => []], 'approval_invalid_configuration', ''];
        yield 'configuration is required' => [['source' => 'core:user'], 'approval_invalid_configuration', ''];
        yield 'configuration cannot be null' => [['source' => 'core:user', 'config' => null], 'approval_invalid_configuration', ''];
        yield 'configuration cannot be a list' => [['source' => 'core:user', 'config' => ['user']], 'approval_invalid_configuration', ''];
        yield 'extension configuration must also be an object' => [['source' => 'example:team', 'config' => false], 'approval_invalid_configuration', ''];
        yield 'missing user needs assignment' => [['source' => 'core:user', 'config' => []], 'approval_assignee_required', '/config/userId'];
        yield 'null user needs assignment' => [['source' => 'core:user', 'config' => ['userId' => null]], 'approval_assignee_required', '/config/userId'];
        yield 'empty user needs assignment' => [['source' => 'core:user', 'config' => ['userId' => '']], 'approval_assignee_required', '/config/userId'];
        yield 'whitespace is not a UUID' => [['source' => 'core:user', 'config' => ['userId' => ' ']], 'approval_invalid_configuration', '/config/userId'];
        yield 'malformed UUID is rejected' => [['source' => 'core:user', 'config' => ['userId' => 'invalid']], 'approval_invalid_configuration', '/config/userId'];
        yield 'UUID with trailing newline is rejected' => [['source' => 'core:user', 'config' => ['userId' => "0123456789abcdef0123456789abcdef\n"]], 'approval_invalid_configuration', '/config/userId'];
        yield 'user cannot be a number' => [['source' => 'core:user', 'config' => ['userId' => 1]], 'approval_invalid_configuration', '/config/userId'];
        yield 'empty user list is malformed' => [['source' => 'core:user', 'config' => ['userId' => []]], 'approval_invalid_configuration', '/config/userId'];
        yield 'multiple users are not one assignee' => [['source' => 'core:user', 'config' => ['userId' => ['0123456789abcdef0123456789abcdef']]], 'approval_invalid_configuration', '/config/userId'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidUserLists')]
    public function testReportsInvalidUserList(array $config, string $code): void
    {
        $event = new WorkflowValidationEvent(new WorkflowDocument([
            new WorkflowNode('review', 'core:approval', 1, [
                'assignee' => ['source' => 'core:users', 'config' => $config],
                'approval' => ['mode' => 'core:all', 'config' => []],
            ]),
        ], []));

        (new ApprovalWorkflowSubscriber())->validate($event);

        static::assertEquals([new WorkflowViolation($code, '/nodes/0/config/assignee/config/userIds', 'review')], $event->getViolations());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidUserLists(): iterable
    {
        yield 'missing list needs assignment' => [[], 'approval_assignee_required'];
        yield 'null list needs assignment' => [['userIds' => null], 'approval_assignee_required'];
        yield 'empty list needs assignment' => [['userIds' => []], 'approval_assignee_required'];
        yield 'single UUID is not a list' => [['userIds' => '0123456789abcdef0123456789abcdef'], 'approval_invalid_configuration'];
        yield 'empty string is not a list' => [['userIds' => ''], 'approval_invalid_configuration'];
        yield 'boolean is not a list' => [['userIds' => false], 'approval_invalid_configuration'];
        yield 'empty object is not a list' => [['userIds' => new \stdClass()], 'approval_invalid_configuration'];
        yield 'associative array is not a list' => [['userIds' => ['reviewer' => '0123456789abcdef0123456789abcdef']], 'approval_invalid_configuration'];
        yield 'sparse array is not a list' => [['userIds' => [1 => '0123456789abcdef0123456789abcdef']], 'approval_invalid_configuration'];
        yield 'invalid UUID is rejected' => [['userIds' => ['invalid']], 'approval_invalid_configuration'];
        yield 'UUID with trailing newline is rejected' => [['userIds' => ["0123456789abcdef0123456789abcdef\n"]], 'approval_invalid_configuration'];
        yield 'non-string UUID is rejected' => [['userIds' => [1]], 'approval_invalid_configuration'];
        yield 'null UUID is rejected' => [['userIds' => [null]], 'approval_invalid_configuration'];
        yield 'duplicate user is rejected' => [['userIds' => ['0123456789abcdef0123456789abcdef', '0123456789abcdef0123456789abcdef']], 'approval_invalid_configuration'];
        yield 'duplicate user with different case is rejected' => [['userIds' => ['0123456789abcdef0123456789abcdef', '0123456789ABCDEF0123456789ABCDEF']], 'approval_invalid_configuration'];
    }

    /**
     * @param array<string, mixed> $assigneeConfig
     */
    #[DataProvider('incompatibleModes')]
    public function testReportsIncompatibleBuiltInMode(string $source, array $assigneeConfig, string $mode): void
    {
        $event = new WorkflowValidationEvent(new WorkflowDocument([
            new WorkflowNode('review', 'core:approval', 1, [
                'assignee' => ['source' => $source, 'config' => $assigneeConfig],
                'approval' => ['mode' => $mode, 'config' => []],
            ]),
        ], []));

        (new ApprovalWorkflowSubscriber())->validate($event);

        static::assertEquals([new WorkflowViolation('approval_incompatible_mode', '/nodes/0/config/approval/mode', 'review')], $event->getViolations());
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function incompatibleModes(): iterable
    {
        yield 'one user cannot use all mode' => ['core:user', ['userId' => '0123456789abcdef0123456789abcdef'], 'core:all'];
        yield 'one user cannot use any mode' => ['core:user', ['userId' => '0123456789abcdef0123456789abcdef'], 'core:any'];
        yield 'user list cannot use single mode' => ['core:users', ['userIds' => ['0123456789abcdef0123456789abcdef']], 'core:single'];
    }

    #[DataProvider('invalidApprovals')]
    public function testReportsInvalidApproval(mixed $approval): void
    {
        $event = new WorkflowValidationEvent(new WorkflowDocument([
            new WorkflowNode('review', 'core:approval', 1, [
                'assignee' => ['source' => 'core:user', 'config' => ['userId' => '0123456789abcdef0123456789abcdef']],
                'approval' => $approval,
            ]),
        ], []));

        (new ApprovalWorkflowSubscriber())->validate($event);

        static::assertEquals([new WorkflowViolation('approval_invalid_configuration', '/nodes/0/config/approval', 'review')], $event->getViolations());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidApprovals(): iterable
    {
        yield 'null approval is incomplete' => [null];
        yield 'approval must be an object' => ['core:single'];
        yield 'approval cannot be a list' => [['core:single']];
        yield 'mode is required' => [['config' => []]];
        yield 'mode cannot be empty' => [['mode' => '', 'config' => []]];
        yield 'mode must be namespaced' => [['mode' => 'single', 'config' => []]];
        yield 'mode must be a string' => [['mode' => 1, 'config' => []]];
        yield 'configuration is required' => [['mode' => 'core:single']];
        yield 'configuration cannot be null' => [['mode' => 'core:single', 'config' => null]];
        yield 'configuration cannot be a list' => [['mode' => 'core:single', 'config' => ['votes']]];
        yield 'all approval has no options' => [['mode' => 'core:all', 'config' => ['votes' => 2]]];
        yield 'any approval has no options' => [['mode' => 'core:any', 'config' => ['votes' => 2]]];
        yield 'single approval has no options' => [['mode' => 'core:single', 'config' => ['votes' => 2]]];
        yield 'extension configuration must also be an object' => [['mode' => 'example:quorum', 'config' => false]];
    }

    public function testIgnoresOtherNodeTypesAndVersionsWhileKeepingDiagnosticNodePosition(): void
    {
        $event = new WorkflowValidationEvent(new WorkflowDocument([
            new WorkflowNode('custom', 'example:approval', 1),
            new WorkflowNode('future', 'core:approval', 2),
            new WorkflowNode('start', 'core:start', 1),
            new WorkflowNode('review', 'core:approval', 1),
        ], []));

        (new ApprovalWorkflowSubscriber())->validate($event);

        static::assertEquals([
            new WorkflowViolation('approval_assignee_required', '/nodes/3/config/assignee', 'review'),
            new WorkflowViolation('approval_invalid_configuration', '/nodes/3/config/approval', 'review'),
        ], $event->getViolations());
    }
}
