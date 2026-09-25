<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Exception;

use Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\UuidException;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class InvalidEntityUuidException extends DataAbstractionLayerException
{
    public function __construct(string $uuid)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            'FRAMEWORK__INVALID_UUID',
            'Value is not a valid UUID: {{ uuid }}',
            ['uuid' => $uuid],
            UuidException::invalidUuid($uuid)
        );
    }
}
