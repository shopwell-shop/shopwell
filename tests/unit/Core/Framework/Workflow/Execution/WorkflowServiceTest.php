<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;
use Shopwell\Tests\Unit\Core\Framework\Workflow\_fixtures\ApprovalRegistryFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowService::class)]
class WorkflowServiceTest extends TestCase
{
    #[DataProvider('languages')]
    public function testDefaultGraphNamesUseTheSelectedLanguage(string $locale, string $start, string $end): void
    {
        $graph = WorkflowService::initialGraph(ApprovalRegistryFixture::translator($locale));

        static::assertSame($start, $graph['nodes'][0]['properties']['name']);
        static::assertSame($end, $graph['nodes'][1]['properties']['name']);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function languages(): iterable
    {
        yield 'English' => ['en', 'Initiator', 'End'];
        yield 'Chinese' => ['zh', '发起人', '结束'];
    }
}
