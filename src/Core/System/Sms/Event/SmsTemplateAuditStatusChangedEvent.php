<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Log\Package;

/**
 * Fired when the audit state of one template at one provider moved from one value to another.
 *
 * Only a transition is reported: the state of a pending template is read back again and again, and a
 * subscriber that wants to act on "the template became approved" must not be woken by a refresh that
 * read the same state a second time.
 *
 * It is the hook for everything that follows an approval - warming a cache, notifying an operator,
 * releasing messages that were held back - and carries the mapping row, the template and the
 * provider, so a subscriber needs no query of its own to find the row.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SmsTemplateAuditStatusChangedEvent implements ShopwellEvent
{
    public function __construct(
        private readonly string $mappingId,
        private readonly string $templateId,
        private readonly string $technicalName,
        private readonly ?string $gatewayId,
        private readonly string $previousStatus,
        private readonly string $status,
        private readonly ?string $providerTemplateCode,
        private readonly Context $context,
    ) {
    }

    /**
     * The mapping row the state belongs to, the row the administration shows as the audit ledger.
     */
    public function getMappingId(): string
    {
        return $this->mappingId;
    }

    public function getTemplateId(): string
    {
        return $this->templateId;
    }

    /**
     * The provider that audits the template, e.g. aliyun.
     */
    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    /**
     * The account the template was reported with, null when the row holds for every account of the
     * provider.
     */
    public function getGatewayId(): ?string
    {
        return $this->gatewayId;
    }

    /**
     * The state the row held before, one of the values of TemplateApprovalStatus.
     */
    public function getPreviousStatus(): string
    {
        return $this->previousStatus;
    }

    /**
     * The state the row holds now, one of the values of TemplateApprovalStatus.
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * The template id at the provider, e.g. SMS_123456789, null while nothing was reported.
     */
    public function getProviderTemplateCode(): ?string
    {
        return $this->providerTemplateCode;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
