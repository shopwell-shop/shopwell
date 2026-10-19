<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway\TencentCloud;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\AbstractSmsGatewayTransportFactory;
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
 * @internal
 */
#[Package('framework')]
final class TencentCloudGatewayTransportFactory extends AbstractSmsGatewayTransportFactory
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
     * The international templates of the provider are a separate kind that has to be reported as well,
     * so a number of another country can be served by it.
     */
    public function getSupportedRegions(): array
    {
        return ['CN', 'GLOBAL'];
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
}
