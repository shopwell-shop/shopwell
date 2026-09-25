<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopwell\Core\Content\ImportExport\Struct\Config;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Salutation\SalutationCollection;
use Shopwell\Core\System\Salutation\SalutationDefinition;
use Symfony\Contracts\Service\ResetInterface;

#[Package('fundamentals@after-sales')]
class SalutationSerializer extends EntitySerializer implements ResetInterface
{
    /**
     * @var array<string>|null[]
     */
    private array $cacheSalutations = [];

    /**
     * @internal
     *
     * @param EntityRepository<SalutationCollection> $salutationRepository
     */
    public function __construct(private readonly EntityRepository $salutationRepository)
    {
    }

    public function deserialize(Config $config, EntityDefinition $definition, $entity)
    {
        $deserialized = parent::deserialize($config, $definition, $entity);

        $deserialized = \is_array($deserialized) ? $deserialized : iterator_to_array($deserialized);

        $context = Context::createDefaultContext();

        if (!isset($deserialized['id']) && isset($deserialized['salutationKey'])) {
            $id = $this->getSalutationId($deserialized['salutationKey'], $context);

            // if we dont find it by salutationKey, only set the id to the fallback if we dont have any other data
            if (!$id && \count($deserialized) === 1) {
                $id = $this->getSalutationId('not_specified', $context);
                unset($deserialized['salutationKey']);
            }

            if ($id) {
                $deserialized['id'] = $id;
            }
        }

        yield from $deserialized;
    }

    public function supports(string $entity): bool
    {
        return $entity === SalutationDefinition::ENTITY_NAME;
    }

    public function reset(): void
    {
        $this->cacheSalutations = [];
    }

    private function getSalutationId(string $salutationKey, Context $context): ?string
    {
        if (\array_key_exists($salutationKey, $this->cacheSalutations)) {
            return $this->cacheSalutations[$salutationKey];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salutationKey', $salutationKey));
        $this->cacheSalutations[$salutationKey] = $this->salutationRepository->searchIds(
            $criteria,
            $context
        )->firstId();

        return $this->cacheSalutations[$salutationKey];
    }
}
