<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sms\Transport;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Lets the transports of the text channel come from the sms_gateway rows instead of the static dsns
 * of framework.notifier.texter_transports.
 *
 * It is the counterpart of the MailerConfigurationCompilerPass of the platform, which replaces the
 * transports of mailer with the ones built from the mailer settings.
 *
 * Everything else of the notifier stays intact: the Texter, the SmsChannel, the messenger handler and
 * the events keep working, they simply talk to transports that were built from the database.
 *
 * @internal
 */
#[Package('framework')]
class SmsConfigurationCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('texter.transports')) {
            // the notifier is not enabled, nothing to replace
            return;
        }

        $definition = $container->getDefinition('texter.transports');
        // the class is part of the swap: the transports this component builds are a lazy collection of
        // its own, the notifier ones would have to be built eagerly from dsns
        $definition->setClass(SmsTransports::class);
        $definition->setFactory([
            new Reference(SmsTransportLoader::class),
            'fromConfig',
        ]);
    }
}
