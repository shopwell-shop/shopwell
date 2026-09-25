<?php declare(strict_types=1);

namespace Shopwell\Core\System\CustomEntity\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\CustomEntity\CustomEntityException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class CustomEntityXmlParsingException extends CustomEntityException
{
    public function __construct(
        string $xmlFile,
        string $message
    ) {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::XML_PARSE_ERROR,
            'Unable to parse file "{{ file }}". Message: {{ message }}',
            ['file' => $xmlFile, 'message' => $message]
        );
    }
}
