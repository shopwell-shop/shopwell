<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\App\Api;

use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppDto;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentAppProvisioner;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Onboards the external apps that call the payment api.
 *
 * The list is not limited to those apps: the tenant of the shop itself is part of it, because a
 * merchant has to see through which account the platform collects its own payments. That row is
 * read only - it is seeded by the migration, cannot be created here and is refused on every write.
 *
 * An app is an entity like any other, but its secret is not: storing it through the generic entity
 * endpoints would store it in plain text, so the secret is sealed by the {@see PaymentAppProvisioner}
 * with the same cipher the application verifies the signatures with and is handed out exactly once,
 * when it is created or rotated. Reading an app never returns it - the administration only learns
 * whether one is set.
 *
 * The controller only maps the request onto the {@see PaymentAppDto} and its answer back into the
 * response; the writing itself lives in the provisioner.
 *
 * @internal
 */
#[Package('framework')]
class PaymentAppController extends AbstractController
{
    /**
     * @param EntityRepository<PaymentAppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly PaymentAppProvisioner $provisioner,
    ) {
    }

    #[Route(
        path: '/api/_action/payment/apps',
        name: 'api.action.payment.apps',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'],
            PlatformRequest::ATTRIBUTE_ACL => ['payment_app:read'],
        ],
        methods: [Request::METHOD_GET]
    )]
    public function list(): JsonResponse
    {
        // the tenant of the platform is listed on purpose: a merchant has to see through which
        // account the platform itself collects, otherwise the list looks like nothing could collect
        // at all. It is read only, but that is not enforced here - the guard lives in the provisioner,
        // which is the only writer, so every write path is covered and not just this endpoint.
        $criteria = new Criteria();
        $criteria->addAssociation('providers');
        $criteria->addAssociation('channels');

        $entities = [];
        foreach ($this->appRepository->search($criteria, Context::createDefaultContext())->getEntities() as $app) {
            if ($app instanceof PaymentAppEntity) {
                $entities[] = $app;
            }
        }

        // the order is built here and not in the criteria: "the tenant of the platform first" is not
        // a field order, and the platform row is the one an operator looks for first - the rest keeps
        // the technical name order it is addressable by
        usort($entities, static function (PaymentAppEntity $left, PaymentAppEntity $right): int {
            $leftIsPlatform = $left->type === PaymentAppEntity::TYPE_PLATFORM;
            $rightIsPlatform = $right->type === PaymentAppEntity::TYPE_PLATFORM;

            if ($leftIsPlatform !== $rightIsPlatform) {
                return $leftIsPlatform ? -1 : 1;
            }

            return strcmp($left->technicalName, $right->technicalName);
        });

        $apps = [];
        foreach ($entities as $app) {
            $apps[] = $this->present($app);
        }

        return new JsonResponse(['apps' => $apps]);
    }

    #[Route(
        path: '/api/_action/payment/apps',
        name: 'api.action.payment.app.save',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'],
            PlatformRequest::ATTRIBUTE_ACL => ['payment_app:update'],
        ],
        methods: [Request::METHOD_POST]
    )]
    public function save(#[MapRequestPayload] PaymentAppDto $dto): JsonResponse
    {
        $result = $this->provisioner->save($dto);

        $response = ['app' => $this->present($result['app'])];

        if ($result['secret'] !== '') {
            // returned once: whoever calls this is the only one who ever sees the plain secret
            $response['appSecret'] = $result['secret'];
            $response['secretGenerated'] = $result['generated'];
        }

        return new JsonResponse($response);
    }

    #[Route(
        path: '/api/_action/payment/apps/{id}',
        name: 'api.action.payment.app.delete',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'],
            PlatformRequest::ATTRIBUTE_ACL => ['payment_app:delete'],
        ],
        methods: [Request::METHOD_DELETE]
    )]
    public function delete(string $id): JsonResponse
    {
        // the deletion is not done here: the provisioner is the one writer of an app and the one
        // place that knows which rows may not be touched at all
        $this->provisioner->delete($id, Context::createDefaultContext());

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PaymentAppEntity $app): array
    {
        $providers = [];
        foreach ($app->providers ?? [] as $provider) {
            if (!$provider instanceof PaymentAppProviderEntity) {
                continue;
            }

            $providers[] = [
                'providerTechnicalName' => $provider->providerTechnicalName,
                'enabled' => $provider->enabled,
                'mode' => $provider->mode,
                // the credentials themselves stay on the server, the administration only renders
                // "configured / not configured"
                'hasCredentials' => $provider->credentials !== null && $provider->credentials !== [],
                'settings' => $provider->settings ?? [],
            ];
        }

        $channels = [];
        foreach ($app->channels ?? [] as $channel) {
            if (!$channel instanceof PaymentAppChannelEntity) {
                continue;
            }

            $channels[] = [
                'channelTechnicalName' => $channel->channelTechnicalName,
                'enabled' => $channel->enabled,
                'hasParams' => $channel->params !== null && $channel->params !== [],
                'settings' => $channel->settings ?? [],
            ];
        }

        return [
            'id' => $app->id,
            'technicalName' => $app->technicalName,
            'name' => $app->name,
            // the administration renders the tenant of the platform as a read only row, so it has to
            // know which one it is - the type is the only difference between the two kinds of row
            'type' => $app->type,
            'active' => $app->active,
            'notifyUrl' => $app->notifyUrl,
            'returnUrl' => $app->returnUrl,
            'allowedIps' => $app->allowedIps ?? [],
            'rateLimit' => $app->rateLimit,
            'hasSecret' => $app->appSecret !== '',
            // the moment of the last rotation tells the administration until when the previous
            // secret still signs a request
            'secretRotatedAt' => $app->secretRotatedAt?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'providers' => $providers,
            'channels' => $channels,
        ];
    }
}
