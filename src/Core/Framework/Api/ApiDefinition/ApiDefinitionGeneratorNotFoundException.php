<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\ApiDefinition;

use Shopwell\Core\Framework\Api\ApiException;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - Will be removed. Use {@see ApiException::apiDefinitionGeneratorNotFound} instead
 */
#[Package('framework')]
class ApiDefinitionGeneratorNotFoundException extends ShopwellHttpException
{
    public function __construct(string $format)
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct(
            'A definition generator for format "{{ format }}" was not found.',
            ['format' => $format]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__API_DEFINITION_GENERATOR_NOT_SUPPORTED';
    }
}
