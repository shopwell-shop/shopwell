<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Publication;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftService;
use Shopwell\Core\Framework\ApprovalFlow\Publication\GraphPlanCompiler;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;

/**
 * @internal
 */
#[Package('framework')]
class GraphPlanCompilerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private ApprovalDraftService $drafts;

    private GraphPlanCompiler $compiler;

    protected function setUp(): void
    {
        $this->drafts = static::getContainer()->get(ApprovalDraftService::class);
        static::assertTrue(static::getContainer()->has(GraphPlanCompiler::class));
        $this->compiler = static::getContainer()->get(GraphPlanCompiler::class);
    }

    public function testCompilingASavedDraftDoesNotChangeItsContentOrRevision(): void
    {
        $context = Context::createDefaultContext();
        $draft = $this->drafts->create('Transfer approval', GraphFixture::json(), $context);

        $compiled = $this->compiler->compile($draft->graph);

        $reloaded = $this->drafts->read($draft->definitionId, $context);
        static::assertSame($draft->revision, $reloaded->revision);
        static::assertSame($draft->graph->json, $reloaded->graph->json);
        static::assertSame($reloaded->graph->json, $compiled->snapshot->graphJson);
        $plan = json_decode($compiled->json, associative: true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($plan);
        static::assertSame($compiled->snapshot->hash, $plan['snapshotHash']);
    }

    public function testCompilationFailureLeavesAnIncompleteSavedDraftUntouched(): void
    {
        $context = Context::createDefaultContext();
        $draft = $this->drafts->create('Incomplete approval', str_replace('"outcome": "approved"', '', GraphFixture::json()), $context);
        $violation = $draft->diagnostics->violations[0];
        $this->expectExceptionObject(ApprovalFlowException::publicationGraphInvalid($violation->code, $violation->path, $violation->message));

        try {
            $this->compiler->compile($draft->graph);
        } finally {
            $reloaded = $this->drafts->read($draft->definitionId, $context);
            static::assertSame($draft->revision, $reloaded->revision);
            static::assertSame($draft->graph->json, $reloaded->graph->json);
        }
    }
}
