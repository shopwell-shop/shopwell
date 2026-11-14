<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Transfer\Approval;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Codec\WorkflowDocumentCodec;
use Shopwell\Core\Framework\Workflow\Publication\Entity\WorkflowReleaseEntity;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalEntity;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalException;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalPolicy;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentTransferApprovalPolicy::class)]
class PaymentTransferApprovalPolicyTest extends TestCase
{
    private PaymentTransferApprovalPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new PaymentTransferApprovalPolicy(new WorkflowDocumentCodec(Validation::createValidator()));
    }

    public function testACompatibleReleaseRequiresAnApproval(): void
    {
        $this->policy->validate($this->release());
        $this->expectNotToPerformAssertions();
    }

    public function testAConditionBranchThatSkipsEveryApprovalIsRejected(): void
    {
        $release = $this->release();
        $document = json_decode($release->getDocument(), true, flags: \JSON_THROW_ON_ERROR);
        $document['nodes'][] = ['id' => 'condition', 'type' => 'core:condition', 'typeVersion' => 1, 'config' => new \stdClass()];
        $document['connections'][0]['target'] = 'condition';
        $document['connections'][] = ['id' => 'review', 'source' => 'condition', 'sourcePort' => 'branch-high', 'target' => 'approval', 'targetPort' => 'in'];
        $document['connections'][] = ['id' => 'bypass', 'source' => 'condition', 'sourcePort' => 'fallback', 'target' => 'end', 'targetPort' => 'in'];
        $release->setDocument(json_encode($document, \JSON_THROW_ON_ERROR));
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('approval_bypass'));
        $this->policy->validate($release);
    }

    public function testAnAmountFieldLimitedToTwoDecimalsIsRejected(): void
    {
        $release = $this->release();
        $release->setDocument(str_replace('"type":"number"', '"type":"amount"', $release->getDocument()));
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('incompatible_form'));
        $this->policy->validate($release);
    }

    public function testTheApprovedRecipientIsIndependentOfLaterRecipientEdits(): void
    {
        [$transfer, $recipient] = $this->entities();
        $approval = new PaymentTransferApprovalEntity();
        $approval->snapshot = $this->policy->snapshot($transfer, $recipient);
        $recipient->identityValue = 'different-encrypted-identity';
        $recipient->bankInfo = ['bank' => 'different-bank'];
        $frozen = $this->policy->recipient($approval, $transfer);
        static::assertSame('encrypted-identity', $frozen->identityValue);
        static::assertSame(['bank' => 'approved-bank'], $frozen->bankInfo);
        static::assertSame(1234, $approval->snapshot['amount']);
        static::assertSame('KWD', $approval->snapshot['currency']);
    }

    public function testChangingAnApprovedAmountInvalidatesTheBinding(): void
    {
        [$transfer, $recipient] = $this->entities();
        $approval = new PaymentTransferApprovalEntity();
        $approval->snapshot = $this->policy->snapshot($transfer, $recipient);
        ++$transfer->amount;
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('changed_transfer'));
        $this->policy->recipient($approval, $transfer);
    }

    public function testChangingTheApprovedReasonInvalidatesTheBinding(): void
    {
        [$transfer, $recipient] = $this->entities();
        $approval = new PaymentTransferApprovalEntity();
        $approval->snapshot = $this->policy->snapshot($transfer, $recipient);
        $transfer->metadata = ['note' => 'different reason'];
        $this->expectExceptionObject(PaymentTransferApprovalException::blocked('changed_transfer'));
        $this->policy->recipient($approval, $transfer);
    }

    private function release(): WorkflowReleaseEntity
    {
        $release = new WorkflowReleaseEntity();
        $release->setDocument(json_encode([
            'schemaVersion' => 1,
            'policy' => 'core:structured',
            'nodes' => [
                ['id' => 'start', 'type' => 'core:start', 'typeVersion' => 1, 'config' => ['formFields' => [
                    ['key' => 'amount', 'type' => 'number', 'label' => 'Amount', 'required' => true],
                    ['key' => 'currency', 'type' => 'text', 'label' => 'Currency', 'required' => true],
                    ['key' => 'recipient', 'type' => 'text', 'label' => 'Recipient', 'required' => true],
                    ['key' => 'paymentAccount', 'type' => 'text', 'label' => 'Account', 'required' => true],
                    ['key' => 'reason', 'type' => 'text', 'label' => 'Reason', 'required' => false],
                ]]],
                ['id' => 'approval', 'type' => 'core:approval', 'typeVersion' => 1, 'config' => new \stdClass()],
                ['id' => 'end', 'type' => 'core:end', 'typeVersion' => 1, 'config' => new \stdClass()],
            ],
            'connections' => [
                ['id' => 'a', 'source' => 'start', 'sourcePort' => 'out', 'target' => 'approval', 'targetPort' => 'in'],
                ['id' => 'b', 'source' => 'approval', 'sourcePort' => 'approved', 'target' => 'end', 'targetPort' => 'in'],
            ],
        ], \JSON_THROW_ON_ERROR));

        return $release;
    }

    /**
     * @return array{PaymentTransferEntity, PaymentTransferRecipientEntity}
     */
    private function entities(): array
    {
        $transfer = new PaymentTransferEntity();
        $transfer->assign(['paymentAppId' => 'app', 'accountId' => 'account', 'recipientId' => 'recipient', 'outNo' => 'TF1', 'provider' => 'provider', 'channel' => 'channel', 'amount' => 1234, 'currency' => 'KWD', 'metadata' => ['note' => 'approved reason']]);
        $recipient = new PaymentTransferRecipientEntity();
        $recipient->assign(['id' => 'recipient', 'paymentAppId' => 'app', 'provider' => 'provider', 'identityType' => 'bank', 'identityValue' => 'encrypted-identity', 'identityHash' => 'hash', 'bankInfo' => ['bank' => 'approved-bank'], 'onboardingStatus' => 'registered']);

        return [$transfer, $recipient];
    }
}
