<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\System\User\UserDefinition;

#[Package('framework')]
class AppLocaleProvider
{
    /**
     * @internal
     *
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        private readonly EntityRepository $userRepository,
        private readonly LanguageLocaleCodeProvider $languageLocaleProvider
    ) {
    }

    public function getLocaleFromContext(Context $context): string
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            return $this->languageLocaleProvider->getLocaleForLanguageId($context->getLanguageId());
        }

        $source = $context->getSource();

        if ($source->getUserId() === null) {
            return $this->languageLocaleProvider->getLocaleForLanguageId($context->getLanguageId());
        }

        $criteria = new Criteria([$source->getUserId()]);
        $criteria->addAssociation('locale');

        $user = $this->userRepository->search($criteria, $context)->getEntities()->first();

        if ($user === null) {
            throw new EntityNotFoundException(UserDefinition::ENTITY_NAME, $source->getUserId());
        }

        $locale = $user->getLocale();
        \assert($locale !== null);

        return $locale->getCode();
    }
}
