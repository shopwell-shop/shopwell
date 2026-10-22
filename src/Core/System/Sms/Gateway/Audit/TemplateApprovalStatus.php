<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway\Audit;

use Shopwell\Core\Framework\Log\Package;

/**
 * The audit state of a template of a provider, the single vocabulary of the module.
 *
 * The states of the providers differ - one has an "approved but not effective yet", the other has
 * not - so every implementation maps its own codes onto these five, and a code it does not know
 * maps to UNKNOWN. Only APPROVED may be sent: an UNKNOWN is a state the shop cannot judge, not a
 * permission.
 */
#[Package('framework')]
final class TemplateApprovalStatus
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const SUSPENDED = 'suspended';
    public const UNKNOWN = 'unknown';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [self::PENDING, self::APPROVED, self::REJECTED, self::SUSPENDED, self::UNKNOWN];
    }

    /**
     * The snippet key of the label the administration shows, so the five states are translated in
     * one place and the frontend never keeps a list of its own.
     */
    public static function labelSnippet(string $status): string
    {
        return 'sms.approval.status.' . (\in_array($status, self::values(), true) ? $status : self::UNKNOWN);
    }

    /**
     * Only an approved template may be sent, everything else - including a state the shop does not
     * know - blocks the gateway.
     */
    public static function isSendable(string $status): bool
    {
        return $status === self::APPROVED;
    }
}
