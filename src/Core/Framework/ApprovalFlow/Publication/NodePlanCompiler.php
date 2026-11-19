<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Publication;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeCompilerRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\NodeConfiguration;
use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @phpstan-import-type GraphPayload from GraphDiagnostics
 *
 * @internal
 */
#[Package('framework')]
class NodePlanCompiler
{
    public const ID = 'core:node-plan';

    public const VERSION = '1.0.0';

    public function __construct(
        private readonly GraphPlanCompiler $graphCompiler,
        private readonly NodeCompilerRegistry $nodeCompilers,
        private readonly GraphJsonDecoder $jsonDecoder,
    ) {
    }

    public function compile(DraftGraph $draft): CompiledNodePlan
    {
        $graphPlan = $this->graphCompiler->compile($draft);
        /** @var GraphPayload $payload */
        $payload = $this->jsonDecoder->decode($graphPlan->snapshot->graphJson);
        $nodes = [];
        foreach ($payload->nodes as $index => $node) {
            $compiler = $this->nodeCompilers->get($node->type, $node->typeVersion);
            $json = json_encode($node->config, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION);
            try {
                $configuration = $compiler->compile($node->type, $node->typeVersion, $json);
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
                    $node->type,
                    $node->typeVersion,
                    '/nodes/' . $index . '/config' . $localPath,
                    $reason,
                    $exception,
                );
            }
            $nodes[] = [
                'nodeId' => $node->id,
                'type' => $node->type,
                'typeVersion' => $node->typeVersion,
                'configuration' => $this->freezeConfiguration($compiler, $configuration),
            ];
        }

        $json = json_encode([
            'formatVersion' => '1.0.0',
            'compilerId' => self::ID,
            'compilerVersion' => self::VERSION,
            'graphPlanHash' => $graphPlan->hash,
            'nodes' => $nodes,
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION);

        return new CompiledNodePlan($graphPlan, $json);
    }

    private function freezeConfiguration(NodeCompiler $compiler, NodeConfiguration $configuration): \stdClass
    {
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
