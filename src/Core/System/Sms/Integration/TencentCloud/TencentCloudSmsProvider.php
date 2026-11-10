<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Integration\TencentCloud;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Provider\AbstractSmsProvider;
use Shopwell\Core\System\Sms\Provider\Audit\ReadsTemplateApprovalStatusInterface;
use Shopwell\Core\System\Sms\Provider\Audit\RequiresTemplateApprovalInterface;
use Shopwell\Core\System\Sms\Provider\Audit\SubmitsTemplatesForApprovalInterface;
use Shopwell\Core\System\Sms\Provider\Audit\TemplateApprovalReading;
use Shopwell\Core\System\Sms\Provider\Audit\TemplateSubmission;
use Shopwell\Core\System\Sms\Provider\Audit\TemplateSubmissionReceipt;
use Shopwell\Core\System\Sms\Provider\Config\SmsConfigField;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use TencentCloud\Common\Credential;
use TencentCloud\Sms\V20210111\SmsClient;

/**
 * @internal
 */
#[Package('framework')]
final class TencentCloudSmsProvider extends AbstractSmsProvider implements RequiresTemplateApprovalInterface, SubmitsTemplatesForApprovalInterface, ReadsTemplateApprovalStatusInterface
{
    public function getTechnicalName(): string
    {
        return TencentCloudSmsTransport::TECHNICAL_NAME;
    }

    public function getLabelSnippet(): string
    {
        return 'sms.gateway.tencentcloud';
    }

    /**
     * The provider serves the domestic numbers only, the international dimension of a template needs
     * the International flag of AddSmsTemplate, which this round does not implement.
     *
     * @return list<string>
     */
    public function getSupportedRegions(): array
    {
        return ['CN'];
    }

    public function supportsTemplateMode(): bool
    {
        return true;
    }

    public function getCredentials(): array
    {
        return [
            new SmsConfigField('secretId', 'sms.credential.tencentcloud.secretId', type: 'string', required: true),
            new SmsConfigField('secretKey', 'sms.credential.tencentcloud.secretKey', type: 'secret', required: true),
            new SmsConfigField('sdkAppId', 'sms.credential.tencentcloud.sdkAppId', type: 'string', required: true),
            new SmsConfigField('region', 'sms.credential.tencentcloud.region', type: 'string', required: true),
        ];
    }

    public function getOptions(): array
    {
        return [new SmsConfigField('signName', 'sw-settings-sms.gateway.labelSignName')];
    }

    public function createTransport(
        array $credentials,
        array $options,
        ?EventDispatcherInterface $dispatcher = null,
    ): TransportInterface {
        $this->requireCredentials($credentials);

        $client = new SmsClient(
            new Credential(
                (string) ($credentials['secretId'] ?? ''),
                (string) ($credentials['secretKey'] ?? '')
            ),
            (string) ($credentials['region'] ?? '')
        );

        $signName = $options['signName'] ?? null;

        return new TencentCloudSmsTransport(
            $client,
            (string) ($credentials['sdkAppId'] ?? ''),
            $signName === null || $signName === '' ? null : (string) $signName,
            $dispatcher
        );
    }

    public function getTemplateSubmissionFieldDefinitions(): array
    {
        return (new TencentCloudTemplateApproval())->getTemplateSubmissionFieldDefinitions();
    }

    public function submitTemplate(
        array $credentials,
        array $options,
        TemplateSubmission $submission,
    ): TemplateSubmissionReceipt {
        $this->requireCredentials($credentials);

        return (new TencentCloudTemplateApproval($credentials))->submitTemplate($submission);
    }

    public function readTemplateApprovalStatus(
        array $credentials,
        array $options,
        string $templateCode,
    ): TemplateApprovalReading {
        $this->requireCredentials($credentials);

        return (new TencentCloudTemplateApproval($credentials))->readTemplateApprovalStatus($templateCode);
    }
}
