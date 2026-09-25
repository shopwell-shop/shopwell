<?php declare(strict_types=1);

namespace Shopwell\Core\System\CustomField;

use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class CustomFieldException extends HttpException
{
    public const CUSTOM_FIELD_NAME_INVALID = 'CUSTOM_FIELD_NAME_INVALID';

    public static function customFieldNameInvalid(string $name): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOM_FIELD_NAME_INVALID,
            'Invalid field name: Only letters, numbers, or underscores are allowed, and it must start with a letter or underscore.',
            ['field' => 'name', 'value' => $name]
        );
    }
}
