<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping\SmsTemplateMappingCollection;
use Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping\SmsTemplateMappingEntity;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;
use Shopwell\Core\System\SystemConfig\SystemConfigService;

/**
 * The default routing, the steps are applied in this order and the first one that yields candidates
 * wins:
 *
 *  1. the sales channel the caller sends for, a gateway bound to another channel is not a candidate
 *     and a gateway bound to no channel serves every one of them
 *  2. the country of the recipient, a +86 number goes to a gateway that lists CN, every other one to
 *     a gateway that lists GLOBAL
 *  3. the gateways of a template based message additionally need an approved mapping for the template
 *  4. the configured default direction, used when the region of the number matched nothing
 *  5. the remaining candidates are ordered by their priority
 *
 * Switching sms off globally keeps the pipeline intact, it only leaves the null gateway.
 *
 * @internal
 */
#[Package('framework')]
class SmsGatewayResolver implements SmsGatewayResolverInterface
{
    private const REGION_CHINA = 'CN';
    private const REGION_GLOBAL = 'GLOBAL';

    /**
     * @var array<string, bool>
     */
    private array $approvedMappingCache = [];

    /**
     * @param EntityRepository<SmsGatewayCollection> $gatewayRepository
     * @param EntityRepository<SmsTemplateMappingCollection> $mappingRepository
     */
    public function __construct(
        private readonly EntityRepository $gatewayRepository,
        private readonly EntityRepository $mappingRepository,
        private readonly SmsGatewayRegistry $registry,
        private readonly SystemConfigService $configService,
    ) {
    }

    public function resolveGatewayIds(
        Context $context,
        ?string $salesChannelId = null,
        ?string $countryCode = null,
        ?SmsTemplateEntity $template = null,
    ): array {
        if (!$this->isEnabled($salesChannelId)) {
            return [];
        }

        $gateways = $this->loadGateways($context);
        $matched = $this->filter($gateways, $context, $salesChannelId, $this->regionFor($countryCode), $template);

        if ($matched !== []) {
            return array_map(static fn (SmsGatewayEntity $gateway): string => $gateway->id, $matched);
        }

        $default = $this->configService->getString('core.smsSettings.defaultGateway', $salesChannelId);

        if ($default === '') {
            return [];
        }

        $fallback = array_values(array_filter(
            $gateways,
            static fn (SmsGatewayEntity $gateway): bool => $gateway->technicalName === $default
        ));

        return array_map(
            static fn (SmsGatewayEntity $gateway): string => $gateway->id,
            $this->filter($fallback, $context, $salesChannelId, null, $template)
        );
    }

    /**
     * @return list<SmsGatewayEntity>
     */
    private function loadGateways(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('enabled', true));
        $criteria->addSorting(new FieldSorting('priority', FieldSorting::ASCENDING));

        return array_values($this->gatewayRepository->search($criteria, $context)->getEntities()->getElements());
    }

    /**
     * @param list<SmsGatewayEntity> $gateways
     *
     * @return list<SmsGatewayEntity>
     */
    private function filter(
        array $gateways,
        Context $context,
        ?string $salesChannelId,
        ?string $region,
        ?SmsTemplateEntity $template,
    ): array {
        $matched = [];

        foreach ($gateways as $gateway) {
            if (!$gateway->enabled) {
                continue;
            }

            if ($gateway->salesChannelId !== null && $gateway->salesChannelId !== $salesChannelId) {
                continue;
            }

            if (!$this->registry->has($gateway->technicalName)) {
                continue;
            }

            $factory = $this->registry->get($gateway->technicalName);

            if ($region !== null && !$this->regionMatches($region, $factory->getSupportedRegions())) {
                continue;
            }

            if ($template !== null && $factory->supportsTemplateMode() && !$this->hasApprovedMapping($context, $template, $gateway)) {
                continue;
            }

            $matched[] = $gateway;
        }

        usort($matched, static fn (SmsGatewayEntity $a, SmsGatewayEntity $b): int => $a->priority <=> $b->priority);

        return $matched;
    }

    /**
     * A gateway that lists GLOBAL serves every region, a gateway of a single region serves only that
     * one - the Chinese providers accept domestic numbers only.
     *
     * @param list<string> $supported
     */
    private function regionMatches(string $region, array $supported): bool
    {
        return \in_array($region, $supported, true) || \in_array(self::REGION_GLOBAL, $supported, true);
    }

    private function regionFor(?string $countryCode): string
    {
        return $countryCode === self::REGION_CHINA ? self::REGION_CHINA : self::REGION_GLOBAL;
    }

    private function hasApprovedMapping(Context $context, SmsTemplateEntity $template, SmsGatewayEntity $gateway): bool
    {
        $cacheKey = $template->id . ':' . $gateway->id;

        if (\array_key_exists($cacheKey, $this->approvedMappingCache)) {
            return $this->approvedMappingCache[$cacheKey];
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('templateId', $template->id),
            new EqualsFilter('technicalName', $gateway->technicalName),
            new EqualsFilter('auditStatus', SmsTemplateMappingEntity::AUDIT_APPROVED),
        );

        $found = false;

        foreach ($this->mappingRepository->search($criteria, $context)->getEntities() as $mapping) {
            \assert($mapping instanceof SmsTemplateMappingEntity);

            if ($mapping->auditStatus !== SmsTemplateMappingEntity::AUDIT_APPROVED) {
                continue;
            }

            // a mapping without a gateway holds for every account of the provider
            if ($mapping->gatewayId === null || $mapping->gatewayId === $gateway->id) {
                $found = true;

                break;
            }
        }

        return $this->approvedMappingCache[$cacheKey] = $found;
    }

    /**
     * Sms is on until an administrator switches it off, a missing value must not silence the channel
     * of a fresh installation.
     */
    private function isEnabled(?string $salesChannelId): bool
    {
        $configured = $this->configService->get('core.smsSettings.enabled', $salesChannelId);

        return $configured === null ? true : (bool) $configured;
    }
}
