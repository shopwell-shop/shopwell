<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Transfer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Provider\Transfer\PaymentTransferRules;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentTransferRules::class)]
class PaymentTransferRulesTest extends TestCase
{
    public function testAnEmptyDeclarationCannotPayOutToAnything(): void
    {
        $rules = new PaymentTransferRules(identityTypes: []);

        static::assertFalse($rules->supportsIdentityType('alipay_user_id'));
        static::assertSame([], $rules->requiredFieldsFor('alipay_user_id'));
        static::assertFalse($rules->requiresHolderNameFor(1));
        static::assertFalse($rules->supportsBankAccountType('1'));
        static::assertNull($rules->confirmWindow);
        static::assertFalse($rules->requiresOnboarding);
    }

    public function testKnowsWhichIdentitiesItCanPayOutTo(): void
    {
        $rules = new PaymentTransferRules(identityTypes: ['wechat_open_id']);

        static::assertTrue($rules->supportsIdentityType('wechat_open_id'));
        static::assertFalse($rules->supportsIdentityType('wechat_openid'));
        static::assertFalse($rules->supportsIdentityType('bankcard'));
    }

    public function testAnEmptyCurrencyListMeansTheMerchantAccountDecides(): void
    {
        static::assertTrue((new PaymentTransferRules(identityTypes: ['stripe_account']))->supportsCurrency('EUR'));
        static::assertTrue((new PaymentTransferRules(identityTypes: ['stripe_account']))->supportsCurrency('jpy'));
    }

    public function testACurrencyListRestrictsThePayoutToIt(): void
    {
        $rules = new PaymentTransferRules(identityTypes: ['alipay_user_id'], currencies: ['CNY']);

        static::assertTrue($rules->supportsCurrency('CNY'));
        static::assertTrue($rules->supportsCurrency('cny'), 'The comparison is case insensitive.');
        static::assertFalse($rules->supportsCurrency('USD'));
    }

    public function testTheRequiredRecipientFieldsDependOnTheIdentityType(): void
    {
        $rules = new PaymentTransferRules(
            identityTypes: ['alipay_user_id', 'bankcard'],
            requiredRecipientFields: [
                'bankcard' => [PaymentTransferRules::FIELD_HOLDER_NAME, PaymentTransferRules::FIELD_BANK_INFO],
            ],
        );

        static::assertSame([], $rules->requiredFieldsFor('alipay_user_id'));
        static::assertSame(['holderName', 'bankInfo'], $rules->requiredFieldsFor('bankcard'));
        static::assertSame([], $rules->requiredFieldsFor('unknown'));
    }

    public function testTheHolderNameThresholdIsInclusive(): void
    {
        $rules = new PaymentTransferRules(identityTypes: ['wechat_open_id'], holderNameThreshold: 200000);

        static::assertFalse($rules->requiresHolderNameFor(199999));
        static::assertTrue($rules->requiresHolderNameFor(200000));
        static::assertTrue($rules->requiresHolderNameFor(200001));
    }

    public function testTheBankAccountTypesAreAnExplicitAllowList(): void
    {
        $rules = new PaymentTransferRules(identityTypes: ['bankcard'], bankAccountTypes: ['1', '2']);

        static::assertTrue($rules->supportsBankAccountType('1'));
        static::assertTrue($rules->supportsBankAccountType('2'));
        static::assertFalse($rules->supportsBankAccountType('3'));
    }
}
