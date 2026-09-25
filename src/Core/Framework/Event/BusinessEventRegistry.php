<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Event;

use Shopwell\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopwell\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopwell\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopwell\Core\Checkout\Customer\Event\CustomerDeletedEvent;
use Shopwell\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopwell\Core\Checkout\Customer\Event\CustomerGroupRegistrationAccepted;
use Shopwell\Core\Checkout\Customer\Event\CustomerGroupRegistrationDeclined;
use Shopwell\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopwell\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopwell\Core\Checkout\Customer\Event\CustomerPasswordChangedEvent;
use Shopwell\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopwell\Core\Checkout\Customer\Event\DoubleOptInGuestOrderEvent;
use Shopwell\Core\Checkout\Customer\Event\GuestCustomerRegisterEvent;
use Shopwell\Core\Checkout\DocumentV2\Event\DocumentDeletedEvent;
use Shopwell\Core\Checkout\DocumentV2\Event\DocumentGeneratedEvent;
use Shopwell\Core\Checkout\Order\Event\OrderPaymentMethodChangedEvent;
use Shopwell\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopwell\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopwell\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopwell\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopwell\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopwell\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Shopwell\Core\Content\Newsletter\Event\NewsletterUnsubscribeEvent;
use Shopwell\Core\Content\Product\SalesChannel\Review\Event\ReviewFormEvent;
use Shopwell\Core\Content\ProductExport\Event\ProductExportLoggingEvent;
use Shopwell\Core\Content\RevocationRequest\Event\RevocationRequestEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\User\Recovery\UserRecoveryRequestEvent;

#[Package('framework')]
class BusinessEventRegistry
{
    /**
     * @var list<class-string<FlowEventAware>>
     */
    private array $classes = [
        CustomerBeforeLoginEvent::class,
        CustomerLoginEvent::class,
        CustomerLogoutEvent::class,
        CustomerDeletedEvent::class,
        UserRecoveryRequestEvent::class,
        CheckoutOrderPlacedEvent::class,
        OrderPaymentMethodChangedEvent::class,
        DocumentGeneratedEvent::class,
        DocumentDeletedEvent::class,
        CustomerAccountRecoverRequestEvent::class,
        CustomerDoubleOptInRegistrationEvent::class,
        CustomerGroupRegistrationAccepted::class,
        CustomerGroupRegistrationDeclined::class,
        CustomerRegisterEvent::class,
        DoubleOptInGuestOrderEvent::class,
        GuestCustomerRegisterEvent::class,
        ContactFormEvent::class,
        ReviewFormEvent::class,
        MailBeforeSentEvent::class,
        MailBeforeValidateEvent::class,
        MailSentEvent::class,
        NewsletterConfirmEvent::class,
        NewsletterRegisterEvent::class,
        NewsletterUnsubscribeEvent::class,
        ProductExportLoggingEvent::class,
        CustomerPasswordChangedEvent::class,
        RevocationRequestEvent::class,
    ];

    /**
     * @param list<class-string<FlowEventAware>> $classes
     */
    public function addClasses(array $classes): void
    {
        $classes = array_values(array_unique(array_merge($this->classes, $classes)));

        $this->classes = $classes;
    }

    /**
     * @return list<class-string<FlowEventAware>>
     */
    public function getClasses(): array
    {
        return $this->classes;
    }
}
