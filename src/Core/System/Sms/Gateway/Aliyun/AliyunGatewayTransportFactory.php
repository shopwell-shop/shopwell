<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway\Aliyun;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use Darabonba\OpenApi\Models\Config;
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

/**
 * The aliyun provider of the platform.
 *
 * The credentials are the key pair of a ram user that was granted the SendSms action, the signature
 * can be stored with the account and is then the one the templates fall back to.
 *
 * A template is not delivered before the audit of aliyun approved it, so the row declares the three
 * audit capabilities and hands the work to AliyunTemplateApproval: the vocabulary of the provider
 * stays in that class, this one only builds the client of the account the row names.
 *
 * @internal
 */
#[Package('framework')]
final class AliyunGatewayTransportFactory extends AbstractSmsGatewayTransportFactory implements ReadsTemplateApprovalStatusInterface, RequiresTemplateApprovalInterface, SubmitsTemplatesForApprovalInterface
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

    public function getCredentialDefinitions(): array
    {
        return [
            [
                'name' => 'accessKeyId',
                'type' => 'string',
                'required' => true,
                'labelSnippet' => 'sms.credential.aliyun.accessKeyId',
            ],
            [
                'name' => 'accessKeySecret',
                'type' => 'secret',
                'required' => true,
                'labelSnippet' => 'sms.credential.aliyun.accessKeySecret',
            ],
        ];
    }

    public function create(
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
     * The client of one account of the row, built for the call that needs it so the factory itself
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
