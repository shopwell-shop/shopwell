<?php declare(strict_types=1);

namespace Shopwell\Core\Service\MessageHandler;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Service\LifecycleManager;
use Shopwell\Core\Service\Message\InstallServicesMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler]
final readonly class InstallServicesHandler
{
    public function __construct(private LifecycleManager $manager)
    {
    }

    public function __invoke(InstallServicesMessage $installServicesMessage): void
    {
        $this->manager->reconcile(Context::createDefaultContext());
    }
}
