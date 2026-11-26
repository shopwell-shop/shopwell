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
class UnsupportedSectionError implements Error
{
    public function __construct(private readonly string $section)
    {
    }

    public function getMessage(): string
    {
        return \sprintf('Section <%s> is not supported by this Shopwell version', $this->section);
    }

    public function getErrorCode(): string
    {
        return AppException::VALIDATION_FAILED;
    }

    public function getParameters(): array
    {
        return ['section' => $this->section];
    }

    public function getSeverity(): Severity
    {
        return Severity::Notice;
    }
}
