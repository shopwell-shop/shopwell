<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Transfer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Event\NestedEventCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientCollection;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientService;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentTransferRecipientService::class)]
class PaymentTransferRecipientServiceTest extends TestCase
{
    private const SECRET = 'unit-test-application-secret';

    private const NOW = '2026-10-18T09:00:00+00:00';

    /**
     * `test` is not a ciphertext the platform could ever produce - a decrypt of it must fail, which is
     * exactly the intake guard of the transfer service.
     */
    private const IDENTITY = 'someone@example.com';

    public function testCreateOrGetCreatesTheRecipientOnFirstUse(): void
    {
        $created = $this->recipient(['identityValue' => $this->encrypted(self::IDENTITY)]);

        $written = [];
        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls($this->searchResult([]), $this->searchResult([$created]));
        $repository->expects($this->once())
            ->method('create')
            ->with(static::callback(function (array $data) use (&$written): bool {
                $written = $data[0];

                return true;
            }))
            ->willReturn($this->written());

        $result = $this->service($repository)->createOrGet($this->app(), 'alipay', 'alipay_logon_id', self::IDENTITY, '张三', null);

        static::assertSame($created, $result);
        static::assertSame('app-id', $written['paymentAppId']);
        static::assertSame('alipay', $written['provider']);
        static::assertSame('alipay_logon_id', $written['identityType']);
        static::assertSame('张三', $written['holderName']);
        static::assertNull($written['bankInfo']);
    }

    /**
     * The plain text is a credential: the column holds ciphertext that can be decrypted back, and the
     * unique key spans the deterministic hash of the plain text instead.
     */
    public function testTheIdentityIsStoredEncryptedAndHashed(): void
    {
        $written = $this->createCaptured('paypal', 'paypal_email', self::IDENTITY, null, null);

        static::assertNotSame(self::IDENTITY, $written['identityValue']);
        static::assertStringNotContainsString(self::IDENTITY, $written['identityValue']);
        static::assertSame(self::IDENTITY, $this->cipher()->decryptValue($written['identityValue']));

        static::assertSame(
            hash('sha256', implode('|', ['app-id', 'paypal', 'paypal_email', self::IDENTITY])),
            $written['identityHash']
        );
    }

    /**
     * The hash is what the lookup is made by - the ciphertext of the same plain text differs on every
     * write, so looking the value up would never find the row that was written before.
     */
    public function testTheSameIdentityIsLookedUpByTheSameHash(): void
    {
        $hashes = [];
        /** @var array<string, mixed> $written */
        $written = [];
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturnCallback(function (Criteria $criteria) use (&$hashes, &$written) {
            foreach ($criteria->getFilters() as $filter) {
                if ($filter instanceof EqualsFilter && $filter->getField() === 'identityHash') {
                    $hashes[] = $filter->getValue();
                }
            }

            return $this->searchResult($written === [] ? [] : [$this->recipient()]);
        });
        $repository->method('create')->willReturnCallback(function (array $data) use (&$written): EntityWrittenContainerEvent {
            $written = $data[0];

            return $this->written();
        });

        $service = $this->service($repository);
        $service->createOrGet($this->app(), 'alipay', 'alipay_user_id', '2088000000000000', null, null);
        $service->createOrGet($this->app(), 'alipay', 'alipay_user_id', '2088000000000000', null, null);

        static::assertCount(2, $hashes);
        static::assertSame($hashes[0], $hashes[1]);
        static::assertSame(hash('sha256', 'app-id|alipay|alipay_user_id|2088000000000000'), $hashes[0]);
    }

    public function testAnExistingRecipientIsReturnedWithoutWriting(): void
    {
        $existing = $this->recipient();

        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->searchResult([$existing]));
        $repository->expects($this->never())->method('create');

        static::assertSame(
            $existing,
            $this->service($repository)->createOrGet($this->app(), 'alipay', 'alipay_user_id', '2088000000000000', null, null)
        );
    }

    /**
     * The two interfaces that onboard their recipients - stripe has to wait for the webhook, the other
     * three pay out to any identity they accepted right away. The readiness is written explicitly.
     */
    #[DataProvider('readiness')]
    public function testThePayoutReadinessFollowsTheInterface(string $provider, string $identityType, bool $expectedPayout, string $expectedOnboarding): void
    {
        $written = $this->createCaptured($provider, $identityType, 'identity-value', 'name', null);

        static::assertSame($expectedPayout, $written['payoutEnabled']);
        static::assertSame($expectedOnboarding, $written['onboardingStatus']);
    }

    /**
     * @return iterable<string, array{string, string, bool, string}>
     */
    public static function readiness(): iterable
    {
        yield 'stripe waits for account.updated' => ['stripe', 'stripe_account', false, PaymentTransferStates::ONBOARDING_PENDING];
        yield 'alipay pays out immediately' => ['alipay', 'alipay_user_id', true, PaymentTransferStates::ONBOARDING_REGISTERED];
        yield 'wechat pays out immediately' => ['wechat', 'wechat_open_id', true, PaymentTransferStates::ONBOARDING_REGISTERED];
        yield 'paypal pays out immediately' => ['paypal', 'paypal_email', true, PaymentTransferStates::ONBOARDING_REGISTERED];
    }

    /**
     * The identity matrix is the disagreement of the interfaces written down: wechat only knows the
     * openid of the very app, stripe only a connected account, and alipay has no paypal email.
     */
    #[DataProvider('supportedIdentities')]
    public function testEverySupportedIdentityIsAccepted(string $provider, string $identityType): void
    {
        $written = $this->createCaptured($provider, $identityType, 'identity-value', 'name', null);

        static::assertSame($identityType, $written['identityType']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function supportedIdentities(): iterable
    {
        yield 'alipay uid' => ['alipay', 'alipay_user_id'];
        yield 'alipay logon id' => ['alipay', 'alipay_logon_id'];
        yield 'alipay open id' => ['alipay', 'alipay_open_id'];
        yield 'wechat open id' => ['wechat', 'wechat_open_id'];
        yield 'stripe account' => ['stripe', 'stripe_account'];
        yield 'paypal email' => ['paypal', 'paypal_email'];
        yield 'paypal phone' => ['paypal', 'paypal_phone'];
        yield 'paypal payer id' => ['paypal', 'paypal_payer_id'];
    }

    #[DataProvider('unsupportedIdentities')]
    public function testAnUnsupportedIdentityIsRefused(string $provider, string $identityType): void
    {
        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->never())->method('create');

        try {
            $this->service($repository)->createOrGet($this->app(), $provider, $identityType, 'identity-value', 'name', null);
            static::fail('an identity the interface cannot pay out to must be refused');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_IDENTITY_NOT_SUPPORTED, $exception->getErrorCode());
            static::assertSame($provider, $exception->getParameter('provider'));
            static::assertSame($identityType, $exception->getParameter('identityType'));
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsupportedIdentities(): iterable
    {
        yield 'wechat has no logon id transfer' => ['wechat', 'alipay_logon_id'];
        yield 'alipay has no paypal email' => ['alipay', 'paypal_email'];
        yield 'stripe pays out to an account only' => ['stripe', 'paypal_email'];
        yield 'paypal has no connected account' => ['paypal', 'stripe_account'];
        yield 'an unknown interface pays out to nothing' => ['unknown', 'alipay_user_id'];
    }

    /**
     * Alipay demands the name of the holder for a logon id, and the name plus the bank details for a
     * card - the interface refuses the request otherwise.
     */
    /**
     * @param array<string, mixed>|null $bankInfo
     */
    #[DataProvider('incompleteRecipients')]
    public function testAnIncompleteRecipientIsRefused(string $identityType, ?string $holderName, ?array $bankInfo, string $missingField): void
    {
        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->never())->method('create');

        try {
            $this->service($repository)->createOrGet($this->app(), 'alipay', $identityType, 'identity-value', $holderName, $bankInfo);
            static::fail('a recipient the interface demands more fields for must be refused');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_RECIPIENT_INCOMPLETE, $exception->getErrorCode());
            static::assertSame($missingField, $exception->getParameter('field'));
        }
    }

    /**
     * @return iterable<string, array{string, string|null, array<string, mixed>|null, string}>
     */
    public static function incompleteRecipients(): iterable
    {
        yield 'logon id without a holder name' => ['alipay_logon_id', null, null, 'holderName'];
        yield 'card without a holder name' => ['bankcard', null, ['account_type' => '2'], 'holderName'];
        yield 'card without bank details' => ['bankcard', '张三', null, 'bankInfo'];
        yield 'card with an unknown account type' => ['bankcard', '张三', ['account_type' => '3'], 'bankInfo.account_type'];
        yield 'card without an account type' => ['bankcard', '张三', ['inst_name' => '招商银行'], 'bankInfo.account_type'];
    }

    public function testAnEmptyIdentityIsRefused(): void
    {
        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->never())->method('create');

        try {
            $this->service($repository)->createOrGet($this->app(), 'alipay', 'alipay_user_id', '   ', null, null);
            static::fail('an empty identity has to be refused');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_RECIPIENT_INCOMPLETE, $exception->getErrorCode());
            static::assertSame('identityValue', $exception->getParameter('field'));
        }
    }

    /**
     * 149 bytes is the longest plain text whose ciphertext still fits into `VARCHAR(255)`:
     * `base64_encode(24 byte nonce + plain + 16 byte tag)` is `4 * ceil((n + 40) / 3)` characters,
     * and 149 is the largest `n` with `4 * ceil((n + 40) / 3) <= 255`. Every real identity is ASCII,
     * so bytes and characters coincide here.
     */
    public function testTheLongestIdentityThatFitsTheEncryptedColumnIsAccepted(): void
    {
        $identity = str_repeat('a', 149);

        $written = $this->createCaptured('paypal', 'paypal_email', $identity, null, null);

        static::assertSame($identity, $this->cipher()->decryptValue($written['identityValue']));
        static::assertLessThanOrEqual(255, \strlen($written['identityValue']));
    }

    public function testAnIdentityWhoseCiphertextWouldOverflowTheColumnIsRefused(): void
    {
        $identity = str_repeat('a', 150);

        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->never())->method('create');
        $repository->expects($this->never())->method('search');

        try {
            $this->service($repository)->createOrGet($this->app(), 'paypal', 'paypal_email', $identity, null, null);
            static::fail('an identity that cannot be stored encrypted has to be refused before it is written');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_IDENTITY_VALUE_TOO_LONG, $exception->getErrorCode());
            static::assertSame(150, $exception->getParameter('length'));
        }
    }

    /**
     * The cipher and the column measure bytes, not characters: 50 chinese characters are 150 bytes
     * and would overflow the column although the string is far below the character limit.
     */
    public function testAMultibyteIdentityIsMeasuredInBytes(): void
    {
        $identity = str_repeat('打', 50);
        static::assertSame(150, \strlen($identity));

        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->never())->method('create');

        try {
            $this->service($repository)->createOrGet($this->app(), 'paypal', 'paypal_email', $identity, null, null);
            static::fail('a multibyte identity that cannot be stored encrypted has to be refused');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_IDENTITY_VALUE_TOO_LONG, $exception->getErrorCode());
            static::assertSame(150, $exception->getParameter('length'));
        }
    }

    public function testACardIsAcceptedWithTheBankDetails(): void
    {
        $written = $this->createCaptured('alipay', 'bankcard', '6222020000000000000', '张三', ['account_type' => 2, 'inst_name' => '招商银行']);

        static::assertSame(['account_type' => 2, 'inst_name' => '招商银行'], $written['bankInfo']);
        static::assertSame('6222020000000000000', $this->cipher()->decryptValue($written['identityValue']));
        // a card number is the one identity that is never allowed to reach the database in plain text
        static::assertStringNotContainsString('6222020000000000000', $written['identityValue']);
    }

    /**
     * Wechat demands the name of the holder from 2000 yuan on - the only amount-dependent requirement
     * of the four interfaces, and a property of the recipient rather than of the transfer.
     */
    public function testWechatDemandsTheHolderNameFrom2000YuanOn(): void
    {
        $recipient = $this->recipient(['provider' => 'wechat', 'identityType' => 'wechat_open_id', 'holderName' => null]);

        try {
            $this->service(static::createStub(EntityRepository::class))->assertPayoutReady($recipient, 200000);
            static::fail('the missing holder name has to be refused');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_RECIPIENT_INCOMPLETE, $exception->getErrorCode());
            static::assertSame('holderName', $exception->getParameter('field'));
        }
    }

    public function testWechatBelowTheThresholdNeedsNoHolderName(): void
    {
        $this->expectNotToPerformAssertions();

        $recipient = $this->recipient(['provider' => 'wechat', 'identityType' => 'wechat_open_id', 'holderName' => null]);

        $this->service(static::createStub(EntityRepository::class))->assertPayoutReady($recipient, 199999);
    }

    public function testAlipayHasNoAmountDependentRequirement(): void
    {
        $this->expectNotToPerformAssertions();

        $recipient = $this->recipient(['provider' => 'alipay', 'holderName' => null]);

        $this->service(static::createStub(EntityRepository::class))->assertPayoutReady($recipient, 200000);
    }

    public function testMarkStripeAccountReadyMirrorsTheAccount(): void
    {
        $recipient = $this->recipient(['provider' => 'stripe', 'identityType' => 'stripe_account']);

        $written = [];
        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->searchResult([$recipient]));
        $repository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data) use (&$written): bool {
                $written = $data[0];

                return true;
            }));

        $this->service($repository)->markStripeAccountReady('recipient-id', 'acct_1A2B3C', true);

        static::assertSame('acct_1A2B3C', $written['providerAccountId']);
        static::assertTrue($written['payoutEnabled']);
        static::assertSame(PaymentTransferStates::ONBOARDING_REGISTERED, $written['onboardingStatus']);
        $verifiedAt = $written['verifiedAt'];
        static::assertInstanceOf(\DateTimeImmutable::class, $verifiedAt);
        static::assertSame(self::NOW, $verifiedAt->format(\DATE_ATOM));
    }

    /**
     * The interface can disable payouts again - the mirror has to say so, and a recipient that is not
     * payable has not been verified either.
     */
    public function testMarkStripeAccountReadyMirrorsADisabledAccount(): void
    {
        $recipient = $this->recipient(['provider' => 'stripe', 'identityType' => 'stripe_account']);

        $written = [];
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($this->searchResult([$recipient]));
        $repository->method('update')->willReturnCallback(function (array $data) use (&$written): EntityWrittenContainerEvent {
            $written = $data[0];

            return $this->written();
        });

        $this->service($repository)->markStripeAccountReady('recipient-id', 'acct_1A2B3C', false);

        static::assertFalse($written['payoutEnabled']);
        static::assertSame(PaymentTransferStates::ONBOARDING_PENDING, $written['onboardingStatus']);
        static::assertNull($written['verifiedAt']);
    }

    public function testAnUnknownRecipientIsReported(): void
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($this->searchResult([]));

        try {
            $this->service($repository)->markStripeAccountReady('recipient-id', 'acct_1A2B3C', true);
            static::fail('an unknown recipient has to be reported');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::TRANSFER_RECIPIENT_NOT_FOUND, $exception->getErrorCode());
        }
    }

    /**
     * @param array<string, mixed>|null $bankInfo
     *
     * @return array<string, mixed> the row the service handed to the repository
     */
    private function createCaptured(string $provider, string $identityType, string $identityValue, ?string $holderName, ?array $bankInfo): array
    {
        /** @var array<string, mixed> $written */
        $written = [];
        $repository = static::createStub(EntityRepository::class);
        // the service re-reads the row it wrote - before the write there is nothing to find
        $repository->method('search')->willReturnCallback(
            function () use (&$written): EntitySearchResult {
                return $this->searchResult($written === [] ? [] : [$this->recipient()]);
            }
        );
        $repository->method('create')->willReturnCallback(function (array $data) use (&$written): EntityWrittenContainerEvent {
            $written = $data[0];

            return $this->written();
        });

        $this->service($repository)->createOrGet($this->app(), $provider, $identityType, $identityValue, $holderName, $bankInfo);

        return $written;
    }

    private function written(): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []);
    }

    /**
     * @param EntityRepository<PaymentTransferRecipientCollection> $repository
     */
    private function service(EntityRepository $repository): PaymentTransferRecipientService
    {
        return new PaymentTransferRecipientService($repository, $this->cipher(), new MockClock(self::NOW));
    }

    private function cipher(): PaymentCredentialCipher
    {
        return new PaymentCredentialCipher(self::SECRET);
    }

    private function encrypted(string $plain): string
    {
        return $this->cipher()->encryptValue($plain);
    }

    private function app(): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->id = 'app-id';

        return $app;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function recipient(array $overrides = []): PaymentTransferRecipientEntity
    {
        $recipient = new PaymentTransferRecipientEntity();
        $recipient->id = 'recipient-id';
        $recipient->paymentAppId = 'app-id';
        $recipient->provider = 'alipay';
        $recipient->identityType = 'alipay_user_id';
        $recipient->identityValue = $this->encrypted('2088000000000000');
        $recipient->identityHash = hash('sha256', 'app-id|alipay|alipay_user_id|2088000000000000');
        $recipient->onboardingStatus = PaymentTransferStates::ONBOARDING_REGISTERED;
        $recipient->payoutEnabled = true;
        $recipient->assign($overrides);

        $recipient->setUniqueIdentifier($recipient->id);

        return $recipient;
    }

    /**
     * @param list<PaymentTransferRecipientEntity> $recipients
     *
     * @return EntitySearchResult<PaymentTransferRecipientCollection>
     */
    private function searchResult(array $recipients): EntitySearchResult
    {
        return new EntitySearchResult(
            PaymentTransferRecipientEntity::ENTITY_NAME,
            \count($recipients),
            new PaymentTransferRecipientCollection($recipients),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}
