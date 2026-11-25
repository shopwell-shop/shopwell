<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Mutation\GraphMutator;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(GraphMutator::class)]
class GraphMutatorTest extends TestCase
{
    public function testRemovingNodeReportsEveryLostConnectionConfigurationAndLayout(): void
    {
        $codec = GraphFixture::codec();
        $graph = $codec->decodeDraft(GraphFixture::json());
        $original = $graph->json;
        $mutator = new GraphMutator($codec, new GraphJsonDecoder());

        $result = $mutator->removeNode($graph, 'review');

        static::assertSame($original, $graph->json);
        static::assertCount(1, $result->removedNodes);
        static::assertStringContainsString('finance-user', $result->removedNodes[0]);
        static::assertCount(2, $result->removedConnections);
        static::assertCount(1, $result->removedLayout);
        static::assertStringNotContainsString('finance-user', $result->graph->json);
        static::assertTrue($result->diagnostics->isWritable());
        static::assertContains('unreachable_node', array_column($result->diagnostics->violations, 'code'));
    }

    public function testFailedConnectionLeavesTheOriginalGraphUnchanged(): void
    {
        $codec = GraphFixture::codec();
        $graph = $codec->decodeDraft(GraphFixture::json());
        $original = $graph->json;
        $mutator = new GraphMutator($codec, new GraphJsonDecoder());

        try {
            $mutator->addConnection($graph, '{"id":"invalid","source":"start","target":"gone","sourcePort":"out","targetPort":"in","condition":{"kind":"always"},"extensions":{}}');
            static::fail('A dangling connection must be rejected.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_GRAPH, $exception->getErrorCode());
        }
        static::assertSame($original, $graph->json);
    }

    public function testAddingNodeAndRemovingConnectionKeepDraftsEditable(): void
    {
        $codec = GraphFixture::codec();
        $graph = $codec->decodeDraft(GraphFixture::json());
        $mutator = new GraphMutator($codec, new GraphJsonDecoder());
        $node = '{"id":"other","type":"core:end","typeVersion":"1.0.0","config":{},"ports":[{"id":"in","direction":"input","channel":"control","cardinality":"one"}],"position":{"x":0,"y":0},"extensions":{}}';

        $added = $mutator->addNode($graph, $node);
        $removed = $mutator->removeConnection($added->graph, 'review-end');

        static::assertStringContainsString('"id":"other"', $added->graph->json);
        static::assertCount(1, $removed->removedConnections);
        static::assertSame([], $removed->removedNodes);
        static::assertSame([], $removed->removedLayout);
        static::assertTrue($removed->diagnostics->isWritable());
    }

    public function testConnectionCanBeRemovedAndReconnectedWithoutChangingItsConfiguration(): void
    {
        $codec = GraphFixture::codec();
        $graph = $codec->decodeDraft(GraphFixture::json());
        $mutator = new GraphMutator($codec, new GraphJsonDecoder());

        $removed = $mutator->removeConnection($graph, 'review-end');
        $reconnected = $mutator->addConnection($removed->graph, $removed->removedConnections[0]);

        static::assertSame($graph->json, $reconnected->graph->json);
        static::assertSame([], $reconnected->diagnostics->violations);
    }

    public function testUnknownNodeCannotBeSilentlyDeletedFromAReadOnlyGraph(): void
    {
        $codec = GraphFixture::codec();
        $graph = $codec->decodeDraft(str_replace('core:approval', 'finance:missing', GraphFixture::json()));
        $mutator = new GraphMutator($codec, new GraphJsonDecoder());

        $this->expectExceptionObject(ApprovalFlowException::invalidGraph('/nodes/1/type', 'Node type finance:missing@1.0.0 is unavailable.'));
        $mutator->removeNode($graph, 'review');
    }

    public function testUnknownNodeIdDoesNotTurnRemovalIntoASuccessfulNoOp(): void
    {
        $codec = GraphFixture::codec();
        $graph = $codec->decodeDraft(GraphFixture::json());
        $mutator = new GraphMutator($codec, new GraphJsonDecoder());

        $this->expectExceptionObject(ApprovalFlowException::invalidGraph('/nodes', 'Node gone does not exist.'));
        $mutator->removeNode($graph, 'gone');
    }

    public function testRawMutationFragmentCannotRoundNumbersBeforeValidation(): void
    {
        $codec = GraphFixture::codec();
        $graph = $codec->decodeDraft(GraphFixture::json());
        $mutator = new GraphMutator($codec, new GraphJsonDecoder());
        $node = '{"id":"other","type":"core:end","typeVersion":"1.0.0","config":{},"ports":[{"id":"in","direction":"input","channel":"control","cardinality":"one"}],"position":{"x":9007199254740991.1,"y":0},"extensions":{}}';

        $this->expectException(ApprovalFlowException::class);
        $mutator->addNode($graph, $node);
    }
}
