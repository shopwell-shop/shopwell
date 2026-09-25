<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Controller;

use Shopwell\Core\Content\Flow\FlowException;
use Shopwell\Core\Framework\App\Aggregate\FlowEvent\AppFlowEventCollection;
use Shopwell\Core\Framework\App\Event\CustomAppEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Package('after-sales')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class TriggerFlowController extends AbstractController
{
    /**
     * @internal
     *
     * @param EntityRepository<AppFlowEventCollection> $appFlowEventRepository
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EntityRepository $appFlowEventRepository,
    ) {
    }

    #[Route(path: '/api/_action/trigger-event/{eventName}', name: 'api.action.trigger_event', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['flow:dispatch']], methods: ['POST'])]
    public function trigger(string $eventName, Request $request, Context $context): JsonResponse
    {
        $data = $request->request->all();

        $this->checkAppEventIsExist($eventName, $context);

        $this->eventDispatcher->dispatch(new CustomAppEvent($eventName, $data, $context), $eventName);

        return new JsonResponse([
            'message' => \sprintf('The trigger `%s`successfully dispatched!', $eventName),
        ], Response::HTTP_OK);
    }

    private function checkAppEventIsExist(string $eventName, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('name', $eventName));
        $criteria->addFilter(new EqualsFilter('app.active', 1));

        $this->appFlowEventRepository->search($criteria, $context)->getEntities()->first() ?? throw FlowException::customTriggerByNameNotFound($eventName);
    }
}
