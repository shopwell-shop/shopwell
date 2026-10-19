<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway\Audit;

use Shopwell\Core\Framework\Log\Package;

/**
 * What the shop hands to a provider to have one template reported: the logical reference, the
 * scene, the body of the default language and the values of the fields the provider declared.
 *
 * It carries no language: this round reports and sends the body of the default language only, so a
 * language on the submission would promise a dimension the module does not have.
 */
#[Package('framework')]
final readonly class TemplateSubmission
{
    /**
     * @param string $templateRef the technical name of the logical template, e.g. customer.otp.login
     * @param string $scene one of the SCENE_* constants of the template
     * @param string $content the body of the default language, the only body that is reported
     * @param string|null $signName the signature of the mapping, null when the provider has none
     * @param list<string> $paramNames the placeholders of the body in order, for providers that
     *                                 report parameters positionally
     * @param array<string, mixed> $values the values of the provider declared submission fields,
     *                                     keyed by the field name
     * @param string|null $existingTemplateCode the template code the mapping already knows, so a
     *                                          repeated submission reuses that template
     */
    public function __construct(
        public string $templateRef,
        public string $scene,
        public string $content,
        public ?string $signName,
        public array $paramNames,
        public array $values,
        public ?string $existingTemplateCode,
    ) {
    }
}
