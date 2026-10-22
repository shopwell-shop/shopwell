<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway\Audit;

use Shopwell\Core\Framework\Log\Package;

/**
 * What a provider answered to a submission: the template code it keeps for the template, the state
 * it reports for it and, optionally, the raw words it used.
 *
 * The state is one of the TemplateApprovalStatus values - the implementation maps its own codes
 * before it answers, so the core never sees a provider code as a state.
 */
#[Package('framework')]
final readonly class TemplateSubmissionReceipt
{
    /**
     * @param string $templateCode the template id at the provider, e.g. SMS_123456789
     * @param string $status one of the TemplateApprovalStatus values
     * @param string|null $providerTemplateName the name the provider stored next to the template
     * @param string|null $providerCode the raw status code of the provider, kept for the
     *                                  administration and never interpreted by the core
     * @param string|null $providerMessage the raw text of the provider, e.g. the reason of a
     *                                     rejection
     */
    public function __construct(
        public string $templateCode,
        public string $status,
        public ?string $providerTemplateName = null,
        public ?string $providerCode = null,
        public ?string $providerMessage = null,
    ) {
    }
}
