<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Routing;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Extension\SelectSmsGatewayExtension;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayCollection;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayEntity;
use Shopwell\Core\System\Sms\Provider\Audit\SmsProviderCapabilities;
use Shopwell\Core\System\Sms\Provider\Audit\TemplateApprovalStatus;
use Shopwell\Core\System\Sms\Provider\SmsProviderRegistry;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping\SmsTemplateMappingEntity;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;
use Shopwell\Core\System\Sms\Template\SmsTemplateMappingLocator;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('framework')]
class SmsGatewayResolver implements SmsGatewayResolverInterface, ResetInterface
{
    private const REGION_GLOBAL = 'GLOBAL';

    /**
     * The routing switch an operator sets on a gateway that should also take the numbers this shop
     * cannot place, kept in the custom fields because it is a routing decision and no provider
     * setting - the options of a row are handed to the provider untouched.
     */
    private const CUSTOM_FIELD_ACCEPT_UNKNOWN_REGION = 'sms_accept_unknown_region';

    /**
     * @var array<string, array{blocked: bool, status: string|null}>
     */
    private array $auditStateCache = [];

    /**
     * @var array<string, SmsTemplateMappingEntity|null>
     */
    private array $mappings = [];

    /**
     * @param EntityRepository<SmsGatewayCollection> $gatewayRepository
     */
    public function __construct(
        private readonly EntityRepository $gatewayRepository,
        private readonly SmsTemplateMappingLocator $mappingLocator,
        private readonly SmsProviderRegistry $registry,
        private readonly SmsProviderCapabilities $capabilities,
        private readonly SystemConfigService $configService,
        private readonly ?ExtensionDispatcher $extensions = null,
    ) {
    }

    public function resolveGatewayIds(
        Context $context,
        ?string $salesChannelId = null,
        ?string $countryCode = null,
        ?SmsTemplateEntity $template = null,
    ): array {
        return $this->route($context, $salesChannelId, $countryCode, $template)['ids'];
    }

    public function undeliverableReason(
        Context $context,
        ?string $salesChannelId = null,
        ?string $countryCode = null,
        ?SmsTemplateEntity $template = null,
    ): ?SmsException {
        if ($template === null) {
            return null;
        }

        $blocked = $this->route($context, $salesChannelId, $countryCode, $template)['auditBlocked'];

        if ($blocked === null) {
            return null;
        }

        return SmsException::templateNotApproved(
            $template->technicalName,
            $blocked['technicalName'],
            $blocked['auditStatus']
        );
    }

    public function resolve(
        Context $context,
        ?string $salesChannelId = null,
        ?string $countryCode = null,
        ?SmsTemplateEntity $template = null,
    ): SmsGatewayResolution {
        $route = $this->route($context, $salesChannelId, $countryCode, $template);
        $blocked = $route['auditBlocked'];
        $refusal = $template !== null && $blocked !== null
            ? SmsException::templateNotApproved($template->technicalName, $blocked['technicalName'], $blocked['auditStatus'])
            : null;

        $mappings = [];

        if ($template !== null) {
            foreach ($route['ids'] as $id) {
                $mappings[$id] = $this->mappings[$template->id . ':' . $id] ?? null;
            }
        }

        return new SmsGatewayResolution($route['ids'], $mappings, $refusal);
    }

    public function reset(): void
    {
        $this->auditStateCache = [];
        $this->mappings = [];
    }

    /**
     * The candidates for these arguments and, when nothing is left, the candidate the audit gate
     * dropped.
     *
     * @return array{ids: list<string>, auditBlocked: array{technicalName: string, auditStatus: string}|null}
     */
    private function route(
        Context $context,
        ?string $salesChannelId,
        ?string $countryCode,
        ?SmsTemplateEntity $template,
    ): array {
        if (!$this->isEnabled($salesChannelId)) {
            // the channel is switched off on purpose, that is no audit and no misconfiguration
            return ['ids' => [], 'auditBlocked' => null];
        }

        $gateways = $this->loadGateways($context);
        $filtered = $this->filter($gateways, $context, $salesChannelId, $countryCode, $template);

        if ($filtered['ids'] !== []) {
            return ['ids' => $this->select($filtered['ids'], $context, $salesChannelId, $countryCode, $template), 'auditBlocked' => null];
        }

        $default = $this->configService->getString('core.smsSettings.defaultGateway', $salesChannelId);

        if ($default === '') {
            return ['ids' => [], 'auditBlocked' => $filtered['auditBlocked']];
        }

        $fallback = array_values(array_filter(
            $gateways,
            static fn (SmsGatewayEntity $gateway): bool => $gateway->technicalName === $default
        ));

        // the configured direction is a preference and no override: it passes the same filter as
        // every other candidate, so it cannot route a number of an unknown country into a gateway
        // that serves one country only
        $ofDefault = $this->filter($fallback, $context, $salesChannelId, $countryCode, $template);

        if ($ofDefault['ids'] !== []) {
            return ['ids' => $this->select($ofDefault['ids'], $context, $salesChannelId, $countryCode, $template), 'auditBlocked' => null];
        }

        return [
            'ids' => [],
            // the candidate the operator pointed at is the closest the shop has, so its audit
            // explains the refusal better than the one of a candidate of another country
            'auditBlocked' => $ofDefault['auditBlocked'] ?? $filtered['auditBlocked'],
        ];
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
     * @return array{ids: list<string>, auditBlocked: array{technicalName: string, auditStatus: string}|null}
     */
    private function filter(
        array $gateways,
        Context $context,
        ?string $salesChannelId,
        ?string $countryIso,
        ?SmsTemplateEntity $template,
    ): array {
        $matched = [];
        $auditBlocked = null;

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

            $provider = $this->registry->get($gateway->technicalName);

            if (!$this->acceptsCountry($gateway, $provider->getSupportedRegions(), $countryIso)) {
                continue;
            }

            if ($template !== null && $this->capabilities->requiresApproval($gateway->technicalName)) {
                $audit = $this->auditStateOf($context, $template, $gateway);

                if ($audit['blocked']) {
                    // every gate before this one was passed, which is what makes this candidate the
                    // only honest answer to the question why the message did not go out
                    if ($audit['status'] !== null) {
                        $auditBlocked ??= [
                            'technicalName' => $gateway->technicalName,
                            'auditStatus' => $audit['status'],
                        ];
                    }

                    continue;
                }
            }

            if ($template !== null && $provider->supportsTemplateMode()) {
                $this->mappingFor($context, $template, $gateway);
            }

            $matched[] = $gateway;
        }

        usort($matched, static fn (SmsGatewayEntity $a, SmsGatewayEntity $b): int => $a->priority <=> $b->priority);

        return [
            'ids' => array_map(static fn (SmsGatewayEntity $gateway): string => $gateway->id, $matched),
            'auditBlocked' => $auditBlocked,
        ];
    }

    /**
     * Whether the audit of the template keeps this gateway from sending it, and which state does.
     *
     * The status stays null when nothing was ever reported for this combination: the gateway is
     * blocked just the same, but there is no audit to name - a missing mapping is a missing mapping
     * and no pending audit an operator should wait for.
     *
     * Answered once per template and gateway, because a resolution asks for every candidate it looks
     * at and the refusal for the same template is asked right after.
     *
     * @return array{blocked: bool, status: string|null}
     */
    private function auditStateOf(Context $context, SmsTemplateEntity $template, SmsGatewayEntity $gateway): array
    {
        $cacheKey = $template->id . ':' . $gateway->id;

        if (\array_key_exists($cacheKey, $this->auditStateCache)) {
            return $this->auditStateCache[$cacheKey];
        }

        $mapping = $this->mappingFor($context, $template, $gateway);

        return $this->auditStateCache[$cacheKey] = [
            'blocked' => $mapping === null || !TemplateApprovalStatus::isSendable($mapping->auditStatus),
            'status' => $mapping === null || TemplateApprovalStatus::isSendable($mapping->auditStatus) ? null : $mapping->auditStatus,
        ];
    }

    /**
     * @param list<string> $eligible
     *
     * @return list<string>
     */
    private function select(array $eligible, Context $context, ?string $salesChannelId, ?string $countryCode, ?SmsTemplateEntity $template): array
    {
        if ($this->extensions === null) {
            return $eligible;
        }

        $selected = $this->extensions->publish(
            SelectSmsGatewayExtension::NAME,
            new SelectSmsGatewayExtension($eligible, $context, $salesChannelId, $countryCode, $template),
            static fn (array $gatewayIds, Context $context, ?string $salesChannelId, ?string $countryCode, ?SmsTemplateEntity $template): array => $gatewayIds,
        );

        return array_values(array_unique(array_intersect($selected, $eligible)));
    }

    private function mappingFor(Context $context, SmsTemplateEntity $template, SmsGatewayEntity $gateway): ?SmsTemplateMappingEntity
    {
        $key = $template->id . ':' . $gateway->id;

        if (!\array_key_exists($key, $this->mappings)) {
            $this->mappings[$key] = $this->mappingLocator->locate($context, $template->id, $gateway->technicalName, $gateway->id);
        }

        return $this->mappings[$key];
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
     * Sms is on until an administrator switches it off, a missing value must not silence the channel
     * of a fresh installation.
     */
    private function isEnabled(?string $salesChannelId): bool
    {
        $configured = $this->configService->get('core.smsSettings.enabled', $salesChannelId);

        return $configured === null ? true : (bool) $configured;
    }
}
