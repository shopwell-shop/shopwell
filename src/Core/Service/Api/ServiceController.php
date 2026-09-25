<?php declare(strict_types=1);

namespace Shopwell\Core\Service\Api;

use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\ApiException;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\App\Privileges\Utils;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\Service\DTO\Service;
use Shopwell\Core\Service\LifecycleManager;
use Shopwell\Core\Service\Message\UpdateServiceMessage;
use Shopwell\Core\Service\Requirement\RequirementsValidator;
use Shopwell\Core\Service\ServiceException;
use Shopwell\Core\Service\ServiceLifecycle;
use Shopwell\Core\Service\ServiceStorage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal only for use by the service-system
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ServiceController
{
    public function __construct(
        private readonly ServiceStorage $serviceStorage,
        private readonly MessageBusInterface $messageBus,
        private readonly ServiceLifecycle $serviceLifecycle,
        private readonly LifecycleManager $manager,
        private readonly RequirementsValidator $requirementsValidator,
    ) {
    }

    #[Route(
        path: 'api/services/trigger-update',
        name: 'api.services.trigger-update',
        methods: [Request::METHOD_POST]
    )]
    public function triggerUpdate(Context $context): Response
    {
        $integrationId = $this->extractIntegrationIdOrFail($context);

        $app = $this->loadService($context);

        if (!$app) {
            throw ServiceException::notFound('integrationId', $integrationId);
        }

        $this->messageBus->dispatch(new UpdateServiceMessage($app->name));

        return new JsonResponse([]);
    }

    #[Route(
        path: '/api/service/activate/{serviceName}',
        name: 'api.service.activate',
        defaults: [
            'auth_required' => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function activate(string $serviceName, Context $context): JsonResponse
    {
        $this->validateActivationAccess($context);

        $this->serviceLifecycle->activate($serviceName, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/service/deactivate/{serviceName}',
        name: 'api.service.deactivate',
        defaults: [
            'auth_required' => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function deactivate(string $serviceName, Context $context): JsonResponse
    {
        $this->validateActivationAccess($context);

        $this->serviceLifecycle->deactivate($serviceName, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/service/uninstall/{serviceName}',
        name: 'api.service.uninstall',
        defaults: [
            'auth_required' => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function uninstall(string $serviceName, Context $context): JsonResponse
    {
        $integrationId = $this->extractIntegrationIdOrFail($context);
        $service = $this->serviceStorage->findByNameAndIntegrationId($serviceName, $integrationId, $context);

        if (!$service) {
            throw ServiceException::notFound('name', $serviceName);
        }

        $this->serviceLifecycle->uninstall($service->name, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/service/list',
        name: 'api.service.list',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['system.plugin_maintain'],
        ],
        methods: [Request::METHOD_GET]
    )]
    public function list(Context $context): JsonResponse
    {
        return new JsonResponse($this->loadAllServices($context));
    }

    #[Route(
        path: '/api/services/disable',
        name: 'api.services.disable',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['system.plugin_maintain'],
        ],
        methods: [Request::METHOD_POST]
    )]
    public function disableServices(Context $context): Response
    {
        $this->manager->disable($context);

        return new Response();
    }

    #[Route(
        path: '/api/services/enable',
        name: 'api.services.enable',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['system.plugin_maintain'],
        ],
        methods: [Request::METHOD_POST]
    )]
    public function enableServices(): Response
    {
        $this->manager->enable();

        return new Response();
    }

    #[Route(
        path: '/api/services/categorized-permissions/{serviceName}',
        name: 'api.services.categorized_permissions',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['system.plugin_maintain'],
        ],
        methods: [Request::METHOD_GET]
    )]
    public function categorizedPermissions(string $serviceName, Context $context): Response
    {
        $service = $this->serviceStorage->findByName($serviceName, $context);

        if ($service === null) {
            throw ServiceException::notFound('name', $serviceName);
        }

        return new JsonResponse([
            'permissions' => Utils::makeCategorizedPermissions($service->getAllPrivileges()),
        ]);
    }

    /**
     * @return list<array{id: string, name: string, label: string, active: bool, icon: string|null, description: string|null, updated_at: string|null, version: string, requested_privileges: list<string>, privileges: list<string>, state: string, domains: list<string>, requirements: list<string>, state_change_permitted: bool}>
     */
    private function loadAllServices(Context $context): array
    {
        return array_map(fn (Service $service) => [
            'id' => $service->id,
            'name' => $service->name,
            'label' => $service->label,
            'active' => $service->active,
            'icon' => $service->icon,
            'description' => $service->description,
            'updated_at' => $service->updatedAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'version' => $service->version,
            'requested_privileges' => $service->requestedPrivileges,
            'privileges' => $service->privileges,
            'state' => $service->state->value,
            'domains' => $service->domains,
            'requirements' => $service->requirements,
            'state_change_permitted' => $this->requirementsValidator->permitsStateChange($service->requirements),
        ], $this->serviceStorage->findAll($context));
    }

    private function loadService(Context $context): ?Service
    {
        $source = $context->getSource();
        \assert($source instanceof AdminApiSource);

        return $this->serviceStorage->findByIntegrationId((string) $source->getIntegrationId(), $context);
    }

    private function validateActivationAccess(Context $context): void
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw ServiceException::updateRequiresAdminApiSource($source);
        }

        if ($source->getIntegrationId() !== null) {
            if ($context->isAllowed('api_service_toggle')) {
                return;
            }

            throw ApiException::missingPrivileges(['api_service_toggle']); // @phpstan-ignore shopwell.domainException (Same exception as route ACL listener)
        }

        if ($context->isAllowed('system.plugin_maintain')) {
            return;
        }

        throw ApiException::missingPrivileges(['system.plugin_maintain']); // @phpstan-ignore shopwell.domainException (Same exception as route ACL listener)
    }

    private function extractIntegrationIdOrFail(Context $context): string
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw ServiceException::updateRequiresAdminApiSource($source);
        }

        $integrationId = $source->getIntegrationId();
        if (!$integrationId) {
            throw ServiceException::updateRequiresIntegration();
        }

        return $integrationId;
    }
}
