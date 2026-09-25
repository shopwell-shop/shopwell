<?php declare(strict_types=1);

namespace Shopwell\Core\System\CustomField\Api;

use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class CustomFieldSetActionController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(private readonly DefinitionInstanceRegistry $definitionRegistry)
    {
    }

    #[Route(path: '/api/_action/attribute-set/relations', name: 'api.action.attribute-set.get-relations', methods: ['GET'])]
    public function getAvailableRelations(): JsonResponse
    {
        $definitions = $this->definitionRegistry->getDefinitions();

        $entityNames = [];
        foreach ($definitions as $definition) {
            if (\count($definition->getFields()->filterInstance(CustomFields::class)) === 0) {
                continue;
            }
            if ($definition instanceof EntityTranslationDefinition) {
                $definition = $definition->getParentDefinition();
            }
            $entityNames[] = $definition->getEntityName();
        }
        sort($entityNames);

        return new JsonResponse($entityNames);
    }
}
