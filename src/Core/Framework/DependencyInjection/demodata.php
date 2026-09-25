<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Cart\CartCalculator;
use Shopwell\Core\Checkout\Cart\Order\OrderConverter;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Checkout\Customer\Service\ProductReviewCountService;
use Shopwell\Core\Checkout\Order\OrderDefinition;
use Shopwell\Core\Content\Flow\Api\FlowActionCollector;
use Shopwell\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Shopwell\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopwell\Core\Content\Media\File\FileNameProvider;
use Shopwell\Core\Content\Media\File\FileSaver;
use Shopwell\Core\Content\Media\MediaDefinition;
use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopwell\Core\Content\Product\DataAbstractionLayer\StatesUpdater;
use Shopwell\Core\Content\ProductStream\ProductStreamDefinition;
use Shopwell\Core\Content\Rule\RuleDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopwell\Core\Framework\Demodata\Command\DemodataCommand;
use Shopwell\Core\Framework\Demodata\DemodataService;
use Shopwell\Core\Framework\Demodata\Generator\CategoryGenerator;
use Shopwell\Core\Framework\Demodata\Generator\CustomerGenerator;
use Shopwell\Core\Framework\Demodata\Generator\CustomFieldGenerator;
use Shopwell\Core\Framework\Demodata\Generator\FlowGenerator;
use Shopwell\Core\Framework\Demodata\Generator\MailHeaderFooterGenerator;
use Shopwell\Core\Framework\Demodata\Generator\MailTemplateGenerator;
use Shopwell\Core\Framework\Demodata\Generator\MediaGenerator;
use Shopwell\Core\Framework\Demodata\Generator\NewsletterRecipientGenerator;
use Shopwell\Core\Framework\Demodata\Generator\OrderGenerator;
use Shopwell\Core\Framework\Demodata\Generator\ProductGenerator;
use Shopwell\Core\Framework\Demodata\Generator\ProductManufacturerGenerator;
use Shopwell\Core\Framework\Demodata\Generator\ProductReviewGenerator;
use Shopwell\Core\Framework\Demodata\Generator\ProductStreamGenerator;
use Shopwell\Core\Framework\Demodata\Generator\PromotionGenerator;
use Shopwell\Core\Framework\Demodata\Generator\PropertyGroupGenerator;
use Shopwell\Core\Framework\Demodata\Generator\RuleGenerator;
use Shopwell\Core\Framework\Demodata\Generator\SalesChannelDomainGenerator;
use Shopwell\Core\Framework\Demodata\Generator\TagGenerator;
use Shopwell\Core\Framework\Demodata\Generator\UserGenerator;
use Shopwell\Core\Framework\Event\BusinessEventCollector;
use Shopwell\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\Tag\TagDefinition;
use Shopwell\Core\System\User\UserDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(DemodataCommand::class)
        ->args([
            service(DemodataService::class),
            service('event_dispatcher'),
            param('kernel.environment'),
        ])
        ->tag('console.command');

    $services->set(DemodataService::class)
        ->args([
            tagged_iterator('shopware.demodata_generator'),
            param('kernel.project_dir'),
            service(DefinitionInstanceRegistry::class),
            service(ClockInterface::class),
        ]);

    // Generators
    // The option-* tag attributes exist in dashed AND underscore form because the XML loader
    // emitted both spellings; both are kept so the compiled container stays identical.
    $services->set(RuleGenerator::class)
        ->args([
            service('rule.repository'),
            service(EntityWriter::class),
            service('payment_method.repository'),
            service('shipping_method.repository'),
            service(RuleDefinition::class),
            service(ClockInterface::class),
        ])
        ->tag('shopware.demodata_generator', ['option-name' => 'rules', 'option-default' => 25, 'option_name' => 'rules', 'option_default' => 25]);

    $services->set(CustomerGenerator::class)
        ->args([
            service(EntityWriter::class),
            service(Connection::class),
            service('customer_group.repository'),
            service(NumberRangeValueGeneratorInterface::class),
            service(CustomerDefinition::class),
        ])
        ->tag('shopware.demodata_generator', ['option-name' => 'customers', 'option-default' => 60, 'option_name' => 'customers', 'option_default' => 60]);

    $services->set(PropertyGroupGenerator::class)
        ->args([
            service('property_group.repository'),
        ])
        ->tag('shopware.demodata_generator', ['option-name' => 'properties', 'option-default' => 10, 'option-description' => 'Property group count (option count rand(30-300))', 'option_name' => 'properties', 'option_default' => 10, 'option_description' => 'Property group count (option count rand(30-300))']);

    $services->set(CategoryGenerator::class)
        ->args([
            service('category.repository'),
            service('cms_page.repository'),
            service(Connection::class),
        ])
        ->tag('shopware.demodata_generator', ['option-name' => 'categories', 'option-default' => 10, 'option_name' => 'categories', 'option_default' => 10]);

    $services->set(ProductManufacturerGenerator::class)
        ->args([
            service(EntityWriter::class),
            service(ProductManufacturerDefinition::class),
        ])
        ->tag('shopware.demodata_generator', ['option-name' => 'manufacturers', 'option-default' => 60, 'option_name' => 'manufacturers', 'option_default' => 60]);

    $services->set(TagGenerator::class)
        ->args([
            service(EntityWriter::class),
            service(TagDefinition::class),
        ])
        ->tag('shopware.demodata_generator', ['option-name' => 'tags', 'option-default' => 50, 'option_name' => 'tags', 'option_default' => 50]);

    $services->set(ProductReviewGenerator::class)
        ->args([
            service(EntityWriter::class),
            service(ProductReviewDefinition::class),
            service(Connection::class),
            service(ProductReviewCountService::class),
        ])
        ->tag('shopware.demodata_generator', ['option-name' => 'reviews', 'option-default' => 20, 'option_name' => 'reviews', 'option_default' => 20]);

    $services->set(ProductGenerator::class)
        ->args([
            service(Connection::class),
            service(DefinitionInstanceRegistry::class),
            service(InheritanceUpdater::class),
            service(StatesUpdater::class)->nullOnInvalid(),
        ])
        ->tag('shopware.demodata_generator', ['option-name' => 'products', 'option-default' => 1000, 'option_name' => 'products', 'option_default' => 1000]);

    $services->set(PromotionGenerator::class)
        ->args([
            service(Connection::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.demodata_generator', ['option-name' => 'promotions', 'option-default' => 50, 'option_name' => 'promotions', 'option_default' => 50]);

    $services->set(FlowGenerator::class)
        ->args([
            service(Connection::class),
            service(DefinitionInstanceRegistry::class),
            service(BusinessEventCollector::class),
            service(FlowActionCollector::class),
        ])
        ->tag('shopware.demodata_generator', ['option-name' => 'flows', 'option-default' => 0, 'option_name' => 'flows', 'option_default' => 0]);

    $services->set(MediaGenerator::class)
        ->args([
            service(EntityWriter::class),
            service(FileSaver::class),
            service(FileNameProvider::class),
            service('media_default_folder.repository'),
            service('media_folder.repository'),
            service(MediaDefinition::class),
            service(Connection::class),
        ])
        ->tag('shopware.demodata_generator', ['option-name' => 'media', 'option-default' => 300, 'option_name' => 'media', 'option_default' => 300]);

    $services->set(ProductStreamGenerator::class)
        ->args([
            service(EntityWriter::class),
            service(ProductStreamDefinition::class),
        ])
        ->tag('shopware.demodata_generator', ['option-name' => 'product-streams', 'option-default' => 10, 'option_name' => 'product-streams', 'option_default' => 10]);

    $services->set(OrderGenerator::class)
        ->args([
            service(Connection::class),
            service(SalesChannelContextFactory::class),
            service(CartService::class),
            service(OrderConverter::class),
            service(EntityWriter::class),
            service(OrderDefinition::class),
            service(CartCalculator::class),
        ])
        ->tag('shopware.demodata_generator', ['option-name' => 'orders', 'option-default' => 60, 'option_name' => 'orders', 'option_default' => 60]);

    $services->set(CustomFieldGenerator::class)
        ->args([
            service('custom_field_set.repository'),
            service(Connection::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.demodata_generator', ['option-name' => 'attribute-sets', 'option-default' => 4, 'option-description' => 'CustomField set count', 'option_name' => 'attribute-sets', 'option_default' => 4, 'option_description' => 'CustomField set count']);

    $services->set(MailTemplateGenerator::class)
        ->args([
            service(EntityWriter::class),
            service('mail_template_type.repository'),
            service(MailTemplateDefinition::class),
        ])
        ->tag('shopware.demodata_generator', ['option-name' => 'mail-template', 'option-default' => 10, 'option_name' => 'mail-template', 'option_default' => 10]);

    $services->set(MailHeaderFooterGenerator::class)
        ->args([
            service(EntityWriter::class),
            service(MailHeaderFooterDefinition::class),
        ])
        ->tag('shopware.demodata_generator', ['option-name' => 'mail-header-footer', 'option-default' => 3, 'option-description' => 'Mail header/footer count', 'option_name' => 'mail-header-footer', 'option_default' => 3, 'option_description' => 'Mail header/footer count']);

    $services->set(SalesChannelDomainGenerator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.demodata_generator', ['option-name' => 'sales-channel-domain', 'option-default' => 1, 'option_name' => 'sales-channel-domain', 'option_default' => 1]);

    $services->set(UserGenerator::class)
        ->args([
            service(EntityWriter::class),
            service(UserDefinition::class),
            service('language.repository'),
        ])
        ->tag('shopware.demodata_generator', ['option-name' => 'users', 'option-default' => 0, 'option_name' => 'users', 'option_default' => 0]);

    $services->set(NewsletterRecipientGenerator::class)
        ->args([
            service(EntityWriter::class),
            service(NewsletterRecipientDefinition::class),
            service(Connection::class),
        ])
        ->tag('shopware.demodata_generator', ['option-name' => 'newsletter-recipients', 'option-default' => 20, 'option_name' => 'newsletter-recipients', 'option_default' => 20]);
};
