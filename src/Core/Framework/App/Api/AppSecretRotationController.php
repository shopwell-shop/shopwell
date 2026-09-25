<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Api;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\App\AppCollection;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class AppSecretRotationController
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly AppSecretRotationService $rotationService
    ) {
    }

    #[Route(path: '/api/_action/app-system/secret/rotate', name: 'api.action.app.secret.rotate', methods: ['POST'])]
    public function rotate(Context $context): JsonResponse
    {
        $integrationId = $this->extractIntegrationIdOrFail($context);
        $app = $this->loadAppByIntegrationId($integrationId, $context);

        if ($app === null) {
            throw AppException::notFoundByField($integrationId, 'integrationId');
        }

        $this->rotationService->scheduleRotation($app, AppSecretRotationService::TRIGGER_API);

        return new JsonResponse([], Response::HTTP_ACCEPTED);
    }

    private function loadAppByIntegrationId(string $integrationId, Context $context): ?AppEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('integrationId', $integrationId));
        $criteria->addAssociation('integration');

        return $this->appRepository->search($criteria, $context)->getEntities()->first();
    }

    private function extractIntegrationIdOrFail(Context $context): string
    {
        $source = $context->getSource();

        if (!$source instanceof AdminApiSource) {
            throw AppException::invalidArgument('Secret rotation requires an Admin API source with integration authentication.');
        }

        $integrationId = $source->getIntegrationId();

        if ($integrationId === null) {
            throw AppException::invalidArgument('Secret rotation requires authentication via an app integration.');
        }

        return $integrationId;
    }
}
