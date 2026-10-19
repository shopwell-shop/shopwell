<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Template;

use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateApprovalStatus;
use Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping\SmsTemplateMappingCollection;
use Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping\SmsTemplateMappingEntity;

/**
 * Finds the mapping row of one (template, provider, account?) combination.
 *
 * A row of the account wins over a row that holds for every account of the provider, that is the
 * whole chain - there is no per language row and no fallback between languages in this round.
 *
 * The unique key of the table counts a null gateway_id as a row of its own, so a repeated or a
 * concurrent write can leave several rows for the same combination. The lookup therefore picks
 * deterministically: an approved row first, then the most recently updated one, then the lowest id,
 * so the routing and the audit converge on the same row instead of alternating between duplicates.
 *
 * @internal
 */
#[Package('framework')]
final class SmsTemplateMappingLocator
{
    /**
     * @param EntityRepository<SmsTemplateMappingCollection> $mappingRepository
     */
    public function __construct(private readonly EntityRepository $mappingRepository)
    {
    }

    public function locate(
        Context $context,
        string $templateId,
        string $technicalName,
        ?string $gatewayId,
    ): ?SmsTemplateMappingEntity {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('templateId', $templateId),
            new EqualsFilter('technicalName', $technicalName),
        );

        $ofAccount = [];
        $ofProvider = [];

        foreach ($this->mappingRepository->search($criteria, $context)->getEntities() as $mapping) {
            \assert($mapping instanceof SmsTemplateMappingEntity);

            if ($gatewayId !== null && $mapping->gatewayId === $gatewayId) {
                $ofAccount[] = $mapping;

                continue;
            }

            if ($mapping->gatewayId === null) {
                $ofProvider[] = $mapping;
            }
        }

        return $this->pick($ofAccount) ?? $this->pick($ofProvider);
    }

    /**
     * @param list<SmsTemplateMappingEntity> $mappings
     */
    private function pick(array $mappings): ?SmsTemplateMappingEntity
    {
        if ($mappings === []) {
            return null;
        }

        usort($mappings, static function (SmsTemplateMappingEntity $a, SmsTemplateMappingEntity $b): int {
            $aIsSendable = TemplateApprovalStatus::isSendable($a->auditStatus);
            $bIsSendable = TemplateApprovalStatus::isSendable($b->auditStatus);

            if ($aIsSendable !== $bIsSendable) {
                return $aIsSendable ? -1 : 1;
            }

            $byUpdatedAt = self::updatedAtOf($b) <=> self::updatedAtOf($a);

            return $byUpdatedAt === 0 ? $a->id <=> $b->id : $byUpdatedAt;
        });

        return $mappings[0];
    }

    /**
     * The stored timestamp in the format of the database, so the comparison cannot lose the
     * milliseconds of two rows written in the same second.
     */
    private static function updatedAtOf(SmsTemplateMappingEntity $mapping): string
    {
        return $mapping->getUpdatedAt()?->format(Defaults::STORAGE_DATE_TIME_FORMAT) ?? '';
    }
}
