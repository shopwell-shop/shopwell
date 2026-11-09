<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Validation\Error;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ContentSystemStyleOptionSchemaError implements Error
{
    private readonly string $message;

    public function __construct(string $filename, string $reason)
    {
        $this->message = \sprintf(
            'Invalid style option schema in "%s": %s',
            $filename,
            $reason
        );
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
