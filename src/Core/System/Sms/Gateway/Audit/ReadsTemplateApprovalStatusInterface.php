<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway\Audit;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\SmsException;

/**
 * Declares that the provider reports the audit state of a template, so the shop can keep its own
 * record in sync without an operator reading the console.
 *
 * A provider that submits templates but cannot be asked for their state simply does not implement
 * this interface; the state of such a row stays maintainable by hand.
 */
#[Package('framework')]
interface ReadsTemplateApprovalStatusInterface
{
    /**
     * Reads the state of one template that was reported earlier.
     *
     * The raw code and text of the provider travel with the answer: the administration shows them
     * next to the mapped state, and a code the implementation does not know maps to
     * TemplateApprovalStatus::UNKNOWN instead of guessing an approval.
     *
     * @param array<string, mixed> $credentials the credentials of the sms_gateway row
     * @param array<string, mixed> $options the connection options of the sms_gateway row
     *
     * @throws SmsException
     */
    public function readTemplateApprovalStatus(
        array $credentials,
        array $options,
        string $templateCode,
    ): TemplateApprovalReading;
}
