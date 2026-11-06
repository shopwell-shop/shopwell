<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Transfer;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Exception\PaymentTransferException;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferResultBooker;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;

/**
 * @internal
 */
#[Package('framework')]
class PaymentTransferResultBookerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testTwoStaleCallersCanClaimOnlyOneAttempt(): void
    {
        $transfer = $this->createTransfer();
        $booker = static::getContainer()->get(PaymentTransferResultBooker::class);
        $booker->claim($transfer);

        static::assertSame(1, static::getContainer()->get(PaymentTransferService::class)->getById($transfer->id)->submitAttempts);
        static::assertSame(0, $transfer->submitAttempts);
        $this->expectExceptionObject(PaymentTransferException::transferAlreadyFinalized($transfer->outNo, 'unknown'));

        $booker->claim($transfer);
    }

    public function testAStaleUnclaimedEntityCannotCancelAClaimedAttempt(): void
    {
        $transfer = $this->createTransfer();
        $booker = static::getContainer()->get(PaymentTransferResultBooker::class);
        $booker->claim($transfer);

        try {
            $booker->cancel($transfer, 'operator canceled from a stale page');
            static::fail('An attempt that may have reached the channel cannot be canceled locally.');
        } catch (PaymentTransferException $exception) {
            static::assertSame(PaymentTransferException::TRANSFER_ALREADY_FINALIZED, $exception->getErrorCode());
        }

        $fresh = static::getContainer()->get(PaymentTransferService::class)->getById($transfer->id);
        static::assertSame(1, $fresh->submitAttempts);
        static::assertNull($fresh->canceledAt);
        static::assertSame(PaymentTransferStates::STATE_CREATED, $fresh->statusMachineState?->getTechnicalName());
    }

    public function testACanceledPayoutCannotBeClaimedThroughAStaleEntity(): void
    {
        $transfer = $this->createTransfer();
        $booker = static::getContainer()->get(PaymentTransferResultBooker::class);
        $booker->cancel($transfer, 'operator rejected');

        $fresh = static::getContainer()->get(PaymentTransferService::class)->getById($transfer->id);
        static::assertNotNull($fresh->canceledAt);
        static::assertSame(0, $fresh->submitAttempts);
        $this->expectExceptionObject(PaymentTransferException::transferAlreadyFinalized($transfer->outNo, 'unknown'));

        $booker->claim($transfer);
    }

    private function createTransfer(): PaymentTransferEntity
    {
        $container = static::getContainer();
        $context = Context::createDefaultContext();
        $appId = Uuid::randomHex();
        $recipientId = Uuid::randomHex();
        $transferId = Uuid::randomHex();
        $accountId = Uuid::randomHex();
        $container->get('payment_account.repository')->create([[
            'id' => $accountId,
            'providerTechnicalName' => 'alipay',
            'name' => 'Transfer claim account',
            'enabledProducts' => ['alipay_h5'],
            'active' => true,
        ]], $context);
        $cipher = $container->get(PaymentCredentialCipher::class);
        $container->get('payment_app.repository')->create([[
            'id' => $appId,
            'technicalName' => 'transfer_claim_' . $appId,
            'name' => 'Transfer claim test',
            'type' => PaymentAppEntity::TYPE_EXTERNAL,
            'appSecret' => $cipher->encryptValue('secret'),
            'active' => true,
        ]], $context);
        $container->get('payment_transfer_recipient.repository')->create([[
            'id' => $recipientId,
            'paymentAppId' => $appId,
            'provider' => 'alipay',
            'identityType' => 'alipay_user_id',
            'identityValue' => $cipher->encryptValue('recipient'),
            'identityHash' => hash('sha256', $recipientId),
            'onboardingStatus' => PaymentTransferStates::ONBOARDING_REGISTERED,
            'payoutEnabled' => true,
        ]], $context);
        $container->get('payment_transfer.repository')->create([[
            'id' => $transferId,
            'paymentAppId' => $appId,
            'transferNo' => $transferId,
            'accountId' => $accountId,
            'outNo' => substr($transferId, 0, 30),
            'recipientId' => $recipientId,
            'provider' => 'alipay',
            'channel' => 'alipay_h5',
            'amount' => 100,
            'currency' => 'CNY',
            'statusId' => $container->get(InitialStateIdLoader::class)->get(PaymentTransferStates::STATE_MACHINE),
            'submitAttempts' => 0,
        ]], $context);

        return $container->get(PaymentTransferService::class)->getById($transferId);
    }
}
