<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Gateway\Audit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateApprovalStatus;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TemplateApprovalStatus::class)]
class TemplateApprovalStatusTest extends TestCase
{
    public function testValuesAreTheFiveStatesInTheOrderTheAdministrationShowsThem(): void
    {
        static::assertSame(
            ['pending', 'approved', 'rejected', 'suspended', 'unknown'],
            TemplateApprovalStatus::values()
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function labelSnippetProvider(): iterable
    {
        yield 'pending' => [TemplateApprovalStatus::PENDING, 'sms.approval.status.pending'];
        yield 'approved' => [TemplateApprovalStatus::APPROVED, 'sms.approval.status.approved'];
        yield 'rejected' => [TemplateApprovalStatus::REJECTED, 'sms.approval.status.rejected'];
        yield 'suspended' => [TemplateApprovalStatus::SUSPENDED, 'sms.approval.status.suspended'];
        yield 'unknown' => [TemplateApprovalStatus::UNKNOWN, 'sms.approval.status.unknown'];
    }

    #[DataProvider('labelSnippetProvider')]
    public function testEveryStateHasItsOwnSnippetKey(string $status, string $expected): void
    {
        static::assertSame($expected, TemplateApprovalStatus::labelSnippet($status));
    }

    public function testAStateTheShopDoesNotKnowIsLabelledAsUnknown(): void
    {
        static::assertSame('sms.approval.status.unknown', TemplateApprovalStatus::labelSnippet('pending_effective'));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function sendableProvider(): iterable
    {
        yield 'pending' => [TemplateApprovalStatus::PENDING, false];
        yield 'approved' => [TemplateApprovalStatus::APPROVED, true];
        yield 'rejected' => [TemplateApprovalStatus::REJECTED, false];
        yield 'suspended' => [TemplateApprovalStatus::SUSPENDED, false];
        yield 'unknown' => [TemplateApprovalStatus::UNKNOWN, false];
        yield 'an unmapped provider code' => ['3', false];
    }

    #[DataProvider('sendableProvider')]
    public function testOnlyAnApprovedTemplateMayBeSent(string $status, bool $expected): void
    {
        static::assertSame($expected, TemplateApprovalStatus::isSendable($status));
    }
}
