<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway\TencentCloud;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateApprovalReading;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateApprovalStatus;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateSubmission;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateSubmissionReceipt;
use Shopwell\Core\System\Sms\SmsException;
use TencentCloud\Common\Credential;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Sms\V20210111\Models\AddSmsTemplateRequest;
use TencentCloud\Sms\V20210111\Models\AddSmsTemplateResponse;
use TencentCloud\Sms\V20210111\Models\AddTemplateStatus;
use TencentCloud\Sms\V20210111\Models\DescribeSmsTemplateListRequest;
use TencentCloud\Sms\V20210111\Models\DescribeSmsTemplateListResponse;
use TencentCloud\Sms\V20210111\Models\DescribeTemplateListStatus;
use TencentCloud\Sms\V20210111\SmsClient;

/**
 * Reports a template of the shop at tencent cloud and reads the state the provider keeps for it.
 *
 * The two calls are the whole audit loop of this provider: AddSmsTemplate creates a template the
 * provider reviews afterwards, DescribeSmsTemplateList answers the state of a template that was
 * created earlier - there is no call that returns the state of a submission itself.
 *
 * Only domestic templates are reported in this round: International is sent as 0 and is no field of
 * the form, because the module has no dimension for the range of delivery yet. An international
 * template needs a gateway of its own, see the docblock of the factory.
 *
 * The class holds the credentials of one gateway row and builds the client of the sdk per call, so
 * two submissions can never share the state of a client. The connection options of the row are not
 * needed: the signature of a template is no part of a submission, the provider audits signatures in
 * a call of their own.
 *
 * An answer is read as the array of the fields it actually carries. The models of the sdk declare
 * every field as set, while the sdk leaves a field it did not receive unset - an answer without one
 * of them has to be recognised as invalid instead of being read as its default.
 *
 * @internal
 */
#[Package('framework')]
final class TencentCloudTemplateApproval
{
    private const TECHNICAL_NAME = TencentCloudSmsTransport::TECHNICAL_NAME;

    /**
     * The range of delivery of AddSmsTemplate and DescribeSmsTemplateList, 0 is domestic.
     */
    private const DOMESTIC = 0;

    private const SMS_TYPE_MARKETING = 1;
    private const SMS_TYPE_NOTIFICATION = 2;
    private const SMS_TYPE_VERIFICATION = 3;

    /**
     * The state the provider reports for a template in DescribeSmsTemplateList.
     *
     * 2 is "approved but not effective yet" and is deliberately not an approval: the api does not
     * deliver with such a template, so a shop that read it as approved would send and be refused.
     *
     * @var array<int, string>
     */
    private const STATUS_MAP = [
        0 => TemplateApprovalStatus::APPROVED,
        1 => TemplateApprovalStatus::PENDING,
        2 => TemplateApprovalStatus::PENDING,
        -1 => TemplateApprovalStatus::REJECTED,
    ];

    /**
     * The states in which the provider holds the template already, so a repeated submission answers
     * the code of the existing template instead of creating a second one.
     *
     * @var list<string>
     */
    private const REUSABLE_STATUSES = [
        TemplateApprovalStatus::APPROVED,
        TemplateApprovalStatus::PENDING,
    ];

    /**
     * The codes that name a cause an operator has to correct at the console, a repeat of the same
     * request would be refused just the same.
     *
     * Everything else the api reports is a state of the provider that can pass on another attempt.
     *
     * @var list<string>
     */
    private const REJECTED_CODE_PREFIXES = [
        'AuthFailure',
        'InvalidParameter',
        'FailedOperation.SignatureIncorrectOrUnapproved',
        'FailedOperation.TemplateIncorrectOrUnapproved',
        'FailedOperation.TemplateFull',
        'FailedOperation.TemplateNumberLimitExceeded',
    ];

    /**
     * @param array<string, mixed> $credentials the credentials of the sms_gateway row
     * @param SmsClient|null $sdk the client of the provider, built from the credentials when the
     *                            caller does not hand one in - describing the form needs no client
     */
    public function __construct(
        private readonly array $credentials = [],
        private readonly ?SmsClient $sdk = null,
    ) {
    }

    /**
     * The fields an operator fills for AddSmsTemplate, in the vocabulary of the provider.
     *
     * The submitter and the body are prefilled by the core because it knows them already, the type
     * of the message is the decision of the operator and the remark is the explanation the reviewer
     * of the provider reads.
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
                'labelSnippet' => 'sw-settings-sms.submission.tencentcloud.templateName',
                'prefill' => 'templateName',
            ],
            [
                'name' => 'templateContent',
                'type' => 'text',
                'required' => true,
                'labelSnippet' => 'sw-settings-sms.submission.tencentcloud.templateContent',
                'prefill' => 'content',
            ],
            [
                'name' => 'smsType',
                'type' => 'select',
                'required' => true,
                'labelSnippet' => 'sw-settings-sms.submission.tencentcloud.smsType',
                'default' => self::SMS_TYPE_NOTIFICATION,
                'options' => [
                    [
                        'value' => self::SMS_TYPE_MARKETING,
                        'labelSnippet' => 'sw-settings-sms.submission.tencentcloud.smsType.marketing',
                    ],
                    [
                        'value' => self::SMS_TYPE_NOTIFICATION,
                        'labelSnippet' => 'sw-settings-sms.submission.tencentcloud.smsType.notification',
                    ],
                    [
                        'value' => self::SMS_TYPE_VERIFICATION,
                        'labelSnippet' => 'sw-settings-sms.submission.tencentcloud.smsType.verification',
                    ],
                ],
            ],
            [
                'name' => 'remark',
                'type' => 'string',
                'required' => false,
                'labelSnippet' => 'sw-settings-sms.submission.tencentcloud.remark',
                'helpSnippet' => 'sw-settings-sms.submission.tencentcloud.remarkHelp',
            ],
        ];
    }

    /**
     * Reports the template of the submission and answers the state the provider keeps for it.
     *
     * The submitted body is the body of the default language - the mapping carries one body, not one
     * per language. A submission that names a template the provider still holds returns the code of
     * that template, so pressing "submit" twice cannot create a duplicate.
     *
     * @throws SmsException
     */
    public function submitTemplate(TemplateSubmission $submission): TemplateSubmissionReceipt
    {
        $existingTemplateCode = $submission->existingTemplateCode;

        if ($existingTemplateCode !== null) {
            $existing = $this->readTemplateApprovalStatus($existingTemplateCode);

            if (\in_array($existing->status, self::REUSABLE_STATUSES, true)) {
                return new TemplateSubmissionReceipt(
                    $existingTemplateCode,
                    $existing->status,
                    $existing->providerTemplateName,
                    $existing->providerStatusCode,
                    $existing->providerStatusMessage,
                );
            }
        }

        $smsType = $submission->values['smsType'] ?? null;

        if ($smsType === null) {
            throw SmsException::templateSubmissionInvalid(
                $submission->templateRef,
                self::TECHNICAL_NAME,
                ['"smsType" is required']
            );
        }

        $templateName = (string) ($submission->values['templateName'] ?? $submission->templateRef);

        $request = new AddSmsTemplateRequest();
        $request->TemplateName = $templateName;
        $request->TemplateContent = $submission->content;
        $request->SmsType = (int) $smsType;
        // the round has no dimension for the range of delivery, so every template is domestic
        $request->International = self::DOMESTIC;
        $request->Remark = (string) ($submission->values['remark'] ?? '');

        try {
            /** @var AddSmsTemplateResponse $response */
            $response = $this->client()->AddSmsTemplate($request);
        } catch (TencentCloudSDKException $error) {
            $this->fail($error);
        }

        /** @var array{AddTemplateStatus?: AddTemplateStatus} $answer */
        $answer = get_object_vars($response);

        $templateCode = '';
        $added = $answer['AddTemplateStatus'] ?? null;

        if ($added !== null) {
            /** @var array{TemplateId?: string} $fields */
            $fields = get_object_vars($added);
            $templateCode = (string) ($fields['TemplateId'] ?? '');
        }

        if ($templateCode === '') {
            throw SmsException::templateAuditResponseInvalid(
                self::TECHNICAL_NAME,
                'The response of tencent cloud carried no template id.'
            );
        }

        // the provider reviews a new template after it was reported, so it is pending until a read
        // of the state says otherwise
        return new TemplateSubmissionReceipt(
            $templateCode,
            TemplateApprovalStatus::PENDING,
            $templateName,
        );
    }

    /**
     * Reads the state tencent cloud reports for one template that was reported earlier.
     *
     * A template the provider does not list is answered as unknown with the words of the provider
     * instead of a guess: a state the shop cannot judge is no permission to send.
     *
     * @throws SmsException
     */
    public function readTemplateApprovalStatus(string $templateCode): TemplateApprovalReading
    {
        $request = new DescribeSmsTemplateListRequest();
        // the same dimension the submission reports, only domestic templates are kept by the shop
        $request->International = self::DOMESTIC;
        $request->TemplateIdSet = [(int) $templateCode];

        try {
            /** @var DescribeSmsTemplateListResponse $response */
            $response = $this->client()->DescribeSmsTemplateList($request);
        } catch (TencentCloudSDKException $error) {
            $this->fail($error);
        }

        /** @var array{DescribeTemplateStatusSet?: list<DescribeTemplateListStatus>} $answer */
        $answer = get_object_vars($response);

        foreach ($answer['DescribeTemplateStatusSet'] ?? [] as $status) {
            /** @var array{TemplateId?: int, StatusCode?: int, ReviewReply?: string, TemplateName?: string} $fields */
            $fields = get_object_vars($status);

            if ((string) ($fields['TemplateId'] ?? '') !== $templateCode) {
                continue;
            }

            $statusCode = $fields['StatusCode'] ?? null;

            if ($statusCode === null) {
                throw SmsException::templateAuditResponseInvalid(
                    self::TECHNICAL_NAME,
                    'The provider listed the template without a status code.'
                );
            }

            return new TemplateApprovalReading(
                $this->mapStatus($statusCode),
                (string) $statusCode,
                $fields['ReviewReply'] ?? '',
                $fields['TemplateName'] ?? null,
            );
        }

        return new TemplateApprovalReading(
            TemplateApprovalStatus::UNKNOWN,
            '',
            \sprintf('Tencent cloud lists no domestic template with the id "%s".', $templateCode),
        );
    }

    /**
     * The state of the shop for a code of the provider, an unlisted code is not guessed into an
     * approval.
     */
    private function mapStatus(int $code): string
    {
        return self::STATUS_MAP[$code] ?? TemplateApprovalStatus::UNKNOWN;
    }

    /**
     * Translates an error of the api into the pair of the module, so a caller can tell a cause it
     * has to correct from one that is worth another try.
     */
    private function fail(TencentCloudSDKException $error): never
    {
        $code = $error->getErrorCode();

        if ($code === '') {
            // the sdk wraps a failure of the transport itself without a code - the api has not seen
            // the request, so repeating it cannot report the template twice
            throw SmsException::providerUnavailable(self::TECHNICAL_NAME, 'TransportError', $error->getMessage());
        }

        foreach (self::REJECTED_CODE_PREFIXES as $prefix) {
            if (str_starts_with($code, $prefix)) {
                throw SmsException::providerRejected(self::TECHNICAL_NAME, $code, $error->getMessage());
            }
        }

        throw SmsException::providerUnavailable(self::TECHNICAL_NAME, $code, $error->getMessage());
    }

    /**
     * The client of the provider, built from the credentials of one gateway row like the factory
     * builds the one of the transport.
     */
    private function client(): SmsClient
    {
        return $this->sdk ?? new SmsClient(
            new Credential(
                (string) ($this->credentials['secretId'] ?? ''),
                (string) ($this->credentials['secretKey'] ?? '')
            ),
            (string) ($this->credentials['region'] ?? '')
        );
    }
}
