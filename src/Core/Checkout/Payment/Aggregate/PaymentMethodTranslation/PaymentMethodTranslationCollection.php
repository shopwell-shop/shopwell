<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PaymentMethodTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class PaymentMethodTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getPaymentMethodIds(): array
    {
        return $this->fmap(static fn (PaymentMethodTranslationEntity $paymentMethodTranslation) => $paymentMethodTranslation->getPaymentMethodId());
    }

    public function filterByPaymentMethodId(string $id): self
    {
        return $this->filter(static fn (PaymentMethodTranslationEntity $paymentMethodTranslation) => $paymentMethodTranslation->getPaymentMethodId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (PaymentMethodTranslationEntity $paymentMethodTranslation) => $paymentMethodTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (PaymentMethodTranslationEntity $paymentMethodTranslation) => $paymentMethodTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'payment_method_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return PaymentMethodTranslationEntity::class;
    }
}
