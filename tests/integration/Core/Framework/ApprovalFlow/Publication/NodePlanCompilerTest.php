<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Publication;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftService;
use Shopwell\Core\Framework\ApprovalFlow\Publication\NodePlanCompiler;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;

/**
 * @internal
 */
#[Package('framework')]
class NodePlanCompilerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private ApprovalDraftService $drafts;

    private NodePlanCompiler $compiler;

    protected function setUp(): void
    {
        $this->drafts = static::getContainer()->get(ApprovalDraftService::class);
        static::assertTrue(static::getContainer()->has(NodePlanCompiler::class));
        $this->compiler = static::getContainer()->get(NodePlanCompiler::class);
    }

    public function testCompilingConfigurationsDoesNotWriteTheSavedDraft(): void
    {
        $context = Context::createDefaultContext();
        $draft = $this->drafts->create('Transfer approval', GraphFixture::json(), $context);

        $plan = $this->compiler->compile($draft->graph);

        $reloaded = $this->drafts->read($draft->definitionId, $context);
        static::assertSame($draft->revision, $reloaded->revision);
        static::assertSame($draft->graph->json, $reloaded->graph->json);
        static::assertSame($reloaded->graph->json, $plan->graphPlan->snapshot->graphJson);
    }

    public function testSemanticFailureLeavesTheSavedDraftEditableAndUnchanged(): void
    {
        $context = Context::createDefaultContext();
        $json = str_replace('"finance-user"', '"one", "two"', str_replace('core:all', 'core:single', GraphFixture::json()));
        $draft = $this->drafts->create('Draft with invalid single approval', $json, $context);

        try {
            $this->compiler->compile($draft->graph);
            static::fail('A complete schema is not proof of valid single-approval semantics.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_NODE_CONFIGURATION, $exception->getErrorCode());
            static::assertSame('/nodes/1/config/policy/kind', $exception->getParameters()['path']);
        }

        $reloaded = $this->drafts->read($draft->definitionId, $context);
        static::assertSame($draft->revision, $reloaded->revision);
        static::assertSame($draft->graph->json, $reloaded->graph->json);
    }
}
