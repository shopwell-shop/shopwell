<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Method;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Method\PaymentMethod;
use Shopwell\Core\System\Payment\Method\PaymentMethodRegistry;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentMethodRegistry::class)]
class PaymentMethodRegistryTest extends TestCase
{
    public function testRegistersCustomMethodWithTranslationKey(): void
    {
        $method = new PaymentMethod('custom_wallet', 'plugin.payment.wallet');
        $registry = new PaymentMethodRegistry([$method]);

        static::assertSame($method, $registry->get('custom_wallet'));
        static::assertSame('plugin.payment.wallet', $registry->get('custom_wallet')->translationKey);
        static::assertSame(['custom_wallet' => $method], $registry->all());
    }

    public function testRejectsDuplicateNames(): void
    {
        $this->expectException(PaymentProviderException::class);
        new PaymentMethodRegistry([new PaymentMethod('wallet', 'one'), new PaymentMethod('wallet', 'two')]);
    }

    public function testRejectsEmptyName(): void
    {
        $this->expectException(PaymentProviderException::class);
        new PaymentMethodRegistry([new PaymentMethod('', 'one')]);
    }

    public function testUnknownMethodDoesNotBecomeAProviderName(): void
    {
        $this->expectExceptionObject(PaymentProviderException::unknownMethod('unknown'));
        (new PaymentMethodRegistry([]))->get('unknown');
    }
}
