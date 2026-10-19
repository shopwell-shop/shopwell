<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Template;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayCollection;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayEntity;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayRegistry;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayResolverInterface;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\Struct\SmsMessageOptions;
use Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping\SmsTemplateMappingCollection;
use Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping\SmsTemplateMappingEntity;
use Shopwell\Core\System\Sms\Util\PhoneNumberNormalizer;
use Symfony\Component\Notifier\Message\SmsMessage;

/**
 * Turns a logical template and its parameters into a message a provider can submit.
 *
 * It is the only place that knows about both modes of the providers: with a gateway that requires a
 * reported template the body is not sent at all, the message carries the template code, the signature
 * and the parameters of the mapping; with a gateway that takes free text the body is rendered from
 * the content of the template.
 *
 * The gateway is resolved while the template is known, so a gateway without an approved mapping for
 * the template is never chosen for it.
 *
 * @internal
 */
#[Package('framework')]
final class SmsTemplateResolver
{
    /**
     * @param EntityRepository<SmsTemplateCollection> $templateRepository
     * @param EntityRepository<SmsTemplateMappingCollection> $mappingRepository
     * @param EntityRepository<SmsGatewayCollection> $gatewayRepository
     */
    public function __construct(
        private readonly EntityRepository $templateRepository,
        private readonly EntityRepository $mappingRepository,
        private readonly EntityRepository $gatewayRepository,
        private readonly SmsGatewayRegistry $registry,
        private readonly SmsGatewayResolverInterface $gatewayResolver,
        private readonly SmsParamValidator $paramValidator,
        private readonly SmsTemplateRenderer $renderer,
        private readonly PhoneNumberNormalizer $normalizer,
    ) {
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws SmsException
     */
    public function resolve(
        string $templateRef,
        array $params,
        string $phone,
        ?Context $context = null,
        ?string $salesChannelId = null,
    ): SmsMessage {
        $context ??= Context::createDefaultContext();

        $template = $this->loadTemplate($templateRef, $context);
        $this->paramValidator->validate($template, $params);

        $e164 = $this->normalizer->toE164($phone, $salesChannelId);

        $gatewayIds = $this->gatewayResolver->resolveGatewayIds(
            $context,
            $salesChannelId,
            $this->normalizer->countryCode($e164),
            $template
        );
        $gateway = $gatewayIds === [] ? null : $this->loadGateway($gatewayIds[0], $context);

        if ($gateway === null) {
            // nothing is usable, the message still goes through the pipeline and is recorded
            return $this->buildTextMessage($template, $params, $e164)
                ->transport(SmsGatewayResolverInterface::GATEWAY_NULL);
        }

        $factory = $this->registry->get($gateway->technicalName);

        $message = $factory->supportsTemplateMode()
            ? $this->buildTemplateMessage($template, $params, $e164, $gateway, $context)
            : $this->buildTextMessage($template, $params, $e164);

        return $message->transport($gateway->id);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildTemplateMessage(
        SmsTemplateEntity $template,
        array $params,
        string $e164,
        SmsGatewayEntity $gateway,
        Context $context,
    ): SmsMessage {
        $mapping = $this->loadMapping($template, $gateway, $context);

        $options = new SmsMessageOptions(
            scene: $template->scene,
            templateRef: $template->technicalName,
            templateCode: $mapping->templateCode,
            signName: $mapping->signName,
            params: $this->mapParams($params, $mapping),
        );

        // the subject is the logical name, the body of the message lives at the provider
        return new SmsMessage($e164, $template->technicalName, '', $options);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildTextMessage(SmsTemplateEntity $template, array $params, string $e164): SmsMessage
    {
        $options = new SmsMessageOptions(
            scene: $template->scene,
            templateRef: $template->technicalName,
        );

        return new SmsMessage($e164, $this->renderer->render($template, $params), '', $options);
    }

    private function loadTemplate(string $templateRef, Context $context): SmsTemplateEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $templateRef));
        $criteria->setLimit(1);

        $template = $this->templateRepository->search($criteria, $context)->getEntities()->first();

        if (!$template instanceof SmsTemplateEntity) {
            throw SmsException::templateNotFound($templateRef);
        }

        if (!$template->enabled) {
            throw SmsException::templateDisabled($templateRef);
        }

        return $template;
    }

    private function loadGateway(string $gatewayId, Context $context): ?SmsGatewayEntity
    {
        $gateway = $this->gatewayRepository
            ->search(new Criteria([$gatewayId]), $context)
            ->getEntities()
            ->first();

        return $gateway instanceof SmsGatewayEntity ? $gateway : null;
    }

    /**
     * A mapping of the account wins over the one that holds for every account of the provider.
     */
    private function loadMapping(SmsTemplateEntity $template, SmsGatewayEntity $gateway, Context $context): SmsTemplateMappingEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('templateId', $template->id),
            new EqualsFilter('technicalName', $gateway->technicalName),
        );

        $fallback = null;

        foreach ($this->mappingRepository->search($criteria, $context)->getEntities() as $mapping) {
            \assert($mapping instanceof SmsTemplateMappingEntity);

            if ($mapping->gatewayId === $gateway->id) {
                return $mapping;
            }

            if ($mapping->gatewayId === null) {
                $fallback = $mapping;
            }
        }

        if ($fallback !== null) {
            return $fallback;
        }

        throw SmsException::templateNotConfigured($template->technicalName, $gateway->technicalName);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, string>
     */
    private function mapParams(array $params, SmsTemplateMappingEntity $mapping): array
    {
        $mapped = array_map(static fn (mixed $value): string => (string) $value, $params);

        foreach ($mapping->paramsMap ?? [] as $source => $target) {
            // a parameter that keeps its name needs no renaming, and dropping it would lose it
            if ($source === $target || !\array_key_exists($source, $mapped)) {
                continue;
            }

            $mapped[$target] = $mapped[$source];
            unset($mapped[$source]);
        }

        return $mapped;
    }
}
