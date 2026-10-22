<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway\Aliyun;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\AddSmsTemplateRequest;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\AddSmsTemplateResponseBody;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\QuerySmsTemplateRequest;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\QuerySmsTemplateResponseBody;
use Darabonba\OpenApi\Exceptions\AlibabaCloudException;
use Darabonba\OpenApi\Exceptions\ServerException;
use Darabonba\OpenApi\Exceptions\ThrottlingException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateApprovalReading;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateApprovalStatus;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateSubmission;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateSubmissionReceipt;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;

/**
 * Reports a template of the shop at the audit of aliyun and reads the state it answered with back.
 *
 * A template lives in the account of a gateway, so every call takes the client of exactly one
 * account - the class itself keeps no state and the caller decides which row is asked.
 *
 * The api answers an accepted request with the code of the failure in the body as well, so only the
 * body tells whether the template was reported: the same rule the send path of the transport follows.
 *
 * @internal
 */
#[Package('framework')]
final class AliyunTemplateApproval
{
    private const SUCCESS_CODE = 'OK';

    /**
     * The account does not hold the template: the state of the row is not a state of the provider,
     * it has to be reported again - which is why the code is answered as UNKNOWN and not as an error.
     */
    private const UNKNOWN_TEMPLATE_CODE = 'isv.SMS_TEMPLATE_NOT_EXIST';

    /**
     * Aliyun prefixes a failure at its own side with isp. and one the request caused with isv., and
     * only the first is worth another try. The rate limit is the exception - nothing was reported and
     * the very same request may pass later.
     *
     * @var list<string>
     */
    private const RETRYABLE_CODE_PREFIXES = [
        'isp.',
        'isv.BUSINESS_LIMIT_CONTROL',
    ];

    /**
     * The three states of templateStatus, the state a template of the shop starts and ends in. A code
     * outside this map is a state the shop cannot judge and therefore not a permission.
     *
     * @var array<int, string>
     */
    private const STATUS_MAP = [
        0 => TemplateApprovalStatus::PENDING,
        1 => TemplateApprovalStatus::APPROVED,
        2 => TemplateApprovalStatus::REJECTED,
    ];

    /**
     * The use of a template as aliyun numbers it, keyed by the scenes of the module.
     *
     * @var array<string, int>
     */
    private const SCENE_TYPE_MAP = [
        SmsTemplateEntity::SCENE_OTP => 0,
        SmsTemplateEntity::SCENE_TRANSACTION => 1,
        SmsTemplateEntity::SCENE_MARKETING => 2,
    ];

    /**
     * A scene the module does not know is reported as a notification - the safest of the three, as a
     * marketing template is refused for a message a customer waits for.
     */
    private const DEFAULT_TEMPLATE_TYPE = 1;

    /**
     * A template that still waits for the audit or already passed it is not created a second time.
     *
     * @var list<string>
     */
    private const REUSABLE_STATUSES = [
        TemplateApprovalStatus::PENDING,
        TemplateApprovalStatus::APPROVED,
    ];

    /**
     * The three fields the api asks for, in the vocabulary of the module.
     *
     * The name and the body are the ones the shop already holds, the use of the template is the scene
     * of it - the operator confirms the three read only and never types them again.
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
    public function getTemplateSubmissionFieldDefinitions(): array
    {
        return [
            [
                'name' => 'templateName',
                'type' => 'string',
                'required' => true,
                'labelSnippet' => 'sms.submission.aliyun.templateName',
                'prefill' => 'templateName',
            ],
            [
                'name' => 'templateContent',
                'type' => 'text',
                'required' => true,
                'labelSnippet' => 'sms.submission.aliyun.templateContent',
                'prefill' => 'content',
            ],
            [
                'name' => 'templateType',
                'type' => 'select',
                'required' => true,
                'labelSnippet' => 'sms.submission.aliyun.templateType',
                'prefill' => 'scene',
                'options' => [
                    [
                        'value' => SmsTemplateEntity::SCENE_OTP,
                        'labelSnippet' => 'sms.submission.aliyun.templateType.code',
                    ],
                    [
                        'value' => SmsTemplateEntity::SCENE_TRANSACTION,
                        'labelSnippet' => 'sms.submission.aliyun.templateType.notice',
                    ],
                    [
                        'value' => SmsTemplateEntity::SCENE_MARKETING,
                        'labelSnippet' => 'sms.submission.aliyun.templateType.promotion',
                    ],
                ],
            ],
        ];
    }

    /**
     * Reports the body of the submission as a template of the account.
     *
     * A template the account already holds and that is still waiting or already approved is not
     * created a second time: the row carries its code and gets that code back. A code the account
     * deleted or rejected is replaced by a new template, as only the provider knows which of the two
     * happened.
     *
     * @throws SmsException
     */
    public function submitTemplate(Dysmsapi $sdk, TemplateSubmission $submission): TemplateSubmissionReceipt
    {
        $existingCode = $submission->existingTemplateCode;

        if ($existingCode !== null) {
            $existing = $this->queryTemplate($sdk, $existingCode);

            if (\in_array($existing->status, self::REUSABLE_STATUSES, true)) {
                return new TemplateSubmissionReceipt(
                    templateCode: $existingCode,
                    status: $existing->status,
                    providerTemplateName: $existing->providerTemplateName,
                    providerCode: $existing->providerStatusCode,
                    providerMessage: $existing->providerStatusMessage,
                );
            }
        }

        $name = $submission->values['templateName'] ?? null;
        $body = $submission->values['templateContent'] ?? null;

        // the fields are the ones the core prefilled, an empty one falls back to the submission
        // itself, so a template is never reported under a blank name or with a blank body
        $templateName = \is_string($name) && trim($name) !== '' ? $name : $submission->templateRef;
        $templateContent = \is_string($body) && trim($body) !== '' ? $body : $submission->content;

        $request = new AddSmsTemplateRequest([
            'templateName' => $templateName,
            'templateContent' => $templateContent,
            'templateType' => self::templateTypeOf($submission),
        ]);

        try {
            // @phpstan-ignore method.deprecated (the classic rpc api is the one the design reports templates with)
            $response = $sdk->addSmsTemplate($request);
        } catch (ThrottlingException|ServerException $error) {
            // the sdk saw a status of the endpoint that asks for another try
            throw SmsException::providerUnavailable(AliyunSmsTransport::TECHNICAL_NAME, $error->code, $error->message);
        } catch (AlibabaCloudException $error) {
            // everything else the endpoint refused, the code says whether it can pass later
            $this->failWithCode($error->code, $error->message);
        }

        $answer = self::submissionAnswerOf($response->body);

        if ($answer['code'] !== self::SUCCESS_CODE) {
            $this->failWithCode($answer['code'], $answer['message']);
        }

        if (trim($answer['templateCode']) === '') {
            throw SmsException::templateAuditResponseInvalid(
                AliyunSmsTransport::TECHNICAL_NAME,
                'the answer of AddSmsTemplate carries no TemplateCode'
            );
        }

        return new TemplateSubmissionReceipt(
            templateCode: $answer['templateCode'],
            // a reported template starts in the audit of the provider, its state is read back later
            status: TemplateApprovalStatus::PENDING,
            providerTemplateName: $templateName,
            providerCode: $answer['code'],
            providerMessage: $answer['message'],
        );
    }

    /**
     * Reads the state the audit of aliyun reports for a template of the account.
     *
     * @throws SmsException
     */
    public function readTemplateApprovalStatus(Dysmsapi $sdk, string $templateCode): TemplateApprovalReading
    {
        return $this->queryTemplate($sdk, $templateCode);
    }

    /**
     * Asks the api for one template and translates its answer into the vocabulary of the module.
     *
     * @throws SmsException
     */
    private function queryTemplate(Dysmsapi $sdk, string $templateCode): TemplateApprovalReading
    {
        $request = new QuerySmsTemplateRequest(['templateCode' => $templateCode]);

        try {
            // @phpstan-ignore method.deprecated (the classic rpc api is the one the design reads the state with)
            $response = $sdk->querySmsTemplate($request);
        } catch (ThrottlingException|ServerException $error) {
            throw SmsException::providerUnavailable(AliyunSmsTransport::TECHNICAL_NAME, $error->code, $error->message);
        } catch (AlibabaCloudException $error) {
            $this->failWithCode($error->code, $error->message);
        }

        $answer = self::queryAnswerOf($response->body);

        if ($answer['code'] === self::UNKNOWN_TEMPLATE_CODE) {
            // the account holds no template under this code, the row has no state the shop can judge
            return new TemplateApprovalReading(
                status: TemplateApprovalStatus::UNKNOWN,
                providerStatusCode: $answer['code'],
                providerStatusMessage: $answer['message'],
            );
        }

        if ($answer['code'] !== self::SUCCESS_CODE) {
            $this->failWithCode($answer['code'], $answer['message']);
        }

        $templateStatus = $answer['templateStatus'];

        if ($templateStatus === null) {
            throw SmsException::templateAuditResponseInvalid(
                AliyunSmsTransport::TECHNICAL_NAME,
                'the answer of QuerySmsTemplate carries no TemplateStatus'
            );
        }

        // the reason of a rejection is what an operator has to read, the message only repeats the code
        $reason = $answer['reason'];
        $statusMessage = trim($reason) !== '' ? $reason : $answer['message'];

        return new TemplateApprovalReading(
            status: self::mapStatus($templateStatus),
            providerStatusCode: (string) $templateStatus,
            providerStatusMessage: $statusMessage,
            providerTemplateName: $answer['templateName'],
        );
    }

    /**
     * The answer of AddSmsTemplate as the audit reads it.
     *
     * The model of the sdk declares the body and its fields as always set, while the sdk leaves every
     * one of them uninitialised when the endpoint answers without them. The nullability is declared
     * on the parameter, so the guarded reads stay in one place.
     *
     * @return array{code: string, message: string, templateCode: string}
     */
    private static function submissionAnswerOf(?AddSmsTemplateResponseBody $body): array
    {
        return [
            // a coalesce reads an unset field with isset semantics, a plain read of it would warn
            'code' => $body->code ?? '',
            'message' => $body->message ?? '',
            'templateCode' => $body->templateCode ?? '',
        ];
    }

    /**
     * The answer of QuerySmsTemplate as the audit reads it, with the status still nullable because an
     * answer without it is a failure of the provider and not the state UNKNOWN.
     *
     * @return array{code: string, message: string, templateStatus: int|string|null, reason: string, templateName: string|null}
     */
    private static function queryAnswerOf(?QuerySmsTemplateResponseBody $body): array
    {
        return [
            'code' => $body->code ?? '',
            'message' => $body->message ?? '',
            'templateStatus' => $body->templateStatus ?? null,
            'reason' => $body->reason ?? '',
            'templateName' => $body->templateName ?? null,
        ];
    }

    /**
     * The three states of aliyun as the module names them, a code outside the map is UNKNOWN.
     */
    private static function mapStatus(int|string $templateStatus): string
    {
        $status = is_numeric($templateStatus) ? (self::STATUS_MAP[(int) $templateStatus] ?? null) : null;

        return $status ?? TemplateApprovalStatus::UNKNOWN;
    }

    /**
     * The use of the template as the api numbers it.
     *
     * The field of the submission offers the three scenes of the module, an implementation that hands
     * in the number of the provider itself is accepted as well.
     */
    private static function templateTypeOf(TemplateSubmission $submission): int
    {
        $value = $submission->values['templateType'] ?? null;

        if (\is_int($value)) {
            return $value;
        }

        $scene = \is_string($value) && isset(self::SCENE_TYPE_MAP[$value]) ? $value : $submission->scene;

        return self::SCENE_TYPE_MAP[$scene] ?? self::DEFAULT_TEMPLATE_TYPE;
    }

    /**
     * Splits a refusal of the api into the causes worth another try and the ones an operator has to
     * correct at the console.
     */
    private function failWithCode(string $code, string $message): never
    {
        throw match (true) {
            self::isRetryable($code) => SmsException::providerUnavailable(AliyunSmsTransport::TECHNICAL_NAME, $code, $message),
            default => SmsException::providerRejected(AliyunSmsTransport::TECHNICAL_NAME, $code, $message),
        };
    }

    private static function isRetryable(string $code): bool
    {
        foreach (self::RETRYABLE_CODE_PREFIXES as $prefix) {
            if (str_starts_with($code, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
