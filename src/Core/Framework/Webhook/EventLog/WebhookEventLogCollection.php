<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Webhook\EventLog;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<WebhookEventLogEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class WebhookEventLogCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return WebhookEventLogEntity::class;
    }
}
