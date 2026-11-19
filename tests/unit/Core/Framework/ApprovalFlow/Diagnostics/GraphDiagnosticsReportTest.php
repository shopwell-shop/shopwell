<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Diagnostics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnosticsReport;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphViolation;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\ViolationCategory;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(GraphDiagnosticsReport::class)]
class GraphDiagnosticsReportTest extends TestCase
{
    #[DataProvider('writeAdmissionProvider')]
    public function testWriteAdmissionDistinguishesDraftsFromUnsafeDocuments(?ViolationCategory $category, bool $writable): void
    {
        $violations = $category === null ? [] : [new GraphViolation($category, 'example', '/nodes', 'Example violation.')];

        static::assertSame($writable, (new GraphDiagnosticsReport($violations))->isWritable());
    }

    /**
     * @return iterable<string, array{?ViolationCategory, bool}>
     */
    public static function writeAdmissionProvider(): iterable
    {
        yield 'complete graph' => [null, true];
        yield 'unfinished draft' => [ViolationCategory::Draft, true];
        yield 'broken structure' => [ViolationCategory::Structure, false];
        yield 'missing extension capability' => [ViolationCategory::Capability, false];
    }
}
