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
use Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping\SmsTemplateMappingEntity;
use Shopwell\Core\System\Sms\Util\PhoneNumberNormalizerInterface;
use Symfony\Component\Notifier\Message\SmsMessage;

/**
 * Turns a logical template and its parameters into a message a provider can submit.
 *
 * It is the only place that knows about both modes of the providers: with a gateway that requires a
 * reported template the body is not sent at all, the message carries the template code, the signature
 * and the parameters of the mapping; with a gateway that takes free text the body is rendered from
 * the content of the template.
 *
 * The gateway is resolved while the template is known, so a gateway that needs an approved mapping of
 * the template is never chosen for it.
 *
 * A message without a usable gateway is not an exception: it is built for the null gateway and
 * carries the reason nothing could be picked, so the delivery record tells the truth while the shop
 * keeps working - an order must not be lost because nobody configured a gateway.
 *
 * @internal
 */
#[Package('framework')]
final class SmsTemplateResolver
{
    /**
     * @param EntityRepository<SmsTemplateCollection> $templateRepository
     * @param EntityRepository<SmsGatewayCollection> $gatewayRepository
     */
    public function __construct(
        private readonly EntityRepository $templateRepository,
        private readonly SmsTemplateMappingLocator $mappingLocator,
        private readonly EntityRepository $gatewayRepository,
        private readonly SmsGatewayRegistry $registry,
        private readonly SmsGatewayResolverInterface $gatewayResolver,
        private readonly SmsParamValidator $paramValidator,
        private readonly SmsTemplateRenderer $renderer,
        private readonly PhoneNumberNormalizerInterface $normalizer,
    ) {
    }

    /**
     * @param array<string, mixed> $params
     * @param string|null $countryIso the country the caller knows about the recipient, handed to the
     *                                normaliser for numbers written without a country code
     *
     * @throws SmsException
     */
    public function resolve(
        string $templateRef,
        array $params,
        string $phone,
        ?Context $context = null,
        ?string $salesChannelId = null,
        ?string $countryIso = null,
    ): SmsMessage {
        $context ??= Context::createDefaultContext();

        $template = $this->loadTemplate($templateRef, $context);
        $this->paramValidator->validate($template, $params);

        // the normaliser decides the e.164 form and the country of the number, and it keeps them
        // apart: a number of a country the shop cannot place stays a valid number without one
        $number = $this->normalizer->normalize($phone, $countryIso);
        $e164 = $number->e164();

        $gatewayIds = $this->gatewayResolver->resolveGatewayIds(
            $context,
            $salesChannelId,
            $number->countryIso(),
            $template
        );
        $gateway = $gatewayIds === [] ? null : $this->loadGateway($gatewayIds[0], $context);

        if ($gateway === null) {
            // nothing is usable, the message still goes through the pipeline and is recorded with the
            // reason it could not be sent
            return $this->buildTextMessage($template, $params, $e164, SmsException::NO_USABLE_GATEWAY)
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
    private function buildTextMessage(
        SmsTemplateEntity $template,
        array $params,
        string $e164,
        ?string $undeliverableReason = null,
    ): SmsMessage {
        $options = new SmsMessageOptions(
            scene: $template->scene,
            templateRef: $template->technicalName,
            undeliverableReason: $undeliverableReason,
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
     * The row the audit reports and reads as well, so the routing and the audit converge on the same
     * mapping instead of each picking its own out of duplicates.
     *
     * @throws SmsException when the template was never reported for this gateway
     */
    private function loadMapping(SmsTemplateEntity $template, SmsGatewayEntity $gateway, Context $context): SmsTemplateMappingEntity
    {
        $mapping = $this->mappingLocator->locate($context, $template->id, $gateway->technicalName, $gateway->id);

        if ($mapping === null || $mapping->templateCode === null) {
            // without the code of the provider there is nothing to submit, the template has to be
            // reported for this gateway first
            throw SmsException::templateNotConfigured($template->technicalName, $gateway->technicalName);
        }

        return $mapping;
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
