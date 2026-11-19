<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Publication;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftService;
use Shopwell\Core\Framework\ApprovalFlow\Publication\GraphPublicationPreflight;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;

/**
 * @internal
 */
#[Package('framework')]
class GraphPublicationPreflightTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private ApprovalDraftService $drafts;

    private GraphPublicationPreflight $preflight;

    protected function setUp(): void
    {
        $this->drafts = static::getContainer()->get(ApprovalDraftService::class);
        static::assertTrue(static::getContainer()->has(GraphPublicationPreflight::class));
        $this->preflight = static::getContainer()->get(GraphPublicationPreflight::class);
    }

    public function testPreparingASavedDraftDoesNotAdvanceItsRevision(): void
    {
        $context = Context::createDefaultContext();
        $draft = $this->drafts->create('Transfer approval', GraphFixture::json(), $context);

        $snapshot = $this->preflight->prepare($draft->graph);

        $reloaded = $this->drafts->read($draft->definitionId, $context);
        static::assertSame($draft->revision, $reloaded->revision);
        static::assertSame($draft->graph->json, $reloaded->graph->json);
        static::assertSame($reloaded->graph->json, $snapshot->graphJson);
    }

    public function testAnIncompleteSavedDraftRemainsUnchangedAfterFailedPreflight(): void
    {
        $context = Context::createDefaultContext();
        $json = str_replace('"outcome": "approved"', '', GraphFixture::json());
        $draft = $this->drafts->create('Incomplete approval', $json, $context);
        $violation = $draft->diagnostics->violations[0];
        $this->expectExceptionObject(ApprovalFlowException::publicationGraphInvalid($violation->code, $violation->path, $violation->message));

        try {
            $this->preflight->prepare($draft->graph);
        } finally {
            $reloaded = $this->drafts->read($draft->definitionId, $context);
            static::assertSame($draft->revision, $reloaded->revision);
            static::assertSame($draft->graph->json, $reloaded->graph->json);
        }
    }

    public function testLaterDraftEditsCannotRewriteAnEarlierSnapshot(): void
    {
        $context = Context::createDefaultContext();
        $draft = $this->drafts->create('Transfer approval', GraphFixture::json(), $context);
        $snapshot = $this->preflight->prepare($draft->graph);
        $hash = $snapshot->hash;

        $this->drafts->removeConnection($draft->definitionId, $draft->revision, 'review-end', confirmRemoval: true, context: $context);

        $reloaded = $this->drafts->read($draft->definitionId, $context);
        static::assertSame(2, $reloaded->revision);
        static::assertNotSame($snapshot->graphJson, $reloaded->graph->json);
        static::assertSame($draft->graph->json, $snapshot->graphJson);
        static::assertSame($hash, $snapshot->hash);
        $violation = $reloaded->diagnostics->violations[0];
        $this->expectExceptionObject(ApprovalFlowException::publicationGraphInvalid($violation->code, $violation->path, $violation->message));

        $this->preflight->prepare($reloaded->graph);
    }
}
