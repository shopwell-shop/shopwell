<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\App\Api\AppSecretRotationController;
use Shopwell\Core\Framework\App\AppCollection;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppSecretRotationController::class)]
class AppSecretRotationControllerTest extends TestCase
{
    public function testRotateSchedulesRotationAndReturns202(): void
    {
        $integrationId = Uuid::randomHex();
        $appId = Uuid::randomHex();

        $source = new AdminApiSource(null, $integrationId);
        $context = new Context($source);

        $app = new AppEntity();
        $app->setId($appId);

        $appRepository = $this->createMock(EntityRepository::class);
        $rotationService = $this->createMock(AppSecretRotationService::class);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('integrationId', $integrationId));

        $searchResult = new EntitySearchResult(
            'app',
            1,
            new AppCollection([$app]),
            null,
            $criteria,
            $context
        );

        $appRepository->expects($this->once())
            ->method('search')
            ->with(static::callback(function (Criteria $crit) use ($integrationId): bool {
                $filters = $crit->getFilters();

                return \count($filters) === 1
                    && $filters[0] instanceof EqualsFilter
                    && $filters[0]->getField() === 'integrationId'
                    && $filters[0]->getValue() === $integrationId;
            }), $context)
            ->willReturn($searchResult);

        $rotationService->expects($this->once())
            ->method('scheduleRotation')
            ->with($app, AppSecretRotationService::TRIGGER_API);

        $controller = new AppSecretRotationController($appRepository, $rotationService);
        $response = $controller->rotate($context);

        static::assertSame(202, $response->getStatusCode());
    }

    public function testRotateThrowsAccessDeniedWhenContextHasNoAdminApiSource(): void
    {
        $context = Context::createDefaultContext();

        $appRepository = $this->createMock(EntityRepository::class);
        $rotationService = $this->createMock(AppSecretRotationService::class);

        $appRepository->expects($this->never())
            ->method('search');

        $rotationService->expects($this->never())
            ->method('scheduleRotation');

        $controller = new AppSecretRotationController($appRepository, $rotationService);

        $this->expectExceptionObject(AppException::invalidArgument('Secret rotation requires an Admin API source with integration authentication.'));

        $controller->rotate($context);
    }

    public function testRotateThrowsAccessDeniedWhenIntegrationIdIsMissing(): void
    {
        $source = new AdminApiSource(null, null);
        $context = new Context($source);

        $appRepository = $this->createMock(EntityRepository::class);
        $rotationService = $this->createMock(AppSecretRotationService::class);

        $appRepository->expects($this->never())
            ->method('search');

        $rotationService->expects($this->never())
            ->method('scheduleRotation');

        $controller = new AppSecretRotationController($appRepository, $rotationService);

        $this->expectExceptionObject(AppException::invalidArgument('Secret rotation requires authentication via an app integration.'));

        $controller->rotate($context);
    }

    public function testRotateThrowsNotFoundWhenAppDoesNotExist(): void
    {
        $integrationId = Uuid::randomHex();

        $source = new AdminApiSource(null, $integrationId);
        $context = new Context($source);

        $appRepository = $this->createMock(EntityRepository::class);
        $rotationService = $this->createMock(AppSecretRotationService::class);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('integrationId', $integrationId));

        $searchResult = new EntitySearchResult(
            'app',
            0,
            new AppCollection([]),
            null,
            $criteria,
            $context
        );

        $appRepository->expects($this->once())
            ->method('search')
            ->with(static::callback(function (Criteria $crit) use ($integrationId): bool {
                $filters = $crit->getFilters();

                return \count($filters) === 1
                    && $filters[0] instanceof EqualsFilter
                    && $filters[0]->getField() === 'integrationId'
                    && $filters[0]->getValue() === $integrationId;
            }), $context)
            ->willReturn($searchResult);

        $rotationService->expects($this->never())
            ->method('scheduleRotation');

        $controller = new AppSecretRotationController($appRepository, $rotationService);

        $this->expectExceptionObject(AppException::notFoundByField($integrationId, 'integrationId'));

        $controller->rotate($context);
    }
}
