<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway\Audit;

use Shopwell\Core\Framework\Log\Package;

/**
 * What a provider answered when it was asked for the audit state of a template.
 *
 * The raw code and text always travel with the mapped state: an operator reconciles the two when a
 * template was rejected, and a code the implementation does not know is answered as UNKNOWN instead
 * of being guessed into an approval.
 */
#[Package('framework')]
final readonly class TemplateApprovalReading
{
    /**
     * @param string $status one of the TemplateApprovalStatus values
     * @param string $providerStatusCode the raw status code of the provider, as a string
     * @param string $providerStatusMessage the raw text of the provider, e.g. a rejection reason
     * @param string|null $providerTemplateName the name the provider stored next to the template
     */
    public function __construct(
        public string $status,
        public string $providerStatusCode,
        public string $providerStatusMessage,
        public ?string $providerTemplateName = null,
    ) {
    }
}
