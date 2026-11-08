<?php declare(strict_types=1);

namespace Shopwell\Core\System\Unit\Api;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\Unit\UnitUneceCodes;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('inventory')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class UnitUneceCodeController
{
    public function __construct(private readonly UnitUneceCodes $uneceCodes)
    {
    }

    #[Route(path: '/api/_action/unit/unece-codes', name: 'api.action.unit.unece-codes', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['unit:read']], methods: ['GET'])]
    public function getUneceCodes(): JsonResponse
    {
        $codes = [];
        foreach ($this->uneceCodes->getCodes() as $code => $name) {
            $codes[] = ['code' => (string) $code, 'name' => $name];
        }

        return new JsonResponse($codes);
    }
}
