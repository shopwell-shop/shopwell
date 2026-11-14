<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Transfer;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalEntity;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalException;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalStore;
use Shopwell\Tests\Integration\Core\System\Payment\Transfer\_fixtures\PaymentTransferApprovalFixture;

/**
 * @internal
 */
#[Package('framework')]
class PaymentTransferApprovalStoreTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testAnApprovalBindingCanBeReadButNeverRebound(): void
    {
        $store = static::getContainer()->get(PaymentTransferApprovalStore::class);
        $approval = $this->approval();
        $store->create($approval);
        static::assertSame($approval->snapshot, $store->get($approval->id)?->snapshot);
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('immutable_transfer'));
        static::getContainer()->get('payment_transfer_approval.repository')->update([['id' => $approval->id, 'instanceId' => Uuid::randomHex()]], Context::createDefaultContext());
    }

    public function testGenericApiCannotCreateAnApprovalBindingEvenInSystemScope(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()));
        $approval = $this->approval();
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('protected_transfer'));
        $context->scope(Context::SYSTEM_SCOPE, function (Context $scoped) use ($approval): void {
            static::getContainer()->get('payment_transfer_approval.repository')->create([[
                'id' => $approval->id, 'releaseId' => $approval->releaseId, 'instanceId' => $approval->instanceId, 'initiatorId' => $approval->initiatorId, 'snapshot' => $approval->snapshot,
            ]], $scoped);
        });
    }

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

    private function approval(): PaymentTransferApprovalEntity
    {
        $approval = new PaymentTransferApprovalEntity();
        $approval->assign(['id' => Uuid::randomHex(), 'releaseId' => Uuid::randomHex(), 'instanceId' => Uuid::randomHex(), 'initiatorId' => Uuid::randomHex(), 'snapshot' => ['amount' => 1200, 'currency' => 'CNY']]);

        return $approval;
    }
}
