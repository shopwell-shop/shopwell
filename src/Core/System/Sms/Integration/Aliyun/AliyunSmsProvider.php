<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Integration\Aliyun;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use Darabonba\OpenApi\Models\Config;
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

/**
 * @internal
 */
#[Package('framework')]
final class AliyunSmsProvider extends AbstractSmsProvider implements ReadsTemplateApprovalStatusInterface, RequiresTemplateApprovalInterface, SubmitsTemplatesForApprovalInterface
{
    /**
     * The endpoint of the api, both the domestic and the international message of every region go
     * through this one.
     */
    private const ENDPOINT = 'dysmsapi.aliyuncs.com';

    public function getTechnicalName(): string
    {
        return AliyunSmsTransport::TECHNICAL_NAME;
    }

    public function getLabelSnippet(): string
    {
        return 'sms.gateway.aliyun';
    }

    /**
     * The signature of a domestic template has to be reported in china, which the provider checks, so
     * only numbers of the country are routed here.
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
            new SmsConfigField('accessKeyId', 'sms.credential.aliyun.accessKeyId', type: 'string', required: true),
            new SmsConfigField('accessKeySecret', 'sms.credential.aliyun.accessKeySecret', type: 'secret', required: true),
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
        $signName = $options['signName'] ?? null;

        return new AliyunSmsTransport(
            $this->createClient($credentials),
            $signName === null || $signName === '' ? null : (string) $signName,
            $dispatcher
        );
    }

    public function getTemplateSubmissionFieldDefinitions(): array
    {
        return (new AliyunTemplateApproval())->getTemplateSubmissionFieldDefinitions();
    }

    public function submitTemplate(
        array $credentials,
        array $options,
        TemplateSubmission $submission,
    ): TemplateSubmissionReceipt {
        return (new AliyunTemplateApproval())->submitTemplate($this->createClient($credentials), $submission);
    }

    public function readTemplateApprovalStatus(
        array $credentials,
        array $options,
        string $templateCode,
    ): TemplateApprovalReading {
        return (new AliyunTemplateApproval())->readTemplateApprovalStatus($this->createClient($credentials), $templateCode);
    }

    /**
     * The client of one account of the row, built for the call that needs it so the provider itself
     * holds no connection.
     *
     * @param array<string, mixed> $credentials the credentials of the sms_gateway row
     */
    private function createClient(array $credentials): Dysmsapi
    {
        $this->requireCredentials($credentials);

        $config = new Config([
            'accessKeyId' => (string) ($credentials['accessKeyId'] ?? ''),
            'accessKeySecret' => (string) ($credentials['accessKeySecret'] ?? ''),
        ]);
        $config->endpoint = self::ENDPOINT;

        return new Dysmsapi($config);
    }
}
