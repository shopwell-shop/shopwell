<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopwell\Core\Content\ImportExport\Struct\Config;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Language\LanguageCollection;
use Shopwell\Core\System\Language\LanguageDefinition;
use Shopwell\Core\System\Language\LanguageEntity;
use Symfony\Contracts\Service\ResetInterface;

#[Package('fundamentals@after-sales')]
class LanguageSerializer extends EntitySerializer implements ResetInterface
{
    /**
     * @var array<string, array{id: string, locale: array{id: string}}|null>
     */
    private array $cacheLanguages = [];

    /**
     * @internal
     *
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    public function __construct(private readonly EntityRepository $languageRepository)
    {
    }

    public function deserialize(Config $config, EntityDefinition $definition, $entity)
    {
        $deserialized = parent::deserialize($config, $definition, $entity);

        $deserialized = \is_array($deserialized) ? $deserialized : iterator_to_array($deserialized);

        if (!isset($deserialized['id']) && isset($deserialized['locale']['code'])) {
            $language = $this->getLanguageSerialized($deserialized['locale']['code']);

            // if we dont find it by name, only set the id to the fallback if we dont have any other data
            if (!$language && \count($deserialized) === 1) {
                $deserialized['id'] = Defaults::LANGUAGE_SYSTEM;
                unset($deserialized['locale']);
            }

            if ($language) {
                $deserialized = array_merge($deserialized, $language);
            }
        }

        yield from $deserialized;
    }

    public function supports(string $entity): bool
    {
        return $entity === LanguageDefinition::ENTITY_NAME;
    }

    public function reset(): void
    {
        $this->cacheLanguages = [];
    }

    /**
     * @return array{id: string, locale: array{id: string}}|null
     */
    private function getLanguageSerialized(string $code): ?array
    {
        if (\array_key_exists($code, $this->cacheLanguages)) {
            return $this->cacheLanguages[$code];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('locale.code', $code));
        $criteria->addAssociation('locale');
        $language = $this->languageRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        $this->cacheLanguages[$code] = null;
        if ($language instanceof LanguageEntity && $language->getLocale() !== null) {
            $this->cacheLanguages[$code] = [
                'id' => $language->getId(),
                'locale' => ['id' => $language->getLocaleId()],
            ];
        }

        return $this->cacheLanguages[$code];
    }
}
