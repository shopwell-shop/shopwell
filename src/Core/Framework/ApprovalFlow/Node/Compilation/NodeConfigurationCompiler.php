<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Node\Compilation;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\NodeConfiguration;
use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared node compilation and lossless encoding for publication and pure node entry.
 *
 * @internal
 */
#[Package('framework')]
class NodeConfigurationCompiler
{
    public function __construct(
        private readonly NodeCompilerRegistry $nodeCompilers,
        private readonly GraphJsonDecoder $jsonDecoder,
    ) {
    }

    public function compile(string $type, string $version, string $json, string $path): NodeConfiguration
    {
        $compiler = $this->nodeCompilers->get($type, $version);
        try {
            $configuration = $compiler->compile($type, $version, $json);
        } catch (HttpException $exception) {
            if ($exception instanceof ApprovalFlowException && $exception->getErrorCode() !== ApprovalFlowException::INVALID_NODE_CONFIGURATION) {
                throw $exception;
            }
            if (!\in_array($exception->getStatusCode(), [Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY], true)) {
                throw $exception;
            }
            $parameters = $exception->getParameters();
            $localPath = \array_key_exists('path', $parameters) ? $parameters['path'] : '';
            if (!\is_string($localPath) || ($localPath !== '' && !str_starts_with($localPath, '/'))) {
                throw ApprovalFlowException::invalidNodeCompiler($compiler::class, 'Configuration errors must use a local JSON pointer.', $exception);
            }
            $reason = $exception instanceof ApprovalFlowException ? $parameters['reason'] : $exception->getMessage();

            throw ApprovalFlowException::invalidNodeConfiguration(
                $type,
                $version,
                $path . $localPath,
                $reason,
                $exception,
            );
        }

        return $configuration;
    }

    public function encode(string $type, string $version, NodeConfiguration $configuration): \stdClass
    {
        $compiler = $this->nodeCompilers->get($type, $version);
        try {
            // Freeze each result immediately: a plugin may reuse a mutable configuration object on its next call.
            $encoded = json_encode($configuration, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION);
            $decoded = $this->jsonDecoder->decode($encoded);
        } catch (\JsonException|HttpException $exception) {
            throw ApprovalFlowException::invalidNodeCompiler($compiler::class, 'Compiled configuration is not lossless object JSON.', $exception);
        }
        if (!$decoded instanceof \stdClass) {
            throw ApprovalFlowException::invalidNodeCompiler($compiler::class, 'Compiled configuration must be a JSON object.');
        }

        return $decoded;
    }
}
