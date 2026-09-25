<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\DataAbstractionLayer;

use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\PluginEntity;

#[Package('checkout')]
class PaymentDistinguishableNameGenerator
{
    /**
     * @internal
     *
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     */
    public function __construct(private readonly EntityRepository $paymentMethodRepository)
    {
    }

    public function generateDistinguishablePaymentNames(Context $context): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context): void {
            $payments = $this->getInstalledPayments($context);

            $upsertablePayments = $this->generateDistinguishableNamesPayload($payments);
            if ($upsertablePayments === []) {
                return;
            }

            $this->paymentMethodRepository->upsert($upsertablePayments, $context);
        });
    }

    private function getInstalledPayments(Context $context): PaymentMethodCollection
    {
        $criteria = new Criteria();
        $criteria
            ->addAssociation('translations')
            ->addAssociation('plugin.translations')
            ->addAssociation('appPaymentMethod.app.translations');

        return $this->paymentMethodRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @return array<array{id: string, distinguishableName: array<string, string>}>
     */
    private function generateDistinguishableNamesPayload(PaymentMethodCollection $payments): array
    {
        $upsertablePayments = [];
        foreach ($payments as $payment) {
            $pluginOrAppEntity = $payment->getPlugin() ?? $payment->getAppPaymentMethod()?->getApp();
            if ($pluginOrAppEntity === null || $payment->getTranslations() === null) {
                continue;
            }

            $distinguishableNames = [];
            foreach ($payment->getTranslations() as $translation) {
                $languageId = $translation->getLanguageId();

                $distinguishableNames[$languageId] = $this->generatePaymentName(
                    $pluginOrAppEntity,
                    $languageId,
                    $translation->getName() ?? $payment->getTranslation('name'),
                );
            }

            $distinguishableNames = array_filter($distinguishableNames);
            if ($distinguishableNames === []) {
                continue;
            }

            $upsertablePayments[] = [
                'id' => $payment->getId(),
                'distinguishableName' => $distinguishableNames,
            ];
        }

        return $upsertablePayments;
    }

    private function generatePaymentName(
        AppEntity|PluginEntity $entity,
        string $languageId,
        string $paymentName,
    ): ?string {
        $label = $entity->getTranslations()?->filterByProperty('languageId', $languageId)->first()?->getLabel()
            ?? $entity->getTranslation('label');

        if (!\is_string($label)) {
            return null;
        }

        return \sprintf(
            '%s | %s',
            $paymentName,
            $label
        );
    }
}
