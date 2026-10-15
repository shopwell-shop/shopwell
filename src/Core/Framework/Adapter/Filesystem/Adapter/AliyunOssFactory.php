<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Adapter\Filesystem\Adapter;

use League\Flysystem\FilesystemAdapter;
use OSS\Credentials\EnvironmentVariableCredentialsProvider;
use OSS\Credentials\StaticCredentialsProvider;
use OSS\OssClient;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Creates the Alibaba Cloud OSS adapter.
 *
 * The endpoint may be passed with or without a scheme, without one the client talks https. A file
 * that is bound to the bucket has to be marked with `is_cname`, then the bucket is not part of the
 * host any more. Credentials may be omitted, then the client reads the OSS_ACCESS_KEY_ID,
 * OSS_ACCESS_KEY_SECRET and OSS_SESSION_TOKEN environment variables.
 *
 * @see https://help.aliyun.com/zh/oss/developer-reference/endpoints
 */
#[Package('framework')]
class AliyunOssFactory implements AdapterFactoryInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function create(array $config): FilesystemAdapter
    {
        $options = $this->resolveOptions($config);

        return new AliyunOssAdapter(
            new OssClient([
                'endpoint' => $this->resolveEndpoint($options['endpoint']),
                'cname' => $options['is_cname'],
                'provider' => $this->resolveCredentialsProvider($options['credentials']),
            ]),
            $options['bucket'],
            $options['root'],
            null,
            $options['options'],
        );
    }

    public function getType(): string
    {
        return 'aliyun-oss';
    }

    private function resolveEndpoint(string $endpoint): string
    {
        if (str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://')) {
            return $endpoint;
        }

        return 'https://' . $endpoint;
    }

    /**
     * @param array{key: string, secret: string, token: string|null}|null $credentials
     */
    private function resolveCredentialsProvider(?array $credentials): StaticCredentialsProvider|EnvironmentVariableCredentialsProvider
    {
        if ($credentials === null) {
            return new EnvironmentVariableCredentialsProvider();
        }

        return new StaticCredentialsProvider($credentials['key'], $credentials['secret'], $credentials['token']);
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return array<string, mixed>
     */
    private function resolveOptions(array $definition): array
    {
        $options = new OptionsResolver();

        $options->setRequired(['bucket', 'endpoint']);
        $options->setDefined(['root', 'credentials', 'is_cname', 'options']);

        $options->setAllowedTypes('bucket', 'string');
        $options->setAllowedTypes('endpoint', 'string');
        $options->setAllowedTypes('root', 'string');
        $options->setAllowedTypes('credentials', ['array', 'null']);
        $options->setAllowedTypes('is_cname', 'bool');
        $options->setAllowedTypes('options', 'array');

        $options->setDefault('root', '');
        $options->setDefault('credentials', null);
        $options->setDefault('is_cname', false);
        $options->setDefault('options', []);

        $resolved = $options->resolve($definition);

        if (\is_array($resolved['credentials']) && $resolved['credentials'] !== []) {
            $resolved['credentials'] = $this->resolveCredentialOptions($resolved['credentials']);
        } else {
            $resolved['credentials'] = null;
        }

        return $resolved;
    }

    /**
     * @param array<string, mixed> $credentials
     *
     * @return array{key: string, secret: string, token: string|null}
     */
    private function resolveCredentialOptions(array $credentials): array
    {
        $options = new OptionsResolver();

        $options->setRequired(['key', 'secret']);
        $options->setDefined('token');

        $options->setAllowedTypes('key', 'string');
        $options->setAllowedTypes('secret', 'string');
        $options->setAllowedTypes('token', ['string', 'null']);

        $options->setDefault('token', null);

        /** @var array{key: string, secret: string, token: string|null} $resolved */
        $resolved = $options->resolve($credentials);

        return $resolved;
    }
}
