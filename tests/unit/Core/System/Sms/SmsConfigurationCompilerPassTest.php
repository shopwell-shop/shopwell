<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\SmsConfigurationCompilerPass;
use Shopwell\Core\System\Sms\Transport\SmsTransportLoader;
use Shopwell\Core\System\Sms\Transport\SmsTransports;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Notifier\Transport\Transports;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsConfigurationCompilerPass::class)]
class SmsConfigurationCompilerPassTest extends TestCase
{
    public function testItReplacesTheClassAndTheFactoryOfTheTexterTransports(): void
    {
        $container = new ContainerBuilder();
        $container->register('texter.transports', Transports::class)
            ->setFactory([new Reference('texter.transport_factory'), 'fromStrings'])
            ->setArguments([[]]);

        (new SmsConfigurationCompilerPass())->process($container);

        $definition = $container->getDefinition('texter.transports');

        // the class has to be swapped too, the transports of the notifier are built eagerly
        static::assertSame(SmsTransports::class, $definition->getClass());
        static::assertEquals([new Reference(SmsTransportLoader::class), 'fromConfig'], $definition->getFactory());
    }

    public function testItLeavesTheContainerAloneWhenTheNotifierIsNotEnabled(): void
    {
        $container = new ContainerBuilder();

        (new SmsConfigurationCompilerPass())->process($container);

        static::assertFalse($container->hasDefinition('texter.transports'));
    }
}
