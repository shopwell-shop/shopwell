<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigSanitizer;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentConfigSanitizer::class)]
class PaymentConfigSanitizerTest extends TestCase
{
    public function testSecretsAreHiddenAndOtherValuesAreLeftUntouched(): void
    {
        $values = PaymentConfigSanitizer::values(
            [
                new PaymentConfigField('app_id'),
                new PaymentConfigField('private_key', secret: true),
            ],
            [
                'app_id' => 'platform-app',
                'private_key' => 'sk',
                'extension_value' => 'kept',
            ],
        );

        static::assertSame([
            'app_id' => 'platform-app',
            'private_key' => null,
            'extension_value' => 'kept',
        ], $values);
    }

    public function testAMissingOrAlreadyEmptySecretStaysWithoutAValue(): void
    {
        $fields = [new PaymentConfigField('private_key', secret: true)];

        static::assertSame([], PaymentConfigSanitizer::values($fields, []));
        static::assertSame(['private_key' => null], PaymentConfigSanitizer::values($fields, ['private_key' => null]));
    }
}
