<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Validation\Error;

use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class IncompatibleAppError implements Error
{
    private readonly string $message;

    public function __construct(private readonly string $appName)
    {
        $this->message = \sprintf('App %s is not compatible with this Shopwell version', $appName);
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getErrorCode(): string
    {
        return AppException::NOT_COMPATIBLE;
    }

    public function getParameters(): array
    {
        return ['name' => $this->appName];
    }
}
