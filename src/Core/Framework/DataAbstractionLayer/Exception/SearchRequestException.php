<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellException;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @phpstan-type SearchErrorData array{
 *     status: string,
 *     code: string,
 *     title: string,
 *     detail: string,
 *     source: array{pointer: string},
 *     meta: array{parameters: array<string, mixed>},
 *     trace?: string
 * }
 */
#[Package('framework')]
class SearchRequestException extends ShopwellHttpException
{
    /**
     * @param array<string, list<\Throwable>> $exceptions
     */
    public function __construct(private array $exceptions = [])
    {
        parent::__construct('Mapping failed, got {{ numberOfFailures }} failure(s).', ['numberOfFailures' => \count($exceptions)]);
    }

    public function add(\Throwable $exception, string $pointer): void
    {
        $this->exceptions[$pointer][] = $exception;
        $this->parameters['numberOfFailures'] = \count($this->exceptions);
        $this->message = $this->parse('Mapping failed, got {{ numberOfFailures }} failure(s).', $this->parameters);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function tryToThrow(): void
    {
        if ($this->exceptions === []) {
            return;
        }

        throw $this;
    }

    /**
     * @return \Generator<SearchErrorData>
     */
    public function getErrors(bool $withTrace = false): \Generator
    {
        foreach ($this->exceptions as $pointer => $innerExceptions) {
            foreach ($innerExceptions as $exception) {
                $parameters = [];
                $code = (string) $exception->getCode();
                if ($exception instanceof ShopwellException) {
                    $parameters = $exception->getParameters();
                    $code = $exception->getErrorCode();
                }

                $error = [
                    'status' => (string) $this->getStatusCode(),
                    'code' => $code,
                    'title' => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
                    'detail' => $exception->getMessage(),
                    'source' => ['pointer' => $pointer],
                    'meta' => [
                        'parameters' => $parameters,
                    ],
                ];

                if ($withTrace) {
                    $error['trace'] = $exception->getTraceAsString();
                }

                yield $error;
            }
        }
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__SEARCH_REQUEST_MAPPING';
    }
}
