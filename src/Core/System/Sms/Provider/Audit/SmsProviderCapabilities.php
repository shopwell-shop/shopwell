<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Provider\Audit;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Provider\AbstractSmsProvider;
use Shopwell\Core\System\Sms\Provider\SmsProviderRegistry;
use Shopwell\Core\System\Sms\SmsException;

/**
 * The one place that knows which registered provider declares which audit capability.
 *
 * Everything else - the routing, the audit service, the administration payload - asks here by
 * technical name, so no class outside this one compares a provider name or probes a method: a
 * provider that is added by an extension is discovered by the interface it implements, and the core
 * needs no change.
 *
 * An extension reads a capability here as well instead of probing its own transport, which is why
 * this is part of the surface the module offers and not an implementation detail.
 */
#[Package('framework')]
final class SmsProviderCapabilities
{
    /**
     * @internal
     */
    public function __construct(private readonly SmsProviderRegistry $registry)
    {
    }

    /**
     * Whether a message over this provider needs an approved mapping before it may go out.
     */
    public function requiresApproval(string $technicalName): bool
    {
        return $this->registry->has($technicalName)
            && $this->registry->get($technicalName) instanceof RequiresTemplateApprovalInterface;
    }

    /**
     * Whether the provider accepts a template over its api, so the administration may offer a
     * submission for it.
     */
    public function canSubmit(string $technicalName): bool
    {
        return $this->registry->has($technicalName)
            && $this->registry->get($technicalName) instanceof SubmitsTemplatesForApprovalInterface;
    }

    /**
     * Whether the provider reports the state of a template, so the administration offers a refresh
     * instead of a confirmation by hand.
     */
    public function canReadStatus(string $technicalName): bool
    {
        return $this->registry->has($technicalName)
            && $this->registry->get($technicalName) instanceof ReadsTemplateApprovalStatusInterface;
    }

    /**
     * @throws SmsException when the provider declares no submission
     */
    public function submitter(string $technicalName): AbstractSmsProvider&SubmitsTemplatesForApprovalInterface
    {
        $provider = $this->registry->get($technicalName);

        if (!$provider instanceof SubmitsTemplatesForApprovalInterface) {
            throw SmsException::templateSubmissionUnsupported($technicalName);
        }

        return $provider;
    }

    /**
     * @throws SmsException when the provider declares no status read
     */
    public function reader(string $technicalName): AbstractSmsProvider&ReadsTemplateApprovalStatusInterface
    {
        $provider = $this->registry->get($technicalName);

        if (!$provider instanceof ReadsTemplateApprovalStatusInterface) {
            throw SmsException::templateStatusReadUnsupported($technicalName);
        }

        return $provider;
    }

    /**
     * The capability and form description of every registered provider, the payload the
     * administration renders its entries from.
     *
     * @return list<array{
     *     technicalName: string,
     *     labelSnippet: string,
     *     requiresApproval: bool,
     *     canSubmit: bool,
     *     canReadStatus: bool,
     *     submissionFields: list<array<string, mixed>>,
     * }>
     */
    public function describeAll(): array
    {
        $described = [];

        foreach ($this->registry->all() as $technicalName => $provider) {
            $described[] = [
                'technicalName' => $technicalName,
                'labelSnippet' => $provider->getLabelSnippet(),
                'requiresApproval' => $provider instanceof RequiresTemplateApprovalInterface,
                'canSubmit' => $provider instanceof SubmitsTemplatesForApprovalInterface,
                'canReadStatus' => $provider instanceof ReadsTemplateApprovalStatusInterface,
                'submissionFields' => $provider instanceof SubmitsTemplatesForApprovalInterface
                    ? $provider->getTemplateSubmissionFieldDefinitions()
                    : [],
            ];
        }

        return $described;
    }

    /**
     * The five states with their snippet keys, so the administration keeps no list of its own.
     *
     * @return list<array{value: string, labelSnippet: string}>
     */
    public function approvalStatusOptions(): array
    {
        return array_map(
            static fn (string $status): array => [
                'value' => $status,
                'labelSnippet' => TemplateApprovalStatus::labelSnippet($status),
            ],
            TemplateApprovalStatus::values()
        );
    }
}
