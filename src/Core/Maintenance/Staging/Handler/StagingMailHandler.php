<?php declare(strict_types=1);

namespace Shopwell\Core\Maintenance\Staging\Handler;

use Shopwell\Core\Content\Mail\Service\MailSender;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Shopwell\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('framework')]
readonly class StagingMailHandler
{
    public function __construct(
        private SystemConfigService $systemConfigService
    ) {
    }

    public function __invoke(SetupStagingEvent $event): void
    {
        if (!$event->disableMailDelivery) {
            return;
        }

        $this->systemConfigService->set(MailSender::DISABLE_MAIL_DELIVERY, true, null, true);

        $event->io->info('Disabled mail delivery.');
    }
}
