<?php

declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Exception;

use Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class PropertyNotFoundException extends DataAbstractionLayerException
{
    public function __construct(string $property, string $entityClassName)
    {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PROPERTY_NOT_FOUND,
            'Property "{{ property }}" does not exist in entity "{{ entityClassName }}".',
            ['property' => $property, 'entityClassName' => $entityClassName]
        );
    }
}
