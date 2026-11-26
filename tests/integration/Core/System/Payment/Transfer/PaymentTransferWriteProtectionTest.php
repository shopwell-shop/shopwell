<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Transfer;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalException;
use Shopwell\Tests\Integration\Core\System\Payment\Transfer\_fixtures\PaymentTransferApprovalFixture;

/**
 * @internal
 */
#[Package('framework')]
class PaymentTransferWriteProtectionTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testSystemCodeCannotRewriteAnApprovedAmount(): void
    {
        $transfer = PaymentTransferApprovalFixture::create(static::getContainer());
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('immutable_transfer'));
        static::getContainer()->get('payment_transfer.repository')->update([['id' => $transfer->id, 'amount' => 9999]], Context::createDefaultContext());
    }

    public function testGenericTransitionCannotForgeFinancialState(): void
    {
        $transfer = PaymentTransferApprovalFixture::create(static::getContainer());
        $context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()));
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('protected_transfer'));
        $context->scope(Context::SYSTEM_SCOPE, function (Context $scoped) use ($transfer): void {
            static::getContainer()->get('payment_transfer.repository')->update([['id' => $transfer->id, 'submitAttempts' => 0]], $scoped);
        });
    }
}
