<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Publication\Approval;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Codec\WorkflowDocumentCodec;
use Shopwell\Core\Framework\Workflow\Definition\Connection\WorkflowConnection;
use Shopwell\Core\Framework\Workflow\Definition\Node\WorkflowNode;
use Shopwell\Core\Framework\Workflow\Definition\WorkflowDocument;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowViolation;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowCompiler;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Extension\CompileApprovalNodeExtension;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeCompilationContext;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeRole;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\CompiledApprovalNode;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Shopwell\Tests\Unit\Core\Framework\Workflow\_fixtures\ApprovalRegistryFixture;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalWorkflowCompiler::class)]
class ApprovalWorkflowCompilerTest extends TestCase
{
    public function testPluginCompilerMapsVersionedPortsWithoutTreatingTheirNamesAsApprovalRules(): void
    {
        $document = self::document();
        $nodes = [...$document->nodes, new WorkflowNode('plugin', 'example:route', 2, ['destination' => 'finance'])];
        $connections = $document->connections;
        $connections[0] = new WorkflowConnection('enter-plugin', 'start', 'out', 'plugin', 'request');
        $connections[] = new WorkflowConnection('leave-plugin', 'plugin', 'rejected', 'approval', 'in');

        $graph = ApprovalRegistryFixture::compiler(additional: [new ExampleRouteCompiler()])->compile(
            new WorkflowDocument($nodes, $connections),
        );

        static::assertSame(['start', 'plugin', 'approval', 'approved'], array_column($graph['nodes'], 'id'));
        static::assertSame('example:route', $graph['nodes'][1]['type']);
        static::assertSame('finance', $graph['nodes'][1]['properties']['destination']);
        static::assertSame(['type' => 'example:route', 'version' => 2], $graph['nodes'][1]['properties']['approvalNodeCompiler']);
        static::assertSame('rejected', $graph['edges'][1]['properties']['sourcePort']);
    }

    public function testOverrideUsesARegisteredCompilerAndPinsItsVersion(): void
    {
        $replacement = new ExampleRouteCompiler(ApprovalNodeRole::APPROVAL);
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(CompileApprovalNodeExtension::onPre(), static function (CompileApprovalNodeExtension $extension) use ($replacement): void {
            if ($extension->context->nodeId === 'approval') {
                $extension->compiler = $replacement;
            }
        });
        $graph = ApprovalRegistryFixture::compiler([$replacement], $dispatcher)->compile(self::document());

        static::assertSame('example:route', $graph['nodes'][1]['type']);
        static::assertSame(['type' => 'example:route', 'version' => 2], $graph['nodes'][1]['properties']['approvalNodeCompiler']);
    }

    public function testOverrideCannotChangeTheStructuralRole(): void
    {
        $replacement = $this->createMock(ApprovalNodeCompiler::class);
        $replacement->method('getNodeType')->willReturn('example:route');
        $replacement->method('getNodeVersion')->willReturn(2);
        $replacement->method('getRole')->willReturn(ApprovalNodeRole::ACTIVITY);
        $replacement->expects($this->never())->method('compile');
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(CompileApprovalNodeExtension::onPre(), static function (CompileApprovalNodeExtension $extension) use ($replacement): void {
            if ($extension->context->nodeId === 'approval') {
                $extension->compiler = $replacement;
            }
        });
        $this->expectExceptionObject(WorkflowException::publicationRejected('unsupported_approval_workflow'));

        ApprovalRegistryFixture::compiler([$replacement], $dispatcher)->compile(self::document());
    }

    public function testOverrideCannotUseAnUnregisteredCompiler(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(CompileApprovalNodeExtension::onPre(), static function (CompileApprovalNodeExtension $extension): void {
            if ($extension->context->nodeId === 'approval') {
                $extension->compiler = new ExampleRouteCompiler(ApprovalNodeRole::APPROVAL);
            }
        });
        $this->expectExceptionObject(WorkflowException::publicationRejected('unsupported_approval_workflow'));

        ApprovalRegistryFixture::compiler(dispatcher: $dispatcher)->compile(self::document());
    }

    public function testOverrideCannotRelabelAResultAfterCompilation(): void
    {
        $replacement = new ExampleRouteCompiler(ApprovalNodeRole::APPROVAL);
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(CompileApprovalNodeExtension::onPost(), static function (CompileApprovalNodeExtension $extension) use ($replacement): void {
            if ($extension->context->nodeId === 'approval') {
                $extension->compiler = $replacement;
            }
        });
        $this->expectExceptionObject(WorkflowException::publicationRejected('unsupported_approval_workflow'));

        ApprovalRegistryFixture::compiler([$replacement], $dispatcher)->compile(self::document());
    }

    public function testShortCircuitCannotClaimTheUnexecutedCompilerIdentity(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(CompileApprovalNodeExtension::onPre(), static function (CompileApprovalNodeExtension $extension): void {
            if ($extension->context->nodeId === 'approval') {
                $extension->result = new CompiledApprovalNode('example:replacement', outputPorts: ['approved' => []], terminalPorts: ['rejected']);
                $extension->stopPropagation();
            }
        });
        $this->expectExceptionObject(WorkflowException::publicationRejected('unsupported_approval_workflow'));

        ApprovalRegistryFixture::compiler(dispatcher: $dispatcher)->compile(self::document());
    }

    public function testPostReplacementCannotClaimAnotherCompilersIdentity(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(CompileApprovalNodeExtension::onPost(), static function (CompileApprovalNodeExtension $extension): void {
            if ($extension->context->nodeId === 'approval') {
                $extension->result = new CompiledApprovalNode('example:replacement', outputPorts: ['approved' => []], terminalPorts: ['rejected']);
            }
        });
        $this->expectExceptionObject(WorkflowException::publicationRejected('unsupported_approval_workflow'));

        ApprovalRegistryFixture::compiler(dispatcher: $dispatcher)->compile(self::document());
    }

    public function testErrorReplacementCannotBypassCompilerValidation(): void
    {
        $document = self::document();
        $nodes = $document->nodes;
        $nodes[1] = new WorkflowNode('approval', 'core:approval', 1);
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(CompileApprovalNodeExtension::onError(), static function (CompileApprovalNodeExtension $extension): void {
            $extension->result = new CompiledApprovalNode('example:replacement', outputPorts: ['approved' => []], terminalPorts: ['rejected']);
        });
        $this->expectExceptionObject(WorkflowException::publicationRejected('unsupported_approval_workflow'));

        ApprovalRegistryFixture::compiler(dispatcher: $dispatcher)->compile(new WorkflowDocument(array_values($nodes), $document->connections));
    }

    public function testPreservesEndCompilerPropertiesWhenMergingDesignEnds(): void
    {
        $replacement = new ExampleEndCompiler();
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(CompileApprovalNodeExtension::onPre(), static function (CompileApprovalNodeExtension $extension) use ($replacement): void {
            if ($extension->compiler->getRole() === ApprovalNodeRole::END) {
                $extension->compiler = $replacement;
            }
        });
        $graph = ApprovalRegistryFixture::compiler([$replacement], $dispatcher)->compile(self::document());

        static::assertSame('plugin-result', $graph['nodes'][2]['properties']['result']);
        static::assertSame(['type' => 'example:end', 'version' => 1], $graph['nodes'][2]['properties']['approvalNodeCompiler']);
    }

    public function testRejectsMergingEndsWithDifferentRuntimeSemantics(): void
    {
        $replacement = new ExampleEndCompiler(differentResults: true);
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(CompileApprovalNodeExtension::onPre(), static function (CompileApprovalNodeExtension $extension) use ($replacement): void {
            if ($extension->compiler->getRole() === ApprovalNodeRole::END) {
                $extension->compiler = $replacement;
            }
        });
        $this->expectExceptionObject(WorkflowException::publicationRejected('unsupported_approval_workflow'));

        ApprovalRegistryFixture::compiler([$replacement], $dispatcher)->compile(self::document());
    }

    #[DataProvider('publicationLanguages')]
    public function testDefaultPublicationTextUsesTheSelectedLanguage(string $locale, string $reason, string $approval, string $condition, string $fallback): void
    {
        $document = self::document();
        $nodes = [...$document->nodes, new WorkflowNode('condition', 'core:condition', 1, [
            'branches' => [[
                'id' => 'branch-match', 'label' => 'Authored branch', 'field' => 'request.reason',
                'fieldType' => 'text', 'operator' => 'equals', 'value' => 'finance',
            ]],
        ])];
        $connections = $document->connections;
        $connections[0] = new WorkflowConnection('enter-condition', 'start', 'out', 'condition', 'in');
        $connections[] = new WorkflowConnection('match', 'condition', 'branch-match', 'approval', 'in');
        $connections[] = new WorkflowConnection('otherwise', 'condition', 'fallback', 'approved', 'in');
        $graph = ApprovalRegistryFixture::compiler(translator: ApprovalRegistryFixture::translator($locale))->compile(new WorkflowDocument($nodes, $connections));

        static::assertSame($reason, $graph['nodes'][0]['properties']['inputSchema'][0]['label']);
        static::assertSame($condition, $graph['nodes'][1]['properties']['name']);
        static::assertSame($approval, $graph['nodes'][2]['properties']['name']);
        $edges = array_column($graph['edges'], null, 'id');
        static::assertSame($fallback, $edges['otherwise']['properties']['name']);
        static::assertSame('Authored branch', $edges['match']['properties']['name']);
    }

    /**
     * @return iterable<string, array{string, string, string, string, string}>
     */
    public static function publicationLanguages(): iterable
    {
        yield 'English' => ['en', 'Reason for request', 'Approval', 'Condition', 'Otherwise'];
        yield 'Chinese' => ['zh', '申请事由', '审批', '条件分支', '默认分支'];
    }

    public function testCompilesApprovalPathAndUsesRuntimeRejectionTermination(): void
    {
        $graph = ApprovalRegistryFixture::compiler()->compile(self::document());

        static::assertSame([WorkflowNodeTypes::START, WorkflowNodeTypes::TASK, WorkflowNodeTypes::END], array_column($graph['nodes'], 'type'));
        static::assertSame(['start', 'approval', 'approved'], array_column($graph['nodes'], 'id'));
        static::assertSame(['approval', 'approved'], array_column($graph['edges'], 'targetNodeId'));
        static::assertSame(['0123456789abcdef0123456789abcdef'], $graph['nodes'][1]['properties']['assigneeValue']);
        static::assertSame('single', $graph['nodes'][1]['properties']['countersignType']);
    }

    public function testCompilesTheDefaultDesignerApprovalRequestDocument(): void
    {
        $document = (new WorkflowDocumentCodec(Validation::createValidator()))->decode((string) file_get_contents(__DIR__ . '/_fixtures/default-approval-request.json'));
        $graph = ApprovalRegistryFixture::compiler()->compile($document);

        static::assertSame(['start', 'approval', 'end'], array_column($graph['nodes'], 'id'));
        static::assertSame('Approval', $graph['nodes'][1]['properties']['name']);
        static::assertSame(['0123456789abcdef0123456789abcdef'], $graph['nodes'][1]['properties']['assigneeValue']);
        static::assertSame(['approval', 'end'], array_column($graph['edges'], 'targetNodeId'));
    }

    #[DataProvider('multiPersonModes')]
    public function testCompilesAllConfiguredReviewers(string $mode, string $runtimeMode): void
    {
        $document = self::document();
        $nodes = $document->nodes;
        $ids = ['0123456789ABCDEF0123456789ABCDEF', 'abcdef0123456789abcdef0123456789'];
        $nodes[1] = new WorkflowNode('approval', 'core:approval', 1, [
            'assignee' => ['source' => 'core:users', 'config' => ['userIds' => $ids]],
            'approval' => ['mode' => $mode, 'config' => []],
        ]);
        $graph = ApprovalRegistryFixture::compiler()->compile(new WorkflowDocument(array_values($nodes), $document->connections));

        static::assertSame(array_map('strtolower', $ids), $graph['nodes'][1]['properties']['assigneeValue']);
        static::assertSame($runtimeMode, $graph['nodes'][1]['properties']['countersignType']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function multiPersonModes(): iterable
    {
        yield 'all reviewers must agree' => ['core:all', 'countersign'];
        yield 'first decision includes rejection' => ['core:any', 'firstDecision'];
    }

    #[DataProvider('organizationAssignments')]
    public function testCompilesOrganizationAssignments(string $source, string $mode, string $runtimeType, string $runtimeMode): void
    {
        $document = self::document();
        $nodes = $document->nodes;
        $nodes[1] = new WorkflowNode('approval', 'core:approval', 1, [
            'assignee' => ['source' => $source, 'config' => []],
            'approval' => ['mode' => $mode, 'config' => []],
        ]);
        $graph = ApprovalRegistryFixture::compiler()->compile(new WorkflowDocument(array_values($nodes), $document->connections));

        static::assertSame($runtimeType, $graph['nodes'][1]['properties']['assigneeType']);
        static::assertSame([], $graph['nodes'][1]['properties']['assigneeValue']);
        static::assertSame($runtimeMode, $graph['nodes'][1]['properties']['countersignType']);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function organizationAssignments(): iterable
    {
        yield 'department leaders countersign' => ['core:department-leaders', 'core:all', 'unitLeader', 'countersign'];
        yield 'department leaders first decision' => ['core:department-leaders', 'core:any', 'unitLeader', 'firstDecision'];
    }

    public function testOrdersStagesByConnectionsAndPreservesStageText(): void
    {
        $document = self::document();
        $nodes = $document->nodes;
        $second = new WorkflowNode('second', 'core:approval', 1, [
            'label' => 'Finance', 'description' => 'Check the amount',
            'assignee' => ['source' => 'core:users', 'config' => ['userIds' => ['abcdef0123456789abcdef0123456789']]],
            'approval' => ['mode' => 'core:any', 'config' => []],
        ]);
        $connections = $document->connections;
        $connections[1] = new WorkflowConnection('accept', 'approval', 'approved', 'second', 'in');
        $connections[] = new WorkflowConnection('second-accept', 'second', 'approved', 'approved', 'in');
        $connections[] = new WorkflowConnection('second-reject', 'second', 'rejected', 'rejected', 'in');
        $graph = ApprovalRegistryFixture::compiler()->compile(new WorkflowDocument([$second, ...array_reverse($nodes)], array_values(array_reverse($connections))));
        static::assertSame(['start', 'approval', 'second', 'approved'], array_column($graph['nodes'], 'id'));
        static::assertSame('Finance', $graph['nodes'][2]['properties']['name']);
        static::assertSame('Check the amount', $graph['nodes'][2]['properties']['description']);
        static::assertSame('firstDecision', $graph['nodes'][2]['properties']['countersignType']);
    }

    public function testSupportsSharedEnd(): void
    {
        $document = self::document();
        $connections = $document->connections;
        $connections[2] = new WorkflowConnection('reject', 'approval', 'rejected', 'approved', 'in');
        $graph = ApprovalRegistryFixture::compiler()->compile(new WorkflowDocument(\array_slice($document->nodes, 0, 3), array_values($connections)));

        static::assertCount(3, $graph['nodes']);
        static::assertCount(2, $graph['edges']);
    }

    #[DataProvider('unsupportedDocuments')]
    public function testRejectsUnsupportedSemantics(WorkflowDocument $document): void
    {
        $this->expectExceptionObject(WorkflowException::publicationRejected('unsupported_approval_workflow'));

        ApprovalRegistryFixture::compiler()->compile($document);
    }

    /**
     * @return iterable<string, array{WorkflowDocument}>
     */
    public static function unsupportedDocuments(): iterable
    {
        $document = self::document();
        yield 'custom policy is not executed as structured' => [new WorkflowDocument($document->nodes, $document->connections, 'extension:policy')];
        yield 'missing rejected path cannot silently terminate' => [new WorkflowDocument($document->nodes, \array_slice($document->connections, 0, 2))];
        $connections = $document->connections;
        $connections[2] = new WorkflowConnection('reject', 'approval', 'rejected', 'approval', 'in');
        yield 'rejection cannot run another activity' => [new WorkflowDocument($document->nodes, array_values($connections))];
        $connections[2] = new WorkflowConnection('reject', 'approval', 'approved', 'rejected', 'in');
        yield 'duplicate approved output cannot replace rejection' => [new WorkflowDocument($document->nodes, array_values($connections))];
        $connections = $document->connections;
        $connections[1] = new WorkflowConnection('accept', 'approval', 'approved', 'approval', 'in');
        yield 'approved cycle' => [new WorkflowDocument($document->nodes, array_values($connections))];
        $nodes = [...$document->nodes, new WorkflowNode('detached', 'core:approval', 1, $document->nodes[1]->config)];
        yield 'detached approval' => [new WorkflowDocument(array_values($nodes), $document->connections)];
        foreach (['null label' => ['label' => null], 'null description' => ['description' => null], 'oversized label' => ['label' => str_repeat('a', 256)]] as $name => $metadata) {
            $nodes = $document->nodes;
            $nodes[1] = new WorkflowNode('approval', 'core:approval', 1, [...(array) $document->nodes[1]->config, ...$metadata]);
            yield $name => [new WorkflowDocument(array_values($nodes), $document->connections)];
        }
        foreach ([
            'future version' => new WorkflowNode('approval', 'core:approval', 2, $document->nodes[1]->config),
            'unknown activity' => new WorkflowNode('approval', 'extension:approval', 1, $document->nodes[1]->config),
            'multiple source cannot silently become single' => new WorkflowNode('approval', 'core:approval', 1, ['assignee' => ['source' => 'core:users', 'config' => ['userIds' => ['0123456789abcdef0123456789abcdef']]], 'approval' => ['mode' => 'core:single', 'config' => []]]),
            'extension mode' => new WorkflowNode('approval', 'core:approval', 1, ['assignee' => ['source' => 'core:user', 'config' => ['userId' => '0123456789abcdef0123456789abcdef']], 'approval' => ['mode' => 'extension:single', 'config' => []]]),
            'mode options' => new WorkflowNode('approval', 'core:approval', 1, ['assignee' => ['source' => 'core:user', 'config' => ['userId' => '0123456789abcdef0123456789abcdef']], 'approval' => ['mode' => 'core:single', 'config' => ['timeout' => 5]]]),
            'extra assignment options' => new WorkflowNode('approval', 'core:approval', 1, ['assignee' => ['source' => 'core:user', 'config' => ['userId' => '0123456789abcdef0123456789abcdef', 'fallback' => true]], 'approval' => ['mode' => 'core:single', 'config' => []]]),
        ] as $name => $node) {
            $nodes = $document->nodes;
            $nodes[1] = $node;
            yield $name => [new WorkflowDocument(array_values($nodes), $document->connections)];
        }
        foreach ([
            'empty list' => [],
            'duplicate reviewers' => ['0123456789abcdef0123456789abcdef', '0123456789abcdef0123456789abcdef'],
            'case duplicate reviewers' => ['0123456789abcdef0123456789abcdef', '0123456789ABCDEF0123456789ABCDEF'],
            'invalid reviewer' => ['0123456789abcdef0123456789abcdef', 'not-a-user'],
            'not a list' => ['one' => '0123456789abcdef0123456789abcdef', 'two' => 'abcdef0123456789abcdef0123456789'],
        ] as $name => $ids) {
            $nodes = $document->nodes;
            $nodes[1] = new WorkflowNode('approval', 'core:approval', 1, [
                'assignee' => ['source' => 'core:users', 'config' => ['userIds' => $ids]],
                'approval' => ['mode' => 'core:all', 'config' => []],
            ]);
            yield $name => [new WorkflowDocument(array_values($nodes), $document->connections)];
        }
        foreach ([
            'unknown request type' => ['inputType' => 'extension:approval-request'],
            'known request with extra behavior' => ['inputType' => 'core:approval-request', 'autoApprove' => true],
        ] as $name => $config) {
            $nodes = $document->nodes;
            $nodes[0] = new WorkflowNode('start', 'core:start', 1, $config);
            yield $name => [new WorkflowDocument(array_values($nodes), $document->connections)];
        }
        $nodes = $document->nodes;
        $nodes[0] = new WorkflowNode('start', 'core:start', 1, ['trigger' => 'extension:timer']);
        yield 'start options are not discarded' => [new WorkflowDocument(array_values($nodes), $document->connections)];
        $nodes = $document->nodes;
        $nodes[1] = new WorkflowNode(str_repeat('a', 65), 'core:approval', 1, $document->nodes[1]->config);
        yield 'runtime storage cannot represent node identifiers over 64 characters' => [new WorkflowDocument(array_values($nodes), $document->connections)];
        $nodes = [...$document->nodes, new WorkflowNode('extra', 'core:end', 1)];
        yield 'extra nodes are not discarded' => [new WorkflowDocument(array_values($nodes), $document->connections)];
    }

    public function testCompilesConditionalTreeWithImmutableSchemaAndOrderedBranches(): void
    {
        $document = (new WorkflowDocumentCodec(Validation::createValidator()))->decode((string) file_get_contents(__DIR__ . '/_fixtures/expense-approval-request.json'));
        $graph = ApprovalRegistryFixture::compiler()->compile($document);
        static::assertSame(['start', 'department', 'threshold', 'finance', 'default-end'], array_column($graph['nodes'], 'id'));
        static::assertSame('amount', $graph['nodes'][0]['properties']['inputSchema'][1]['type']);
        static::assertSame(['high', 'default'], array_column(array_values(array_filter($graph['edges'], static fn (array $edge): bool => $edge['sourceNodeId'] === 'threshold')), 'id'));
        static::assertSame(['default-end', 'default-end'], array_column(\array_slice($graph['edges'], -2), 'targetNodeId'));
        static::assertSame('finance-end', $graph['edges'][4]['properties']['workflowTargetNodeId']);
    }

    #[DataProvider('invalidConditionalDocuments')]
    public function testRejectsUnexecutableConditionalDrafts(string $mutation): void
    {
        $raw = json_decode((string) file_get_contents(__DIR__ . '/_fixtures/expense-approval-request.json'), true, flags: \JSON_THROW_ON_ERROR);
        if ($mutation === 'missing-default') {
            array_splice($raw['connections'], 4, 1);
        } elseif ($mutation === 'removed-field') {
            array_splice($raw['nodes'][0]['config']['formFields'], 1, 1);
        } elseif ($mutation === 'changed-type') {
            $raw['nodes'][0]['config']['formFields'][1]['type'] = 'text';
        } elseif ($mutation === 'changed-compatible-type') {
            $raw['nodes'][0]['config']['formFields'][1]['type'] = 'number';
        } elseif ($mutation === 'missing-field-type') {
            unset($raw['nodes'][2]['config']['branches'][0]['fieldType']);
        } else {
            $raw['nodes'][2]['config']['branches'][0]['value'] = 'not-a-number';
        }
        $document = (new WorkflowDocumentCodec(Validation::createValidator()))->decode(json_encode($raw, \JSON_THROW_ON_ERROR));
        $this->expectExceptionObject(WorkflowException::publicationRejected('unsupported_approval_workflow'));
        ApprovalRegistryFixture::compiler()->compile($document);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidConditionalDocuments(): iterable
    {
        yield 'every condition requires default' => ['missing-default'];
        yield 'removed references cannot silently rebind' => ['removed-field'];
        yield 'changed types cannot use stale comparison' => ['changed-type'];
        yield 'invalid comparison value' => ['invalid-value'];
        yield 'compatible changed types require explicit rebind' => ['changed-compatible-type'];
        yield 'unbound field type cannot publish' => ['missing-field-type'];
    }

    #[DataProvider('runtimeIdentifierLengths')]
    public function testChecksEveryRuntimeIdentifierAtPublication(int $length, bool $accepted, bool $nodeIdentifier, string $character): void
    {
        $document = self::document();
        $identifier = str_repeat($character, $length);
        $nodes = $document->nodes;
        $connections = $document->connections;
        if ($nodeIdentifier) {
            $nodes[1] = new WorkflowNode($identifier, 'core:approval', 1, $nodes[1]->config);
            $connections = array_map(static fn (WorkflowConnection $connection): WorkflowConnection => new WorkflowConnection(
                $connection->id,
                $connection->source === 'approval' ? $identifier : $connection->source,
                $connection->sourcePort,
                $connection->target === 'approval' ? $identifier : $connection->target,
                $connection->targetPort,
            ), $connections);
        } else {
            // The rejected connection is not emitted at runtime, but still belongs to the published document.
            $connections[2] = new WorkflowConnection($identifier, 'approval', 'rejected', 'rejected', 'in');
        }
        if (!$accepted) {
            $this->expectExceptionObject(WorkflowException::publicationRejected('unsupported_approval_workflow'));
        }
        $graph = ApprovalRegistryFixture::compiler()->compile(new WorkflowDocument(array_values($nodes), array_values($connections)));
        static::assertSame($nodeIdentifier ? $identifier : 'approval', $graph['nodes'][1]['id']);
    }

    /**
     * @return iterable<string, array{int, bool, bool, string}>
     */
    public static function runtimeIdentifierLengths(): iterable
    {
        foreach ([64 => true, 65 => false, 128 => false, 129 => false] as $length => $accepted) {
            yield 'node length ' . $length => [$length, $accepted, true, 'a'];
            yield 'connection length ' . $length => [$length, $accepted, false, 'a'];
        }
        yield 'runtime node storage counts unicode characters' => [64, true, true, '节'];
        yield 'runtime node storage rejects too many unicode characters' => [65, false, true, '节'];
        yield 'runtime connection storage counts unicode characters' => [64, true, false, '边'];
        yield 'runtime connection storage rejects too many unicode characters' => [65, false, false, '边'];
    }

    public function testReportsTheInvalidApprovalConfigurationLocation(): void
    {
        $document = self::document();
        $nodes = $document->nodes;
        $nodes[1] = new WorkflowNode('approval', 'core:approval', 1, [
            'assignee' => ['source' => 'core:user', 'config' => ['userId' => 'not-a-user']],
            'approval' => ['mode' => 'core:single', 'config' => []],
        ]);
        try {
            ApprovalRegistryFixture::compiler()->compile(new WorkflowDocument(array_values($nodes), $document->connections));
            static::fail('Invalid reviewer must reject publication.');
        } catch (WorkflowException $exception) {
            $violations = $exception->getParameters()['violations'];
            static::assertIsArray($violations);
            static::assertInstanceOf(WorkflowViolation::class, $violations[0]);
            static::assertSame('/nodes/1/config/assignee/config/userId', $violations[0]->path);
            static::assertSame('approval', $violations[0]->nodeId);
        }
    }

    public function testReportsTheInputFieldWithinTheStartConfiguration(): void
    {
        $document = self::document();
        $nodes = $document->nodes;
        $nodes[0] = new WorkflowNode('start', 'core:start', 1, ['formFields' => [
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'amount'],
            ['key' => 'amount', 'label' => 'Duplicate', 'type' => 'number'],
        ]]);
        try {
            ApprovalRegistryFixture::compiler()->compile(new WorkflowDocument(array_values($nodes), $document->connections));
            static::fail('Duplicate fields must reject publication.');
        } catch (WorkflowException $exception) {
            $violations = $exception->getParameters()['violations'];
            static::assertIsArray($violations);
            static::assertInstanceOf(WorkflowViolation::class, $violations[0]);
            static::assertSame('/nodes/0/config/formFields/1/key', $violations[0]->path);
            static::assertSame('start', $violations[0]->nodeId);
        }
    }

    public function testReportsTheInvalidConditionBranchLocation(): void
    {
        $raw = json_decode((string) file_get_contents(__DIR__ . '/_fixtures/expense-approval-request.json'), true, flags: \JSON_THROW_ON_ERROR);
        $raw['nodes'][2]['config']['branches'][0]['value'] = 'not-a-number';
        $document = (new WorkflowDocumentCodec(Validation::createValidator()))->decode(json_encode($raw, \JSON_THROW_ON_ERROR));
        try {
            ApprovalRegistryFixture::compiler()->compile($document);
            static::fail('Invalid conditions must reject publication.');
        } catch (WorkflowException $exception) {
            $violations = $exception->getParameters()['violations'];
            static::assertIsArray($violations);
            static::assertInstanceOf(WorkflowViolation::class, $violations[0]);
            static::assertSame('/nodes/2/config/branches/0', $violations[0]->path);
            static::assertSame('threshold', $violations[0]->nodeId);
        }
    }

    private static function document(): WorkflowDocument
    {
        return new WorkflowDocument([
            new WorkflowNode('start', 'core:start', 1),
            new WorkflowNode('approval', 'core:approval', 1, [
                'assignee' => ['source' => 'core:user', 'config' => ['userId' => '0123456789abcdef0123456789abcdef']],
                'approval' => ['mode' => 'core:single', 'config' => []],
            ]),
            new WorkflowNode('approved', 'core:end', 1),
            new WorkflowNode('rejected', 'core:end', 1),
        ], [
            new WorkflowConnection('begin', 'start', 'out', 'approval', 'in'),
            new WorkflowConnection('accept', 'approval', 'approved', 'approved', 'in'),
            new WorkflowConnection('reject', 'approval', 'rejected', 'rejected', 'in'),
        ]);
    }
}

/**
 * @internal
 */
final class ExampleRouteCompiler implements ApprovalNodeCompiler
{
    public function __construct(private readonly ApprovalNodeRole $role = ApprovalNodeRole::ACTIVITY)
    {
    }

    public function getNodeType(): string
    {
        return 'example:route';
    }

    public function getNodeVersion(): int
    {
        return 2;
    }

    public function getRole(): ApprovalNodeRole
    {
        return $this->role;
    }

    public function compile(ApprovalNodeCompilationContext $context): CompiledApprovalNode
    {
        if ($this->role === ApprovalNodeRole::APPROVAL) {
            return new CompiledApprovalNode(
                'example:route',
                outputPorts: ['approved' => []],
                terminalPorts: ['rejected'],
            );
        }

        $configuration = $context->configuration(['destination']);

        return new CompiledApprovalNode(
            'example:route',
            $configuration,
            inputPorts: ['request'],
            outputPorts: ['rejected' => ['sourcePort' => 'must-not-override-mapping']],
        );
    }
}

/**
 * @internal
 */
final class ExampleEndCompiler implements ApprovalNodeCompiler
{
    public function __construct(private readonly bool $differentResults = false)
    {
    }

    public function getNodeType(): string
    {
        return 'example:end';
    }

    public function getNodeVersion(): int
    {
        return 1;
    }

    public function getRole(): ApprovalNodeRole
    {
        return ApprovalNodeRole::END;
    }

    public function compile(ApprovalNodeCompilationContext $context): CompiledApprovalNode
    {
        return new CompiledApprovalNode(
            WorkflowNodeTypes::END,
            ['result' => $this->differentResults ? $context->nodeId : 'plugin-result'],
            outputPorts: [],
        );
    }
}
