<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Store\InAppPurchase\Api;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\App\AppCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\Framework\Store\InAppPurchase;
use Shopwell\Core\Framework\Store\StoreException;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class InAppPurchasesController
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly InAppPurchase $inAppPurchase,
        private readonly EntityRepository $appRepository,
    ) {
    }

    #[Route(path: '/api/store/active-in-app-purchases', name: 'api.store.active-in-app-purchases', methods: ['GET'])]
    public function activeExtensionInAppPurchases(Context $context): JsonResponse
    {
        return new JsonResponse(
            [
                'inAppPurchases' => $this->inAppPurchase->getByExtension($this->getAppName($context)),
                'encodedInAppPurchases' => $this->inAppPurchase->getJWTByExtension($this->getAppName($context)) ?? [],
            ],
        );
    }

    #[Route(path: '/api/store/check-in-app-purchase-active', name: 'api.store.check-in-app-purchase-active', methods: ['POST'])]
    public function checkExtensionInAppPurchaseIsActive(RequestDataBag $request, Context $context): JsonResponse
    {
        $identifier = \trim($request->getString('identifier'));
        if (!$identifier) {
            throw StoreException::missingRequestParameter('identifier');
        }

        return new JsonResponse(
            ['isActive' => $this->inAppPurchase->isActive($this->getAppName($context), $identifier)]
        );
    }

    private function getAppName(Context $context): string
    {
        $source = $context->getSource();

        if (!$source instanceof AdminApiSource) {
            throw StoreException::invalidContextSource(AdminApiSource::class, $source::class);
        }

        if ($source->getIntegrationId() === null) {
            throw StoreException::missingIntegrationInContextSource($source::class);
        }

        $appId = $source->getIntegrationId();
        $app = $this->appRepository->search(new Criteria([$appId]), $context)->getEntities()->first();
        if (!$app) {
            throw StoreException::extensionNotFoundFromId($appId);
        }

        return $app->getName();
    }
}
