/**
 * @sw-package discovery
 */
import body from './body.json.twig?raw';

Shopwell.Service('exportTemplateService').registerProductExportTemplate({
    name: 'open_ai',
    translationKey: 'sw-sales-channel.detail.agenticCommerce.templates.template-label.open-ai',
    salesChannelTypeId: Shopwell.Defaults.agenticCommerceTypeId,
    providerName: 'open-ai',
    headerTemplate: '',
    bodyTemplate: body.trim(),
    footerTemplate: '',
    encoding: 'UTF-8',
    fileFormat: 'jsonl',
    generateByCronjob: false,
    interval: 86400,
});
