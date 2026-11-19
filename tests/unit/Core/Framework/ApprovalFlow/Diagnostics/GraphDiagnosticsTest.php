<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Diagnostics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Specification\NodeTypeSpecification;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(GraphDiagnostics::class)]
class GraphDiagnosticsTest extends TestCase
{
    public function testBrokenPluginSchemaFailsWithItsDeclarationSource(): void
    {
        $loader = new class implements NodeTypeLoader {
            public function load(): array
            {
                return [new NodeTypeSpecification('finance:review', '1.0.0', 'finance.review', 'finance:review.yaml', '{"$id":"urn:finance:broken","type":"object","properties":false}', ['in' => 'input', 'out' => 'output'])];
            }
        };
        $json = str_replace('core:approval', 'finance:review', GraphFixture::json());

        $this->expectExceptionObject(ApprovalFlowException::invalidNodeType('finance:review.yaml', 'configuration schema cannot be evaluated'));
        GraphFixture::diagnostics([$loader])->analyze(new DraftGraph($json));
    }

    public function testUnsafeJsonNumbersAreRejectedBeforeCanonicalization(): void
    {
        $json = str_replace('"x": 0', '"x": 9007199254740993', GraphFixture::json());
        $report = GraphFixture::diagnostics()->analyze(new DraftGraph($json));

        static::assertFalse($report->isWritable());
        static::assertSame('unsafe_number', $report->violations[0]->code);
        static::assertSame('/nodes/0/position/x', $report->violations[0]->path);
    }

    #[DataProvider('structuralDefectProvider')]
    public function testReportsStructuralDefectsWithoutMutatingTheSource(string $search, string $replacement, string $code, string $path): void
    {
        $json = str_replace($search, $replacement, GraphFixture::json());
        $report = GraphFixture::diagnostics()->analyze(new DraftGraph($json));

        static::assertFalse($report->isWritable());
        static::assertSame($code, $report->violations[0]->code);
        static::assertSame($path, $report->violations[0]->path);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function structuralDefectProvider(): iterable
    {
        yield 'duplicate connection identity' => ['"id": "review-end"', '"id": "start-review"', 'duplicate_connection', '/connections/1/id'];
        yield 'missing port reference' => ['"targetPort": "in"', '"targetPort": "gone"', 'dangling_port', '/connections/0/targetPort'];
        yield 'input used as source' => ['"sourcePort": "out"', '"sourcePort": "in"', 'dangling_port', '/connections/0/sourcePort'];
        yield 'wrong declared direction' => ['"direction": "input"', '"direction": "output"', 'invalid_port', '/nodes/1/ports/0'];
        yield 'layout references missing node' => ['"nodeId": "review"', '"nodeId": "gone"', 'invalid_layout_reference', '/layout/nodes/0/nodeId'];
        yield 'map replaced by list' => ['"extensions": {}', '"extensions": []', 'schema_type', '/nodes/0/extensions'];
        yield 'invalid filled configuration' => ['"emptyAssignees": "incident"', '"emptyAssignees": null', 'schema_enum', '/nodes/1/config/emptyAssignees'];
    }

    public function testCompleteLinearGraphHasNoGraphViolations(): void
    {
        $graph = GraphFixture::codec()->decodeDraft(GraphFixture::json());

        static::assertSame([], GraphFixture::diagnostics()->analyze($graph)->violations);
    }

    public function testIncompleteConfigurationIsDifferentFromMissingCapability(): void
    {
        $codec = GraphFixture::codec();
        $incomplete = $codec->decodeDraft(str_replace('"outcome": "approved"', '', GraphFixture::json()));
        $missing = $codec->decodeDraft(str_replace('core:approval', 'finance:missing', GraphFixture::json()));

        $draftReport = GraphFixture::diagnostics()->analyze($incomplete);
        $missingReport = GraphFixture::diagnostics()->analyze($missing);
        static::assertSame('draft', $draftReport->violations[0]->category->value);
        static::assertTrue($draftReport->isWritable());
        static::assertSame('capability', $missingReport->violations[0]->category->value);
        static::assertFalse($missingReport->isWritable());
    }

    public function testDanglingConnectionHasAnExactPathAndIsNeverRepaired(): void
    {
        $json = str_replace('"target": "review"', '"target": "gone"', GraphFixture::json());
        $graph = new DraftGraph($json);
        $report = GraphFixture::diagnostics()->analyze($graph);

        static::assertSame('dangling_node', $report->violations[0]->code);
        static::assertSame('/connections/0/target', $report->violations[0]->path);
        static::assertFalse($report->isWritable());
        static::assertSame($json, $graph->json);
    }

    public function testArbitraryCycleBlocksCompletenessButRemainsEditable(): void
    {
        $json = str_replace('"target": "end"', '"target": "review"', GraphFixture::json());
        $report = GraphFixture::diagnostics()->analyze(new DraftGraph($json));

        static::assertContains('cycle', array_column($report->violations, 'code'));
        static::assertTrue($report->isWritable());
    }
}
