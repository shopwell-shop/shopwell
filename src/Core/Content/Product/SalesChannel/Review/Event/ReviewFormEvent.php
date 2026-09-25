<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\Review\Event;

use Shopwell\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Shopwell\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\ProductEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopwell\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopwell\Core\Framework\Event\CustomerAware;
use Shopwell\Core\Framework\Event\EventData\EntityType;
use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Event\EventData\FormDataObjectType;
use Shopwell\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\Event\MailAware;
use Shopwell\Core\Framework\Event\ProductAware;
use Shopwell\Core\Framework\Event\SalesChannelAware;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('after-sales')]
final class ReviewFormEvent extends Event implements SalesChannelAware, MailAware, ProductAware, CustomerAware, ScalarValuesAware, FlowEventAware
{
    public const EVENT_NAME = 'review_form.send';

    /**
     * @var array<int|string, mixed>
     */
    private readonly array $reviewFormData;

    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'product', newType: ProductEntity::class, description: 'The parameter loses its null default and becomes required.')]
    public function __construct(
        private readonly Context $context,
        private readonly string $salesChannelId,
        private readonly MailRecipientStruct $recipients,
        DataBag $reviewFormData,
        private readonly string $productId,
        private readonly string $customerId,
        private readonly ?ProductEntity $product = null,
    ) {
        $this->reviewFormData = $reviewFormData->all();

        if ($product === null) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Not passing $product to ' . static::class . ' is deprecated and will be required in v6.8.0.');
        }
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(FlowMailVariables::REVIEW_FORM_DATA, new FormDataObjectType())
            ->add(ProductAware::PRODUCT, new EntityType(ProductDefinition::class));
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [FlowMailVariables::REVIEW_FORM_DATA => $this->reviewFormData];
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        return $this->recipients;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getReviewFormData(): array
    {
        return $this->reviewFormData;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    // @phpstan-ignore-next-line shopwell.bcChangeAttribute (The constructor still permits a missing product, so the return type cannot be narrowed yet.)
    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: ProductEntity::class, description: 'The return value becomes non-nullable once constructing the event without a product is removed.')]
    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }
}
