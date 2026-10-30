<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Webhook\Authorization\Ownership;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Webhook\Service\WebhookLoader;
use Shopwell\Core\Framework\Webhook\WebhookException;

/**
 * @internal
 */
#[Package('framework')]
class WriteAuthorizer
{
    public function __construct(private readonly WebhookLoader $webhookLoader)
    {
    }

    /**
     * @param list<string> $webhookIds
     *
     * @return list<WebhookException>
     */
    public function getModificationViolations(array $webhookIds, Context $context): array
    {
        if ($context->getScope() === Context::SYSTEM_SCOPE) {
            return [];
        }

        $violations = [];

        foreach ($this->webhookLoader->getOwnership($webhookIds) as $ownership) {
            if ($ownership->belongsToApp()) {
                $violations[] = WebhookException::appWebhookNotModifiable($ownership->webhookId);
            }
        }

        return $violations;
    }
}
