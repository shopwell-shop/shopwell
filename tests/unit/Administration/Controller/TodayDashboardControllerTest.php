<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Administration\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Administration\Controller\TodayDashboardController;
use Shopwell\Administration\Dashboard\TodayStatisticsService;
use Shopwell\Core\Framework\Api\ApiException;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\System\User\UserEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(TodayDashboardController::class)]
class TodayDashboardControllerTest extends TestCase
{
    public function testStatisticsUseTheAuthenticatedUsersConfiguredTimezone(): void
    {
        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $user->setTimeZone('America/New_York');
        $users = new StaticEntityRepository([new UserCollection([$user])]);
        $context = new Context(new AdminApiSource($user->getId()));
        $service = $this->createMock(TodayStatisticsService::class);
        $service->expects($this->once())->method('load')->with($context, 'America/New_York')->willReturn([]);
        $controller = new TodayDashboardController($service, $users);
        $controller->today($context);
        static::assertSame([$user->getId()], $users->searchedCriteria[0]->getIds());
    }

    public function testRequestWithoutALoggedInUserDoesNotLoadStatistics(): void
    {
        $users = StaticEntityRepository::of(UserCollection::class, []);
        $service = $this->createMock(TodayStatisticsService::class);
        $service->expects($this->never())->method('load');
        $controller = new TodayDashboardController($service, $users);
        $this->expectExceptionObject(ApiException::userNotLoggedIn());
        $controller->today(new Context(new AdminApiSource(null)));
    }
}
