<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Transfer\Api\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Transfer\Api\Struct\PaymentTransferDraft;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

/**
 * The payload of the "new transfer request" modal. It is the only place where an operator hand typed
 * value enters the payout domain, so the leniency it grants has to be exactly as wide as intended: a
 * numeric string is fine, a decimal amount is not.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentTransferDraft::class)]
class PaymentTransferDraftTest extends TestCase
{
    public function testAnEmptyPayloadDoesNotThrow(): void
    {
        // the missing fields are the business of the constraints, not of the constructor: throwing here
        // would answer a validation problem with a 500
        $draft = PaymentTransferDraft::fromArray([]);

        static::assertNull($draft->paymentAppId);
        static::assertNull($draft->provider);
        static::assertNull($draft->channel);
        static::assertNull($draft->amount);
        static::assertNull($draft->currency);
        static::assertFalse($draft->usesExistingRecipient());
    }

    /**
     * A body built by hand sends the amount as a string. Accepting it is idempotent with
     * `PaymentTransferRequest::fromArray()`, which has always been that lenient.
     */
    public function testANumericStringAmountBecomesAnInteger(): void
    {
        static::assertSame(10, PaymentTransferDraft::fromArray(['amount' => '10'])->amount);
        static::assertSame(10, PaymentTransferDraft::fromArray(['amount' => 10])->amount);
        static::assertSame(10, PaymentTransferDraft::fromArray(['amount' => ' 10 '])->amount);
    }

    /**
     * A decimal amount is refused instead of being truncated: `10.5` yuan is 1050 fen, and rounding it to
     * 10 fen here would book a payout of a different size than the operator typed.
     */
    public function testADecimalAmountIsNotCoerced(): void
    {
        static::assertNull(PaymentTransferDraft::fromArray(['amount' => '10.5'])->amount);
        static::assertNull(PaymentTransferDraft::fromArray(['amount' => '1.005'])->amount);
    }

    public function testANegativeOrNonNumericAmountIsNotCoerced(): void
    {
        static::assertNull(PaymentTransferDraft::fromArray(['amount' => '-1'])->amount);
        static::assertNull(PaymentTransferDraft::fromArray(['amount' => 'ten'])->amount);
        static::assertNull(PaymentTransferDraft::fromArray(['amount' => [10]])->amount);
    }

    public function testACurrencyIsNormalisedToUpperCase(): void
    {
        static::assertSame('CNY', PaymentTransferDraft::fromArray(['currency' => 'cny'])->currency);
        static::assertSame('EUR', PaymentTransferDraft::fromArray(['currency' => ' eur '])->currency);
    }

    public function testBlankStringsBecomeNull(): void
    {
        $draft = PaymentTransferDraft::fromArray([
            'paymentAppId' => '  ',
            'provider' => '',
            'channel' => null,
            'outNo' => '   ',
            'note' => '   ',
        ]);

        static::assertNull($draft->paymentAppId);
        static::assertNull($draft->provider);
        static::assertNull($draft->channel);
        static::assertNull($draft->outNo);
        static::assertNull($draft->note);
    }

    public function testTheNoteAndTheHolderNameAreTrimmedButTheIdentityIsKept(): void
    {
        $draft = PaymentTransferDraft::fromArray([
            'note' => '  September settlement  ',
            'holderName' => '  Zhang San  ',
            'identityValue' => ' 2088000000000000 ',
        ]);

        static::assertSame('September settlement', $draft->note);
        static::assertSame('Zhang San', $draft->holderName);
        static::assertSame('2088000000000000', $draft->identityValue);
    }

    public function testTheBankInfoIsKeptAsItIs(): void
    {
        $bankInfo = ['account_type' => '1', 'bank_code' => 'ICBC'];

        static::assertSame($bankInfo, PaymentTransferDraft::fromArray(['bankInfo' => $bankInfo])->bankInfo);
        static::assertNull(PaymentTransferDraft::fromArray(['bankInfo' => 'ICBC'])->bankInfo);
    }

    public function testAnExistingRecipientIsRecognised(): void
    {
        static::assertTrue(PaymentTransferDraft::fromArray(['recipientId' => 'recipient-id'])->usesExistingRecipient());
        static::assertFalse(PaymentTransferDraft::fromArray(['identityType' => 'alipay_user_id'])->usesExistingRecipient());
    }

    public function testAnEmptyPayloadViolatesEveryMandatoryField(): void
    {
        $paths = $this->violationPaths(PaymentTransferDraft::fromArray([]));

        static::assertContains('paymentAppId', $paths);
        static::assertContains('provider', $paths);
        static::assertContains('channel', $paths);
        static::assertContains('amount', $paths);
        static::assertContains('currency', $paths);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidPayloads')]
    public function testInvalidValuesAreRejected(array $payload, string $expectedPath): void
    {
        static::assertContains($expectedPath, $this->violationPaths(PaymentTransferDraft::fromArray($payload)));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'amount is zero' => [['amount' => 0], 'amount'];
        yield 'amount is negative' => [['amount' => -5], 'amount'];
        yield 'amount is above the ceiling' => [['amount' => PaymentTransferDraft::MAX_AMOUNT + 1], 'amount'];
        yield 'amount is missing' => [[], 'amount'];
        yield 'currency is too short' => [['currency' => 'CN'], 'currency'];
        yield 'currency carries digits' => [['currency' => 'C1Y'], 'currency'];
        yield 'outNo carries a dash' => [['outNo' => 'TF-1'], 'outNo'];
        yield 'outNo is longer than 30 characters' => [['outNo' => str_repeat('a', 31)], 'outNo'];
        yield 'note is longer than 255 characters' => [['note' => str_repeat('n', 256)], 'note'];
        yield 'holderName is longer than 255 characters' => [['holderName' => str_repeat('h', 256)], 'holderName'];
    }

    /**
     * The known good shape of the modal has to pass every constraint - including the lower case currency,
     * which is normalised before it is checked.
     */
    public function testAValidPayloadHasNoViolations(): void
    {
        $draft = PaymentTransferDraft::fromArray([
            'paymentAppId' => '0192f0c4b1e07a7d8f00000000000001',
            'provider' => 'alipay',
            'channel' => 'alipay_transfer',
            'identityType' => 'alipay_logon_id',
            'identityValue' => 'zhangsan@example.com',
            'holderName' => 'Zhang San',
            'amount' => 10,
            'currency' => 'cny',
            'outNo' => 'TF20261021A1B2C3D4',
            'note' => 'September settlement',
        ]);

        static::assertSame([], $this->violationPaths($draft));
    }

    public function testTheAmountCeilingIsValid(): void
    {
        $draft = PaymentTransferDraft::fromArray([
            'paymentAppId' => '0192f0c4b1e07a7d8f00000000000001',
            'provider' => 'alipay',
            'channel' => 'alipay_transfer',
            'amount' => PaymentTransferDraft::MAX_AMOUNT,
            'currency' => 'CNY',
        ]);

        static::assertSame([], $this->violationPaths($draft));
    }

    /**
     * @return list<string>
     */
    private function violationPaths(PaymentTransferDraft $draft): array
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $violations = $validator->validate($draft);

        return $this->paths($violations);
    }

    /**
     * @return list<string>
     */
    private function paths(ConstraintViolationListInterface $violations): array
    {
        $paths = [];

        foreach ($violations as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        return $paths;
    }
}
