<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\AdvanceEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\CompleteEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\RequestApprovalEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\NodeExecutorRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\CoreNodeCompiler;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;

/**
 * @internal
 */
#[Package('framework')]
class NodeEntryTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testRegisteredCoreCompilersAndExecutorsProduceIntentsWithoutChangingTheDraft(): void
    {
        $drafts = static::getContainer()->get(ApprovalDraftService::class);
        $context = Context::createDefaultContext();
        $draft = $drafts->create('Transfer approval', GraphFixture::json(), $context);
        $compiler = static::getContainer()->get(CoreNodeCompiler::class);
        $executors = static::getContainer()->get(NodeExecutorRegistry::class);

        $start = $compiler->compile('core:start', '1.0.0', '{"businessType":"payment:transfer"}');
        $approval = $compiler->compile('core:approval', '1.0.0', '{"assignees":[{"kind":"core:users","userIds":["finance-user"]},{"kind":"core:users","userIds":["finance-user"]}],"policy":{"kind":"core:all","reject":"veto"},"fields":[],"emptyAssignees":"incident"}');
        $end = $compiler->compile('core:end', '1.0.0', '{"outcome":"approved"}');
        $startEffect = $executors->get('core:start', '1.0.0')->enter($start);
        $approvalEffect = $executors->get('core:approval', '1.0.0')->enter($approval);
        $endEffect = $executors->get('core:end', '1.0.0')->enter($end);

        static::assertInstanceOf(AdvanceEffect::class, $startEffect);
        static::assertInstanceOf(RequestApprovalEffect::class, $approvalEffect);
        static::assertSame(['finance-user'], $approvalEffect->configuration->userIds);
        static::assertSame('core:all', $approvalEffect->configuration->policy);
        static::assertInstanceOf(CompleteEffect::class, $endEffect);
        static::assertSame(ApprovalOutcome::Approved, $endEffect->outcome);
        $reloaded = $drafts->read($draft->definitionId, $context);
        static::assertSame($draft->revision, $reloaded->revision);
        static::assertSame($draft->graph->json, $reloaded->graph->json);
    }
}
