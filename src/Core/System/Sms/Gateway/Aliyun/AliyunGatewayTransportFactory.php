<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway\Aliyun;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use Darabonba\OpenApi\Models\Config;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\AbstractSmsGatewayTransportFactory;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The aliyun provider of the platform.
 *
 * The credentials are the key pair of a ram user that was granted the SendSms action, the signature
 * can be stored with the account and is then the one the templates fall back to.
 *
 * @internal
 */
#[Package('framework')]
final class AliyunGatewayTransportFactory extends AbstractSmsGatewayTransportFactory
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
        $this->requireCredentials($credentials);

        $config = new Config([
            'accessKeyId' => (string) ($credentials['accessKeyId'] ?? ''),
            'accessKeySecret' => (string) ($credentials['accessKeySecret'] ?? ''),
        ]);
        $config->endpoint = self::ENDPOINT;

        $signName = $options['signName'] ?? null;

        return new AliyunSmsTransport(
            new Dysmsapi($config),
            $signName === null || $signName === '' ? null : (string) $signName,
            $dispatcher
        );
    }
}
