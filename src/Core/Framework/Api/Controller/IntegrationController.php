<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Controller\Exception\PermissionDeniedException;
use Shopwell\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\Integration\IntegrationCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Package('fundamentals@framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class IntegrationController extends AbstractController
{
    /**
     * @internal
     *
     * @param EntityRepository<IntegrationCollection> $integrationRepository
     */
    public function __construct(
        private readonly EntityRepository $integrationRepository,
        private readonly Connection $connection,
    ) {
    }

    #[Route(
        path: '/api/integration',
        name: 'api.integration.create',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['integration:create']],
        methods: [Request::METHOD_POST]
    )]
    public function upsertIntegration(
        ?string $integrationId,
        Request $request,
        Context $context,
        ResponseFactoryInterface $factory
    ): Response {
        $source = $context->getSource();

        $data = $request->request->all();
        $admin = $data['admin'] ?? null;
        $changesAdmin = isset($data['admin']);
        unset($data['admin']);

        // only an admin is allowed to set the admin field
        if ((!$source instanceof AdminApiSource)
            || (!$source->isAdmin()
            && $changesAdmin)
        ) {
            throw new PermissionDeniedException();
        }

        $entityId = $integrationId ?? $data['id'] ?? Uuid::randomHex();
        \assert(\is_string($entityId));
        $data['id'] = $entityId;

        $this->connection->transactional(function () use ($data, $context, $changesAdmin, $admin, $entityId): void {
            $this->integrationRepository->upsert([$data], $context);

            if ($changesAdmin) {
                $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($entityId, $admin): void {
                    $this->integrationRepository->update([['id' => $entityId, 'admin' => $admin]], $context);
                });
            }
        });

        return $factory->createRedirectResponse($this->integrationRepository->getDefinition(), $entityId, $request, $context);
    }

    #[Route(
        path: '/api/integration/{integrationId}',
        name: 'api.integration.update',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['integration:update']],
        methods: [Request::METHOD_PATCH]
    )]
    public function updateIntegration(
        ?string $integrationId,
        Request $request,
        Context $context,
        ResponseFactoryInterface $factory
    ): Response {
        return $this->upsertIntegration($integrationId, $request, $context, $factory);
    }
}
