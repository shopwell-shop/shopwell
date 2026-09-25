<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class RestrictDeleteViolationException extends ShopwellHttpException
{
    /**
     * @var RestrictDeleteViolation[]
     */
    private readonly array $restrictions;

    /**
     * @param RestrictDeleteViolation[] $restrictions
     */
    public function __construct(
        EntityDefinition $definition,
        array $restrictions
    ) {
        $restriction = $restrictions[0];
        $usages = [];
        $usagesStrings = [];

        foreach ($restriction->getRestrictions() as $entityName => $rows) {
            $name = $entityName;
            $usages[] = [
                'entityName' => $name,
                'count' => \count($rows),
            ];
            $usagesStrings[] = \sprintf('%s (%d)', $name, \count($rows));
        }

        $this->restrictions = $restrictions;

        $metaData = array_merge(
            ...array_map(static fn (RestrictDeleteViolation $violation) => $violation->getRestrictions(), $restrictions)
        );

        parent::__construct(
            'The delete request for {{ entity }} was denied due to a conflict. The entity is currently in use by: {{ usagesString }}',
            ['entity' => $definition->getEntityName(), 'usagesString' => implode(', ', $usagesStrings), 'usages' => $usages, 'metaData' => $metaData]
        );
    }

    /**
     * @return RestrictDeleteViolation[]
     */
    public function getRestrictions(): array
    {
        return $this->restrictions;
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_CONFLICT;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__DELETE_RESTRICTED';
    }
}
