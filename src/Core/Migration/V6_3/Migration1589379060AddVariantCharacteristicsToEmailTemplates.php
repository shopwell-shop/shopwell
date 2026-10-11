<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\MailTemplate\MailTemplateTypes;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1589379060AddVariantCharacteristicsToEmailTemplates extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1589379060;
    }

    public function update(Connection $connection): void
    {
        // implement update
        $enLangId = $this->fetchLanguageId('en-GB', $connection);
        $zhCnLangId = $this->fetchLanguageId('zh-CN', $connection);

        // update email templates
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
            $connection,
            $enLangId,
            $zhCnLangId,
            $this->getOrderConfirmationHtmlTemplateEn(),
            $this->getOrderConfirmationPlainTemplateEn(),
            $this->getOrderConfirmationHTMLTemplateZhCn(),
            $this->getOrderConfirmationPlainTemplateZhCn()
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function updateMailTemplate(
        string $mailTemplateType,
        Connection $connection,
        ?string $enLangId,
        ?string $zhCnLangId,
        string $getHtmlTemplateEn,
        string $getPlainTemplateEn,
        string $getHtmlTemplateZhCn,
        string $getPlainTemplateZhCn
    ): void {
        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, $mailTemplateType);
        if ($templateId === null) {
            return;
        }

        if ($enLangId !== null) {
            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $enLangId,
                $getHtmlTemplateEn,
                $getPlainTemplateEn
            );
        }
        if ($zhCnLangId !== null) {
            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $zhCnLangId,
                $getHtmlTemplateZhCn,
                $getPlainTemplateZhCn
            );
        }
    }

    private function fetchSystemMailTemplateIdFromType(Connection $connection, string $mailTemplateType): ?string
    {
        $templateTypeId = $connection->executeQuery('
        SELECT `id` from `mail_template_type` WHERE `technical_name` = :type
        ', ['type' => $mailTemplateType])->fetchOne();

        $templateId = $connection->executeQuery('
        SELECT `id` from `mail_template` WHERE `mail_template_type_id` = :typeId AND `system_default` = 1 AND `updated_at` IS NULL
        ', ['typeId' => $templateTypeId])->fetchOne();

        if ($templateId === false || !\is_string($templateId)) {
            return null;
        }

        return $templateId;
    }

    private function fetchLanguageId(string $code, Connection $connection): ?string
    {
        $langId = $connection->fetchOne('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => $code]);

        if (!$langId) {
            return null;
        }

        return $langId;
    }

    private function updateMailTemplateTranslation(
        Connection $connection,
        string $mailTemplateId,
        ?string $langId,
        ?string $contentHtml,
        ?string $contentPlain,
        ?string $senderName = null
    ): void {
        if (!$langId) {
            return;
        }

        $sqlParams = [];

        if ($contentHtml !== null) {
            $sqlParams['content_html'] = $contentHtml;
        }

        if ($contentPlain !== null) {
            $sqlParams['content_plain'] = $contentPlain;
        }

        if ($senderName !== null) {
            $sqlParams['sender_name'] = $senderName;
        }

        $connection->update('mail_template_translation', $sqlParams, [
            'mail_template_id' => $mailTemplateId,
            'language_id' => $langId,
            'updated_at' => null,
        ]);
    }

    private function getOrderConfirmationHtmlTemplateEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">

{% set currencyIsoCode = order.currency.isoCode %}
{{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br>
<br>
Thank you for your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}.<br>
<br>
<strong>Information on your order:</strong><br>
<br>

<table width="80%" border="0" style="font-family:Arial, Helvetica, sans-serif; font-size:12px;">
    <tr>
        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Pos.</strong></td>
        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Description</strong></td>
        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Quantities</strong></td>
        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Price</strong></td>
        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Total</strong></td>
    </tr>

    {% for lineItem in order.lineItems %}
    <tr>
        <td style="border-bottom:1px solid #cccccc;">{{ loop.index }} </td>
        <td style="border-bottom:1px solid #cccccc;">
          {{ lineItem.label|u.wordwrap(80) }}<br/>
            {% if lineItem.payload.options|length >= 1 %}
                {% for option in lineItem.payload.options %}
                    {{ option.group }}: {{ option.option }}
                    {% if lineItem.payload.options|last != option %}
                        {{ " | " }}
                    {% endif %}
                {% endfor %}
                <br/>
            {% endif %}
          {% if lineItem.payload.productNumber is defined %}Art. No.: {{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}
        </td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.quantity }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.unitPrice|currency(currencyIsoCode) }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.totalPrice|currency(currencyIsoCode) }}</td>
    </tr>
    {% endfor %}
</table>

{% set delivery = order.deliveries.first %}
<p>
    <br>
    <br>
    Shipping costs: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>

    Net total: {{ order.amountNet|currency(currencyIsoCode) }}<br>
    {% for calculatedTax in order.price.calculatedTaxes %}
        {% if order.taxStatus is same as(\'net\') %}plus{% else %}including{% endif %} {{ calculatedTax.taxRate }}% VAT. {{ calculatedTax.tax|currency(currencyIsoCode) }}<br>
    {% endfor %}
    <strong>Total gross: {{ order.amountTotal|currency(currencyIsoCode) }}</strong><br>

    <br>

    <strong>Selected payment type:</strong> {{ order.transactions.first.paymentMethod.name }}<br>
    {{ order.transactions.first.paymentMethod.description }}<br>
    <br>

    <strong>Selected shipping type:</strong> {{ delivery.shippingMethod.name }}<br>
    {{ delivery.shippingMethod.description }}<br>
    <br>

    {% set billingAddress = order.addresses.get(order.billingAddressId) %}
    <strong>Billing address:</strong><br>
    {{ billingAddress.company }}<br>
    {{ billingAddress.name }}<br>
    {{ billingAddress.street }} <br>
    {{ billingAddress.zipcode }} {{ billingAddress.city }}<br>
    {{ billingAddress.country.name }}<br>
    <br>

    <strong>Shipping address:</strong><br>
    {{ delivery.shippingOrderAddress.company }}<br>
    {{ delivery.shippingOrderAddress.name }}<br>
    {{ delivery.shippingOrderAddress.street }} <br>
    {{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}<br>
    {{ delivery.shippingOrderAddress.country.name }}<br>
    <br>
    {% if billingAddress.vatId %}
        Your VAT-ID: {{ billingAddress.vatId }}
        In case of a successful order and if you are based in one of the EU countries, you will receive your goods exempt from turnover tax.<br>
    {% endif %}
    <br/>
    You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
    </br>
    If you have any questions, do not hesitate to contact us.

</p>
<br>
</div>
        ';
    }

    private function getOrderConfirmationPlainTemplateEn(): string
    {
        return '{% set currencyIsoCode = order.currency.isoCode %}
{{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

Thank you for your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}.

Information on your order:

Pos.   Art.No.			Description			Quantities			Price			Total
{% for lineItem in order.lineItems %}
{{ loop.index }}      {% if lineItem.payload.productNumber is defined %}{{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}				{{ lineItem.label|u.wordwrap(80) }}{% if lineItem.payload.options|length >= 1 %}, {% for option in lineItem.payload.options %}{{ option.group }}: {{ option.option }}{% if lineItem.payload.options|last != option %}{{ " | " }}{% endif %}{% endfor %}{% endif %}			{{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
{% endfor %}

{% set delivery = order.deliveries.first %}

Shipping costs: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}
Net total: {{ order.amountNet|currency(currencyIsoCode) }}
    {% for calculatedTax in order.price.calculatedTaxes %}
           {% if order.taxStatus is same as(\'net\') %}plus{% else %}including{% endif %} {{ calculatedTax.taxRate }}% VAT. {{ calculatedTax.tax|currency(currencyIsoCode) }}
    {% endfor %}
Total gross: {{ order.amountTotal|currency(currencyIsoCode) }}


Selected payment type: {{ order.transactions.first.paymentMethod.name }}
{{ order.transactions.first.paymentMethod.description }}

Selected shipping type: {{ delivery.shippingMethod.name }}
{{ delivery.shippingMethod.description }}

{% set billingAddress = order.addresses.get(order.billingAddressId) %}
Billing address:
{{ billingAddress.company }}
{{ billingAddress.name }}
{{ billingAddress.street }}
{{ billingAddress.zipcode }} {{ billingAddress.city }}
{{ billingAddress.country.name }}

Shipping address:
{{ delivery.shippingOrderAddress.company }}
{{ delivery.shippingOrderAddress.name }}
{{ delivery.shippingOrderAddress.street }}
{{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}
{{ delivery.shippingOrderAddress.country.name }}

{% if billingAddress.vatId %}
Your VAT-ID: {{ billingAddress.vatId }}
In case of a successful order and if you are based in one of the EU countries, you will receive your goods exempt from turnover tax.
{% endif %}

You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
If you have any questions, do not hesitate to contact us.

However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getOrderConfirmationHTMLTemplateZhCn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">

{% set currencyIsoCode = order.currency.isoCode %}
您好 {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br>
<br>
感谢您在 {{ salesChannel.name }} 下单（订单号：{{order.orderNumber}}），下单时间 {{ order.orderDateTime|date }}。<br>
<br>
<strong>订单信息：</strong><br>
<br>

<table width="80%" border="0" style="font-family:Arial, Helvetica, sans-serif; font-size:12px;">
    <tr>
        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Pos.</strong></td>
        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>商品名称</strong></td>
        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>数量</strong></td>
        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>单价</strong></td>
        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>金额</strong></td>
    </tr>s

    {% for lineItem in order.lineItems %}
    <tr>
        <td style="border-bottom:1px solid #cccccc;">{{ loop.index }} </td>
        <td style="border-bottom:1px solid #cccccc;">
          {{ lineItem.label|u.wordwrap(80) }}<br/>
          {% if lineItem.payload.options|length >= 1 %}
                {% for option in lineItem.payload.options %}
                    {{ option.group }}: {{ option.option }}
                    {% if lineItem.payload.options|last != option %}
                        {{ " | " }}
                    {% endif %}
                {% endfor %}
                <br/>
            {% endif %}
          {% if lineItem.payload.productNumber is defined %}商品编号：{{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}
        </td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.quantity }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.unitPrice|currency(currencyIsoCode) }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.totalPrice|currency(currencyIsoCode) }}</td>
    </tr>
    {% endfor %}
</table>

{% set delivery = order.deliveries.first %}
<p>
    <br>
    <br>
    运费：{{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>
    净额合计：{{ order.amountNet|currency(currencyIsoCode) }}<br>
        {% for calculatedTax in order.price.calculatedTaxes %}
            {% if order.taxStatus is same as(\'net\') %}另加{% else %}含{% endif %} {{ calculatedTax.taxRate }}% 增值税 {{ calculatedTax.tax|currency(currencyIsoCode) }}<br>
        {% endfor %}
    <strong>总额合计：{{ order.amountTotal|currency(currencyIsoCode) }}</strong><br>
    <br>

    <strong>支付方式：</strong> {{ order.transactions.first.paymentMethod.name }}<br>
    {{ order.transactions.first.paymentMethod.description }}<br>
    <br>

    <strong>配送方式：</strong> {{ delivery.shippingMethod.name }}<br>
    {{ delivery.shippingMethod.description }}<br>
    <br>

    {% set billingAddress = order.addresses.get(order.billingAddressId) %}
    <strong>账单地址：</strong><br>
    {{ billingAddress.company }}<br>
    {{ billingAddress.name }}<br>
    {{ billingAddress.street }} <br>
    {{ billingAddress.zipcode }} {{ billingAddress.city }}<br>
    {{ billingAddress.country.name }}<br>
    <br>

    <strong>收货地址：</strong><br>
    {{ delivery.shippingOrderAddress.company }}<br>
    {{ delivery.shippingOrderAddress.name }}<br>
    {{ delivery.shippingOrderAddress.street }} <br>
    {{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}<br>
    {{ delivery.shippingOrderAddress.country.name }}<br>
    <br>
    {% if billingAddress.vatId %}
        您的增值税号：{{ billingAddress.vatId }}
        验证通过且您从欧盟境外
        下单，将免税发货。 <br>
    {% endif %}
    <br/>
    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
    </br>
    如有疑问，欢迎随时联系我们。

</p>
<br>
</div>';
    }

    private function getOrderConfirmationPlainTemplateZhCn(): string
    {
        return '{% set currencyIsoCode = order.currency.isoCode %}
您好 {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

感谢您在 {{ salesChannel.name }} 下单（订单号：{{order.orderNumber}}），下单时间 {{ order.orderDateTime|date }}。

订单信息：

序号 商品编号			商品名称			数量			单价			金额
{% for lineItem in order.lineItems %}
{{ loop.index }}      {% if lineItem.payload.productNumber is defined %}{{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}				{{ lineItem.label|u.wordwrap(80) }}{% if lineItem.payload.options|length >= 1 %}, {% for option in lineItem.payload.options %}{{ option.group }}: {{ option.option }}{% if lineItem.payload.options|last != option %}{{ " | " }}{% endif %}{% endfor %}{% endif %}				{{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
{% endfor %}

{% set delivery = order.deliveries.first %}

运费：{{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}
净额合计：{{ order.amountNet|currency(currencyIsoCode) }}
    {% for calculatedTax in order.price.calculatedTaxes %}
        {% if order.taxStatus is same as(\'net\') %}另加{% else %}含{% endif %} {{ calculatedTax.taxRate }}% 增值税 {{ calculatedTax.tax|currency(currencyIsoCode) }}
    {% endfor %}
总额合计：{{ order.amountTotal|currency(currencyIsoCode) }}


支付方式：{{ order.transactions.first.paymentMethod.name }}
{{ order.transactions.first.paymentMethod.description }}

配送方式：{{ delivery.shippingMethod.name }}
{{ delivery.shippingMethod.description }}

{% set billingAddress = order.addresses.get(order.billingAddressId) %}
账单地址：
{{ billingAddress.company }}
{{ billingAddress.name }}
{{ billingAddress.street }}
{{ billingAddress.zipcode }} {{ billingAddress.city }}
{{ billingAddress.country.name }}

收货地址：
{{ delivery.shippingOrderAddress.company }}
{{ delivery.shippingOrderAddress.name }}
{{ delivery.shippingOrderAddress.street }}
{{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}
{{ delivery.shippingOrderAddress.country.name }}

{% if billingAddress.vatId %}
您的增值税号：{{ billingAddress.vatId }}
验证通过且您从欧盟境外
下单，将免税发货。
{% endif %}

您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
如有疑问，欢迎随时联系我们。';
    }
}
