<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Template\Audit;

use Doctrine\DBAL\Connection;
use Monolog\Level;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Sms\Event\SmsTemplateAuditStatusChangedEvent;
use Shopwell\Core\System\Sms\Gateway\Audit\SmsGatewayAuditCapabilities;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateApprovalStatus;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateSubmission;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayCollection;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayEntity;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping\SmsTemplateMappingCollection;
use Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping\SmsTemplateMappingEntity;
use Shopwell\Core\System\Sms\Template\SmsTemplateCollection;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;
use Shopwell\Core\System\Sms\Template\SmsTemplateMappingLocator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Drives the audit loop of one mapping row: report the template of the shop at the provider,
 * read the state back, write both onto the row - and take the confirmation of an operator for a
 * provider that cannot be asked.
 *
 * One row is one template at one provider account and carries exactly one submitted body: the body
 * of the default language. There is no per language row and no language fallback in this round -
 * the language of the recipient never decides which template is used.
 *
 * The provider is never named here - the capabilities of the registered factory decide what happens,
 * and which fields the submission needs is asked from the factory as well.
 *
 * Every write path first resolves the row through SmsTemplateMappingLocator, so a combination that
 * already exists is reused instead of duplicated - the unique key of the table cannot express that,
 * a null gateway_id is treated as distinct by the database.
 *
 * No provider call is wrapped in a database transaction: the write of an answer is a single row
 * update and therefore atomic on its own, and a failing call must leave the row untouched.
 *
 * @internal
 */
#[Package('framework')]
final class SmsTemplateAuditService
{
    /**
     * Where the answers of the provider are kept. They are read only by the administration, which is
     * why they live in the custom fields of the row instead of columns of their own.
     */
    private const CUSTOM_FIELD_STATUS_CODE = 'sms_provider_status_code';
    private const CUSTOM_FIELD_STATUS_MESSAGE = 'sms_provider_status_message';
    private const CUSTOM_FIELD_LAST_SYNCED_AT = 'sms_last_synced_at';
    private const CUSTOM_FIELD_TEMPLATE_NAME = 'sms_provider_template_name';

    private const LOG_MESSAGE_CONFIRMED_MANUALLY = 'sms.template_mapping.confirmed_manually';

    /**
     * Pending is what a submission produces and unknown is what the shop does not know, so neither
     * may be set by hand.
     */
    private const CONFIRMABLE_STATUSES = [
        TemplateApprovalStatus::APPROVED,
        TemplateApprovalStatus::REJECTED,
        TemplateApprovalStatus::SUSPENDED,
    ];

    /**
     * The placeholders of a body, the same shape the renderer replaces at send time.
     */
    private const PLACEHOLDER = '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/';

    /**
     * @param EntityRepository<SmsTemplateMappingCollection> $mappingRepository
     * @param EntityRepository<SmsGatewayCollection> $gatewayRepository
     * @param EntityRepository<SmsTemplateCollection> $templateRepository
     */
    public function __construct(
        private readonly SmsGatewayAuditCapabilities $capabilities,
        private readonly EntityRepository $mappingRepository,
        private readonly EntityRepository $gatewayRepository,
        private readonly EntityRepository $templateRepository,
        private readonly SmsTemplateMappingLocator $mappingLocator,
        private readonly SmsTemplateBodyReader $bodyReader,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Reports the template of the row at the provider the row points to.
     *
     * A row whose template is at the provider already and still pending or approved is returned as it
     * is: pressing "submit" twice must not create a second template at the provider.
     *
     * @param array<string, mixed> $fieldValues
     *
     * @throws SmsException
     */
    public function submit(Context $context, string $mappingId, array $fieldValues): SmsTemplateMappingEntity
    {
        $mapping = $this->loadMapping($context, $mappingId);
        $factory = $this->capabilities->submitter($mapping->technicalName);

        $gatewayId = $mapping->gatewayId;

        if ($gatewayId === null) {
            // reporting needs the credentials of one account, a row that holds for every account of
            // the provider has none
            throw SmsException::templateAuditAccountRequired($mapping->templateId, $mapping->technicalName);
        }

        $gateway = $this->loadGateway($context, $mapping, $gatewayId);
        $template = $this->loadTemplate($context, $mapping);
        $content = $this->bodyReader->readDefaultContent($template);

        if (trim($content) === '') {
            throw SmsException::templateContentMissing($template->technicalName);
        }

        $violations = $this->validateFieldValues($factory->getTemplateSubmissionFieldDefinitions(), $fieldValues);

        if ($violations !== []) {
            throw SmsException::templateSubmissionInvalid($template->technicalName, $mapping->technicalName, $violations);
        }

        if ($mapping->templateCode !== null
            && \in_array($mapping->auditStatus, [TemplateApprovalStatus::PENDING, TemplateApprovalStatus::APPROVED], true)
        ) {
            return $mapping;
        }

        $receipt = $factory->submitTemplate(
            $gateway->credentials ?? [],
            $gateway->options ?? [],
            new TemplateSubmission(
                templateRef: $template->technicalName,
                scene: $template->scene,
                content: $content,
                signName: $mapping->signName,
                paramNames: $this->paramNames($content),
                values: $fieldValues,
                existingTemplateCode: $mapping->templateCode,
            ),
        );

        $customFields = $mapping->getCustomFields() ?? [];

        if ($receipt->providerCode !== null) {
            $customFields[self::CUSTOM_FIELD_STATUS_CODE] = $receipt->providerCode;
        }

        if ($receipt->providerMessage !== null) {
            $customFields[self::CUSTOM_FIELD_STATUS_MESSAGE] = $receipt->providerMessage;
        }

        if ($receipt->providerTemplateName !== null) {
            $customFields[self::CUSTOM_FIELD_TEMPLATE_NAME] = $receipt->providerTemplateName;
        }

        $mapping->setCustomFields($customFields);

        $fromStatus = $mapping->auditStatus;

        // the row keeps the body that was reported, so a body that changed in the shop can be
        // reported again deliberately instead of silently differing from the one at the provider
        $this->mappingRepository->update([[
            'id' => $mapping->id,
            'templateCode' => $receipt->templateCode,
            'reportedContent' => $content,
            'reportedAt' => $this->clock->now(),
            'auditStatus' => $receipt->status,
            'customFields' => $mapping->getCustomFields(),
        ]], $context);

        $this->dispatchStatusChange($context, $mapping, $fromStatus, $receipt->status, $receipt->templateCode);

        return $this->loadMapping($context, $mappingId);
    }

    /**
     * Reads the state of the reported template back and keeps it on the row.
     *
     * A provider that cannot be asked throws, and then nothing is written: the state and the time of
     * the last sync keep their value, so the row never looks as if it had been reconciled.
     *
     * @throws SmsException
     */
    public function refresh(Context $context, string $mappingId): SmsTemplateMappingEntity
    {
        $mapping = $this->loadMapping($context, $mappingId);
        $factory = $this->capabilities->reader($mapping->technicalName);

        $gatewayId = $mapping->gatewayId;

        if ($gatewayId === null) {
            throw SmsException::templateAuditAccountRequired($mapping->templateId, $mapping->technicalName);
        }

        $templateCode = $mapping->templateCode;

        if ($templateCode === null) {
            // nothing was reported for this row, there is no state to read
            throw SmsException::templateNotConfigured($mapping->templateId, $mapping->technicalName);
        }

        $gateway = $this->loadGateway($context, $mapping, $gatewayId);

        $reading = $factory->readTemplateApprovalStatus(
            $gateway->credentials ?? [],
            $gateway->options ?? [],
            $templateCode,
        );

        $customFields = $mapping->getCustomFields() ?? [];
        $customFields[self::CUSTOM_FIELD_STATUS_CODE] = $reading->providerStatusCode;
        $customFields[self::CUSTOM_FIELD_STATUS_MESSAGE] = $reading->providerStatusMessage;
        $customFields[self::CUSTOM_FIELD_LAST_SYNCED_AT] = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        if ($reading->providerTemplateName !== null) {
            $customFields[self::CUSTOM_FIELD_TEMPLATE_NAME] = $reading->providerTemplateName;
        }

        $mapping->setCustomFields($customFields);

        $fromStatus = $mapping->auditStatus;

        $this->mappingRepository->update([[
            'id' => $mapping->id,
            'auditStatus' => $reading->status,
            'customFields' => $mapping->getCustomFields(),
        ]], $context);

        $this->dispatchStatusChange($context, $mapping, $fromStatus, $reading->status, $templateCode);

        return $this->loadMapping($context, $mappingId);
    }

    /**
     * Records the state an operator read at the console of a provider that cannot be asked.
     *
     * Only for a provider that does not declare ReadsTemplateApprovalStatusInterface - a state the
     * shop could learn itself must not be overridable by hand. Only approved, rejected and suspended
     * may be set: pending is what a submission produces and unknown is what the shop does not know.
     *
     * @param string $status one of the confirmable states
     * @param string $note the reason the operator gives, mandatory and part of the audit trail
     *
     * @throws SmsException
     */
    public function confirmManually(Context $context, string $mappingId, string $status, string $note): SmsTemplateMappingEntity
    {
        $mapping = $this->loadMapping($context, $mappingId);

        if ($this->capabilities->canReadStatus($mapping->technicalName)) {
            throw SmsException::templateStatusReadUnsupported($mapping->technicalName);
        }

        $template = $this->loadTemplate($context, $mapping);
        $violations = $this->validateConfirmation($status, $note);

        if ($violations !== []) {
            throw SmsException::templateAuditConfirmationInvalid($template->technicalName, $mapping->technicalName, $violations);
        }

        $fromStatus = $mapping->auditStatus;

        // the raw words of the provider stay untouched, an operators note is no provider message
        $this->mappingRepository->update([[
            'id' => $mapping->id,
            'auditStatus' => $status,
        ]], $context);

        $this->logConfirmation($context, $mapping, $template, $fromStatus, $status, $note);

        $this->dispatchStatusChange($context, $mapping, $fromStatus, $status, $mapping->templateCode);

        return $this->loadMapping($context, $mappingId);
    }

    /**
     * Resolves the row of a (template, provider, account) combination, creating it when it does not
     * exist yet.
     *
     * This is the single creation path of the module: the administration offers it as "report at the
     * console and record it here", so a second row for the same combination cannot be produced by a
     * double click.
     *
     * @throws SmsException
     */
    public function ensureMapping(
        Context $context,
        string $templateId,
        string $technicalName,
        ?string $gatewayId,
    ): SmsTemplateMappingEntity {
        $existing = $this->mappingLocator->locate($context, $templateId, $technicalName, $gatewayId);

        if ($existing !== null) {
            return $existing;
        }

        $mappingId = Uuid::randomHex();

        $this->mappingRepository->create([[
            'id' => $mappingId,
            'templateId' => $templateId,
            'technicalName' => $technicalName,
            'gatewayId' => $gatewayId,
        ]], $context);

        return $this->loadMapping($context, $mappingId);
    }

    /**
     * Reports the transition of a row after it was written.
     *
     * Only a transition is news: a pending template is read back again and again, and a subscriber
     * that wants to act on "the template became approved" must not be woken by a refresh that read
     * the same state a second time.
     */
    private function dispatchStatusChange(
        Context $context,
        SmsTemplateMappingEntity $mapping,
        string $fromStatus,
        string $toStatus,
        ?string $providerTemplateCode,
    ): void {
        if ($fromStatus === $toStatus) {
            return;
        }

        $this->eventDispatcher->dispatch(new SmsTemplateAuditStatusChangedEvent(
            mappingId: $mapping->id,
            templateId: $mapping->templateId,
            technicalName: $mapping->technicalName,
            gatewayId: $mapping->gatewayId,
            previousStatus: $fromStatus,
            status: $toStatus,
            providerTemplateCode: $providerTemplateCode,
            context: $context,
        ));
    }

    /**
     * @throws SmsException
     */
    private function loadMapping(Context $context, string $mappingId): SmsTemplateMappingEntity
    {
        $mapping = $this->mappingRepository
            ->search(new Criteria([$mappingId]), $context)
            ->getEntities()
            ->first();

        if (!$mapping instanceof SmsTemplateMappingEntity) {
            throw SmsException::templateNotFound($mappingId);
        }

        return $mapping;
    }

    /**
     * A disabled account is as unusable as a missing one: both are a configuration the shop has to
     * fix before a template can be reported.
     *
     * @throws SmsException
     */
    private function loadGateway(Context $context, SmsTemplateMappingEntity $mapping, string $gatewayId): SmsGatewayEntity
    {
        $gateway = $this->gatewayRepository
            ->search(new Criteria([$gatewayId]), $context)
            ->getEntities()
            ->first();

        if (!$gateway instanceof SmsGatewayEntity || !$gateway->enabled) {
            throw SmsException::templateNotConfigured($mapping->templateId, $mapping->technicalName);
        }

        return $gateway;
    }

    /**
     * @throws SmsException
     */
    private function loadTemplate(Context $context, SmsTemplateMappingEntity $mapping): SmsTemplateEntity
    {
        $criteria = new Criteria([$mapping->templateId]);
        $criteria->addAssociation('translations');

        $template = $this->templateRepository
            ->search($criteria, $context)
            ->getEntities()
            ->first();

        if (!$template instanceof SmsTemplateEntity) {
            throw SmsException::templateNotFound($mapping->templateId);
        }

        return $template;
    }

    /**
     * Checks the values the operator submitted against the form the provider declared, so a request
     * the provider would refuse is not sent at all.
     *
     * @param list<array{
     *     name: string,
     *     type: 'string'|'text'|'int'|'select',
     *     required: bool,
     *     labelSnippet: string,
     *     prefill?: 'templateName'|'content'|'scene',
     *     default?: string|int|null,
     *     helpSnippet?: string,
     *     options?: list<array{value: string|int, labelSnippet: string}>,
     * }> $definitions
     * @param array<string, mixed> $fieldValues
     *
     * @return list<string>
     */
    private function validateFieldValues(array $definitions, array $fieldValues): array
    {
        $violations = [];

        foreach ($definitions as $definition) {
            $name = $definition['name'];
            $value = $fieldValues[$name] ?? null;

            if (\is_string($value)) {
                // an empty input of the form leaves a blank string, which is a missing value
                $value = trim($value) === '' ? null : $value;
            }

            if ($value === null) {
                if ($definition['required']) {
                    $violations[] = \sprintf('"%s" is required', $name);
                }

                continue;
            }

            if ($definition['type'] === 'int' && !is_numeric($value)) {
                $violations[] = \sprintf('"%s" must be a number', $name);
            }

            if ($definition['type'] === 'select'
                && !\in_array($value, array_column($definition['options'] ?? [], 'value'), true)
            ) {
                $violations[] = \sprintf('"%s" is not one of the offered values', $name);
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function validateConfirmation(string $status, string $note): array
    {
        $violations = [];

        if (!\in_array($status, self::CONFIRMABLE_STATUSES, true)) {
            $violations[] = \sprintf('"%s" cannot be confirmed by hand', $status);
        }

        if (trim($note) === '') {
            $violations[] = 'a note is required';
        }

        return $violations;
    }

    /**
     * Writes the audit trail of a manual confirmation into the log entry table, the channel the
     * module already uses for what happened to a message.
     */
    private function logConfirmation(
        Context $context,
        SmsTemplateMappingEntity $mapping,
        SmsTemplateEntity $template,
        string $fromStatus,
        string $toStatus,
        string $note,
    ): void {
        $now = $this->clock->now();
        $source = $context->getSource();

        $trail = [
            'mappingId' => $mapping->id,
            'templateRef' => $template->technicalName,
            'technicalName' => $mapping->technicalName,
            'gatewayId' => $mapping->gatewayId,
            'fromStatus' => $fromStatus,
            'toStatus' => $toStatus,
            'note' => $note,
            // an operator is an administration user, a console command or a message queue has none
            'operatorId' => $source instanceof AdminApiSource ? $source->getUserId() : null,
            'occurredAt' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $encoded = null;

        try {
            $encoded = json_encode($trail, \JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
        }

        $this->connection->insert('log_entry', [
            'id' => Uuid::randomBytes(),
            'message' => self::LOG_MESSAGE_CONFIRMED_MANUALLY,
            'level' => Level::Info->value,
            'channel' => 'sms',
            'context' => $encoded,
            'extra' => null,
            'created_at' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * The placeholders of the body in the order they appear, so a provider that reports parameters
     * positionally can send them without parsing the body itself.
     *
     * @return list<string>
     */
    private function paramNames(string $content): array
    {
        $matched = preg_match_all(self::PLACEHOLDER, $content, $matches);

        if ($matched === false) {
            return [];
        }

        return array_values(array_unique($matches[1]));
    }
}
