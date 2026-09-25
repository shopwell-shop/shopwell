<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Store\Authentication;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Services\FirstRunWizardService;
use Shopwell\Core\System\User\Aggregate\UserConfig\UserConfigCollection;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class FrwRequestOptionsProvider extends AbstractStoreRequestOptionsProvider
{
    private const SHOPWELL_TOKEN_HEADER = 'X-Shopwell-Token';

    /**
     * @param EntityRepository<UserConfigCollection> $userConfigRepository
     */
    public function __construct(
        private readonly AbstractStoreRequestOptionsProvider $optionsProvider,
        private readonly EntityRepository $userConfigRepository,
    ) {
    }

    public function getAuthenticationHeader(Context $context): array
    {
        return array_filter([self::SHOPWELL_TOKEN_HEADER => $this->getFrwUserToken($context)]);
    }

    public function getDefaultQueryParameters(Context $context): array
    {
        return $this->optionsProvider->getDefaultQueryParameters($context);
    }

    private function getFrwUserToken(Context $context): ?string
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw new InvalidContextSourceException(AdminApiSource::class, $context->getSource()::class);
        }

        /** @var AdminApiSource $contextSource */
        $contextSource = $context->getSource();

        $criteria = (new Criteria())->addFilter(
            new EqualsFilter('userId', $contextSource->getUserId()),
            new EqualsFilter('key', FirstRunWizardService::USER_CONFIG_KEY_FRW_USER_TOKEN),
        );

        $userConfig = $this->userConfigRepository->search($criteria, $context)->getEntities()->first();

        return $userConfig === null ? null : $userConfig->getValue()[FirstRunWizardService::USER_CONFIG_VALUE_FRW_USER_TOKEN] ?? null;
    }
}
