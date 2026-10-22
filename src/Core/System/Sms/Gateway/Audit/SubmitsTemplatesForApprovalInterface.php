<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway\Audit;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\SmsException;

/**
 * Declares that the provider accepts a template over its api, and describes the form that submits
 * it, so the administration renders the fields instead of shipping a form per provider.
 *
 * The definitions are the vocabulary of the provider, exactly like getCredentialDefinitions() of
 * the gateway factory: the core maps them onto form fields and never interprets them.
 *
 * `prefill` names the value the core already knows and puts into the field, the vocabulary is fixed
 * and core owned: templateName (the technical name of the logical template), content (the body of
 * the default language, the only body this round submits), scene (one of the SCENE_* constants).
 * A field without `prefill` is filled by the operator.
 */
#[Package('framework')]
interface SubmitsTemplatesForApprovalInterface
{
    /**
     * The fields a submission of this provider needs, rendered by the administration and validated
     * before the provider is called.
     *
     * @return list<array{
     *     name: string,
     *     type: 'string'|'text'|'int'|'select',
     *     required: bool,
     *     labelSnippet: string,
     *     prefill?: 'templateName'|'content'|'scene',
     *     default?: string|int|null,
     *     helpSnippet?: string,
     *     options?: list<array{value: string|int, labelSnippet: string}>,
     * }>
     */
    public function getTemplateSubmissionFieldDefinitions(): array;

    /**
     * Reports one template at the provider and returns what the provider answered.
     *
     * The submitted body is the body of the default language - the mapping carries one body, not one
     * per language. A provider whose api wants the language as a parameter takes it from the
     * entity, not from the submission.
     *
     * Repeated calls for the same mapping must not create a second template at the provider - the
     * submission carries the template code the mapping already knows, and the implementation returns
     * that one instead of creating a new template when it is still usable.
     *
     * @param array<string, mixed> $credentials the credentials of the sms_gateway row
     * @param array<string, mixed> $options the connection options of the sms_gateway row
     *
     * @throws SmsException
     */
    public function submitTemplate(
        array $credentials,
        array $options,
        TemplateSubmission $submission,
    ): TemplateSubmissionReceipt;
}
