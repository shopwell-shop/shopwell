<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\Audit\SmsGatewayAuditCapabilities;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateApprovalStatus;
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
 *  2. the country of the recipient, a gateway that declares it serves it and a gateway that declares
 *     GLOBAL serves every country
 *  3. a provider whose messages need an audit additionally needs an approved mapping of the template,
 *     a provider that does not declare the audit - free text, log, null - is never blocked by one
 *  4. the configured default direction, used when the region of the number matched nothing and
 *     filtered like every other candidate
 *  5. the remaining candidates are ordered by their priority
 *
 * The country of a number and the question whether the shop knows it are two different things, which
 * is why the filter takes the ISO code and not a region name: a number the shop cannot place goes to
 * a gateway for every country or to one an operator explicitly opened for exactly these numbers, but
 * never to a gateway that serves a single country - an unknown number is not a domestic one.
 *
 * Switching sms off globally keeps the pipeline intact, it only leaves the null gateway.
 *
 * @internal
 */
#[Package('framework')]
class SmsGatewayResolver implements SmsGatewayResolverInterface
{
    private const REGION_GLOBAL = 'GLOBAL';

    /**
     * The routing switch an operator sets on a gateway that should also take the numbers this shop
     * cannot place, kept in the custom fields because it is a routing decision and no provider
     * setting - the options of a row are handed to the transport factory untouched.
     */
    private const CUSTOM_FIELD_ACCEPT_UNKNOWN_REGION = 'sms_accept_unknown_region';

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
        private readonly SmsGatewayAuditCapabilities $capabilities,
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
        $matched = $this->filter($gateways, $context, $salesChannelId, $countryCode, $template);

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

        // the configured direction is a preference and no override: it passes the same filter as
        // every other candidate, so it cannot route a number of an unknown country into a gateway
        // that serves one country only
        return array_map(
            static fn (SmsGatewayEntity $gateway): string => $gateway->id,
            $this->filter($fallback, $context, $salesChannelId, $countryCode, $template)
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
        ?string $countryIso,
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

            if (!$this->acceptsCountry($gateway, $factory->getSupportedRegions(), $countryIso)) {
                continue;
            }

            if ($template !== null
                && $this->capabilities->requiresApproval($gateway->technicalName)
                && !$this->hasApprovedMapping($context, $template, $gateway)
            ) {
                continue;
            }

            $matched[] = $gateway;
        }

        usort($matched, static fn (SmsGatewayEntity $a, SmsGatewayEntity $b): int => $a->priority <=> $b->priority);

        return $matched;
    }

    /**
     * Whether a gateway serves a number of this country.
     *
     * A known country is served by a gateway that declares it and by a gateway for every country. A
     * country the shop cannot place is only served by a gateway for every country or by one an
     * operator opened for it - deliberately an explicit configuration, never a default.
     *
     * @param list<string> $supported
     */
    private function acceptsCountry(SmsGatewayEntity $gateway, array $supported, ?string $countryIso): bool
    {
        if ($countryIso === null) {
            return \in_array(self::REGION_GLOBAL, $supported, true)
                || ($gateway->getCustomFields()[self::CUSTOM_FIELD_ACCEPT_UNKNOWN_REGION] ?? false) === true;
        }

        return \in_array($countryIso, $supported, true) || \in_array(self::REGION_GLOBAL, $supported, true);
    }

    /**
     * Whether the shop holds an approved template for this gateway, answered once per template and
     * gateway because a resolution asks for every candidate it looks at.
     */
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
            new EqualsFilter('auditStatus', TemplateApprovalStatus::APPROVED),
        );

        $found = false;

        foreach ($this->mappingRepository->search($criteria, $context)->getEntities() as $mapping) {
            \assert($mapping instanceof SmsTemplateMappingEntity);

            if (!TemplateApprovalStatus::isSendable($mapping->auditStatus)) {
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
