<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Product\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Product\Event\PaymentProductsEvent;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Product\PaymentScene;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentProductsEvent::class)]
class PaymentProductsEventTest extends TestCase
{
    public function testAddsProductAndSceneWithoutMutatingOriginalDeclaration(): void
    {
        $product = new PaymentProduct('plugin_pay', 'plugin', 'plugin.pay', ['custom_intent'], 'custom_wallet');
        $scene = new PaymentScene('terminal', 'kiosk', 'plugin_shell');
        $event = new PaymentProductsEvent('plugin', []);
        $event->add($product);
        $event->addScene('plugin_pay', $scene);

        static::assertSame('plugin', $event->providerName);
        static::assertSame([], $product->scenes);
        static::assertSame([$scene], $event->products()[0]->scenes);
    }

    public function testCannotExtendAnUnknownProduct(): void
    {
        $this->expectExceptionObject(PaymentProviderException::unknownChannel('missing'));
        (new PaymentProductsEvent('plugin', []))->addScene('missing', new PaymentScene('custom', 'custom'));
    }
}
