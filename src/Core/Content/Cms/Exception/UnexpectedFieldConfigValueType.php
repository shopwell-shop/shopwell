<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Cms\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;

#[Package('discovery')]
class UnexpectedFieldConfigValueType extends ShopwellHttpException
{
    public function __construct(
        string $fieldConfigName,
        string $expectedType,
        string $givenType
    ) {
        parent::__construct(
            'Expected to load value of "{{ fieldConfigName }}" with type "{{ expectedType }}", but value with type "{{ givenType }}" given.',
            [
                'fieldConfigName' => $fieldConfigName,
                'expectedType' => $expectedType,
                'givenType' => $givenType,
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__CMS_UNEXPECTED_VALUE_TYPE';
    }
}
