<?php declare(strict_types=1);

namespace Shopwell\Storefront\Theme\Exception;

use Shopwell\Core\Framework\Api\EventListener\ErrorResponseFactory;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('discovery')]
class ThemeConfigException extends ShopwellHttpException
{
    private const MESSAGE = 'There are {{ errorCount }} error(s) while validating the theme config.';

    /**
     * @var list<\Throwable>
     */
    private array $exceptions = [];

    public function __construct()
    {
        parent::__construct(self::MESSAGE, ['errorCount' => 0]);
    }

    public function getErrorCode(): string
    {
        return 'THEME_CONFIG_EXCEPTION';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function add(\Throwable $exception): ThemeConfigException
    {
        $this->exceptions[] = $exception;
        $this->updateMessage();

        return $this;
    }

    public function tryToThrow(): void
    {
        if ($this->exceptions !== []) {
            throw $this;
        }
    }

    public function getErrors(bool $withTrace = false): \Generator
    {
        $errorFactory = new ErrorResponseFactory();

        foreach ($this->getExceptions() as $innerException) {
            if ($innerException instanceof ShopwellHttpException) {
                yield from $innerException->getErrors($withTrace);

                continue;
            }

            yield from $errorFactory->getErrorsFromException($innerException, $withTrace);
        }
    }

    /**
     * @return list<\Throwable>
     */
    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    private function updateMessage(): void
    {
        $this->message = $this->parse(self::MESSAGE, ['errorCount' => \count($this->exceptions)]);
    }
}
