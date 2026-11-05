<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\PaymentContextParser;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentContextParser::class)]
class PaymentContextParserTest extends TestCase
{
    public function testAuthenticatedAppCanDeclareCustomClientAndMerchantScopedIdentity(): void
    {
        $context = (new PaymentContextParser())->parse([
            'currency' => 'eur',
            'context' => [
                'scene' => ['name' => 'custom_terminal', 'device' => 'kiosk', 'environment' => 'merchant_shell'],
                'interactions' => ['terminal_intent'],
                'sdk_products' => ['plugin_terminal'],
                'account_runtime_params' => ['account-a' => ['plugin_terminal' => ['payer_id' => 'payer-a']]],
            ],
        ]);

        static::assertSame('EUR', $context->currency);
        static::assertSame('custom_terminal', $context->scene->name);
        static::assertSame('kiosk', $context->device);
        static::assertSame(['terminal_intent'], $context->supportedInteractions);
        static::assertSame(['account-a' => ['plugin_terminal' => ['payer_id' => 'payer-a']]], $context->accountRuntimeParams);
    }

    public function testEnvironmentIsOptionalAndSdkSupportIsNeverInferred(): void
    {
        $context = (new PaymentContextParser())->parse(['currency' => 'CNY', 'context' => ['scene' => ['name' => 'browser', 'device' => 'web']]]);

        static::assertNull($context->scene->environment);
        static::assertSame([], $context->supportedSdkProducts);
        static::assertSame([], $context->accountRuntimeParams);
    }

    public function testRequiresExplicitScene(): void
    {
        $this->expectExceptionObject(PaymentException::missingParameter('context.scene'));
        (new PaymentContextParser())->parse(['currency' => 'CNY', 'context' => []]);
    }

    public function testRejectsStructuredIdentityInsteadOfSilentlyDiscardingIt(): void
    {
        $this->expectExceptionObject(PaymentException::missingParameter('context.account_runtime_params'));
        (new PaymentContextParser())->parse(['currency' => 'CNY', 'context' => [
            'scene' => ['name' => 'browser', 'device' => 'web'],
            'account_runtime_params' => ['account-a' => ['plugin' => ['payer_id' => ['invalid']]]],
        ]]);
    }

    public function testRejectsAnInteractionObject(): void
    {
        $this->expectExceptionObject(PaymentException::missingParameter('context.interactions'));
        (new PaymentContextParser())->parse(['currency' => 'CNY', 'context' => [
            'scene' => ['name' => 'browser', 'device' => 'web'],
            'interactions' => ['redirect' => true],
        ]]);
    }
}
