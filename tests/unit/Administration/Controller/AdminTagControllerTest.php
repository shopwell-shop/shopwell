<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Administration\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Administration\Controller\AdminTagController;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Tag\Service\FilterTagIdsService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AdminTagController::class)]
class AdminTagControllerTest extends TestCase
{
    public function testFilterIds(): void
    {
        $filterTagIdsService = static::createStub(FilterTagIdsService::class);
        $controller = new AdminTagController($filterTagIdsService);

        $response = $controller->filterIds(new Request(), new Criteria(), Context::createDefaultContext());
        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"total":0,"ids":[]}', $response->getContent());
    }
}
