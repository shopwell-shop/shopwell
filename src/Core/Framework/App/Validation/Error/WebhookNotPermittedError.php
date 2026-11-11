<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Validation\Error;

use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class WebhookNotPermittedError implements Error
{
    private readonly string $message;

    /**
     * @param list<string> $violations
     */
    public function __construct(array $violations)
    {
        $this->message = \sprintf(
            "This app is not permitted to subscribe to the following webhooks:\n- %s",
            implode("\n- ", $violations)
        );
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getErrorCode(): string
    {
        return AppException::VALIDATION_FAILED;
    }

    public function getParameters(): array
    {
        return [];
    }
}
