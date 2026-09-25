<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Api;

use Shopwell\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class AppCmsController extends AbstractController
{
    /**
     * @param EntityRepository<AppCmsBlockCollection> $cmsBlockRepository
     */
    public function __construct(private readonly EntityRepository $cmsBlockRepository)
    {
    }

    #[Route(path: 'api/app-system/cms/blocks', name: 'api.app_system.cms.blocks', methods: ['GET'])]
    public function getBlocks(Context $context): Response
    {
        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('app.active', true))
            ->addSorting(new FieldSorting('name'));
        $blocks = $this->cmsBlockRepository->search($criteria, $context)->getEntities();

        return new JsonResponse(['blocks' => $this->formatBlocks($blocks)]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function formatBlocks(AppCmsBlockCollection $blocks): array
    {
        $formattedBlocks = [];

        foreach ($blocks as $block) {
            $formattedBlock = $block->getBlock();
            $formattedBlock['template'] = $block->getTemplate();
            $formattedBlock['styles'] = $block->getStyles();

            $formattedBlocks[] = $formattedBlock;
        }

        return $formattedBlocks;
    }
}
