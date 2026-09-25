<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ContactForm\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route can be used to send a contact form mail for the authenticated sales channel.
 * Required fields are: "salutationId", "firstName", "lastName", "email", "phone", "subject" and "comment"
 */
#[Package('discovery')]
abstract class AbstractContactFormRoute
{
    abstract public function getDecorated(): AbstractContactFormRoute;

    abstract public function load(RequestDataBag $data, SalesChannelContext $context): ContactFormRouteResponse;
}
