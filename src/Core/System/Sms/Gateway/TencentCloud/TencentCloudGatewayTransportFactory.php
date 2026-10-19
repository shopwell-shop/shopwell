<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway\TencentCloud;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\AbstractSmsGatewayTransportFactory;
use Shopwell\Core\System\Sms\Gateway\Audit\ReadsTemplateApprovalStatusInterface;
use Shopwell\Core\System\Sms\Gateway\Audit\RequiresTemplateApprovalInterface;
use Shopwell\Core\System\Sms\Gateway\Audit\SubmitsTemplatesForApprovalInterface;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateApprovalReading;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateSubmission;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateSubmissionReceipt;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use TencentCloud\Common\Credential;
use TencentCloud\Sms\V20210111\SmsClient;

/**
 * The tencent cloud provider of the platform.
 *
 * The credentials are the key pair of a cam user that was granted the SendSms action plus the id of
 * the sms application the templates belong to - every message is sent in the name of that application,
 * so an account without it cannot send at all. The region decides where the message is submitted
 * from, the endpoint of the api is the same in all of them.
 *
 * The provider audits every template before it can be used, so the factory declares the three audit
 * capabilities: it reports a template over AddSmsTemplate and reads the state of one over
 * DescribeSmsTemplateList, both in the name of the account of the row.
 *
 * The provider is declared for domestic numbers only. An international template is a template of its
 * own at this provider - AddSmsTemplate takes the International flag for it, and this round does not
 * implement that dimension, so a gateway that claims GLOBAL would be routed numbers it cannot serve.
 * An international gateway has to be a separate provider, which an extension can add without a
 * change of the core: technical name of its own, GLOBAL as its region, International set in its own
 * submission - see getSupportedRegions().
 *
 * @internal
 */
#[Package('framework')]
final class TencentCloudGatewayTransportFactory extends AbstractSmsGatewayTransportFactory implements RequiresTemplateApprovalInterface, SubmitsTemplatesForApprovalInterface, ReadsTemplateApprovalStatusInterface
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

    public function getCredentialDefinitions(): array
    {
        return [
            [
                'name' => 'secretId',
                'type' => 'string',
                'required' => true,
                'labelSnippet' => 'sms.credential.tencentcloud.secretId',
            ],
            [
                'name' => 'secretKey',
                'type' => 'secret',
                'required' => true,
                'labelSnippet' => 'sms.credential.tencentcloud.secretKey',
            ],
            [
                'name' => 'sdkAppId',
                'type' => 'string',
                'required' => true,
                'labelSnippet' => 'sms.credential.tencentcloud.sdkAppId',
            ],
            [
                'name' => 'region',
                'type' => 'string',
                'required' => true,
                'labelSnippet' => 'sms.credential.tencentcloud.region',
            ],
        ];
    }

    public function create(
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
