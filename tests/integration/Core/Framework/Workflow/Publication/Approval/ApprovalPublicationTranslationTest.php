<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Workflow\Publication\Approval;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeCompilationContext;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\StartApprovalNodeCompiler;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalPublicationTranslationTest extends TestCase
{
    use KernelTestBehaviour;

    #[DataProvider('languages')]
    public function testSystemTranslatorResolvesThePublicationDefaults(string $locale, string $reason, string $fallback): void
    {
        $translator = static::getContainer()->get('translator');
        static::assertInstanceOf(LocaleAwareInterface::class, $translator);
        $originalLocale = $translator->getLocale();

        try {
            $translator->setLocale($locale);
            $compiler = new StartApprovalNodeCompiler(static::getContainer()->get(WorkflowInputSchema::class), $translator);
            $node = $compiler->compile(new ApprovalNodeCompilationContext('start', [], '/nodes/0/config'));

            static::assertSame($reason, $node->properties['inputSchema'][0]['label']);
            static::assertSame($fallback, $translator->trans('workflow.nodes.fallback'));
        } finally {
            $translator->setLocale($originalLocale);
        }
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function languages(): iterable
    {
        yield 'English administration' => ['en-GB', 'Reason for request', 'Otherwise'];
        yield 'Chinese administration' => ['zh-CN', '申请事由', '默认分支'];
    }
}
