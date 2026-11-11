<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
class PaymentIntegrationOwnershipTest extends TestCase
{
    public function testCoreRegistersOnlyItsOwnPaymentIntegrations(): void
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../../../../../src/Core/System/DependencyInjection'));
        $loader->load('payment.php');

        $providers = [];
        foreach ($container->findTaggedServiceIds('shopwell.payment.provider') as $tags) {
            foreach ($tags as $tag) {
                $providers[] = $tag['key'];
            }
        }
        sort($providers);
        static::assertSame(['alipay', 'wechat'], $providers);
        static::assertFalse($container->hasDefinition('shopwell.payment.method.paypal'));
        static::assertFalse($container->hasDefinition('shopwell.payment.method.stripe'));
    }
}
