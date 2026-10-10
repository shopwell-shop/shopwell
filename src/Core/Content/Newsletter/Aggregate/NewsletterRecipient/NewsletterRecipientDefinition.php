<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient;

use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipientTag\NewsletterRecipientTagDefinition;
use Shopwell\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Choice;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Language\LanguageDefinition;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;
use Shopwell\Core\System\Salutation\SalutationDefinition;
use Shopwell\Core\System\Tag\TagDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class NewsletterRecipientDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'newsletter_recipient';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return NewsletterRecipientCollection::class;
    }

    public function getEntityClass(): string
    {
        return NewsletterRecipientEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of newsletter recipient.'),
            (new StringField('email', 'email'))->addFlags(new Required())->setDescription('Email of the recipient.'),
            (new StringField('title', 'title'))->setDescription('Title of the recipient\'s newsletter.'),
            (new StringField('name', 'name'))->setDescription('Full name of the recipient.'),
            (new StringField('zip_code', 'zipCode'))->setDescription('Zipcode of the recipient\'s address.'),
            (new StringField('city', 'city'))->setDescription('City of the recipient.'),
            (new StringField('street', 'street'))->setDescription('Street of the recipient.'),
            (new StringField('status', 'status'))->addFlags(new Required(), new Choice([
                NewsletterSubscribeRoute::STATUS_NOT_SET,
                NewsletterSubscribeRoute::STATUS_OPT_IN,
                NewsletterSubscribeRoute::STATUS_OPT_OUT,
                NewsletterSubscribeRoute::STATUS_DIRECT,
            ]))->setDescription('When status is set, the NewsletterRecipient is made visible.'),
            (new StringField('hash', 'hash'))->addFlags(new Required())->setDescription('Random string for the subscribe URL if opt-in is used.'),
            (new CustomFields())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            (new DateTimeField('confirmed_at', 'confirmedAt'))->setDescription('Date and time when the Newsletter was received.'),
            new ManyToManyAssociationField('tags', TagDefinition::class, NewsletterRecipientTagDefinition::class, 'newsletter_recipient_id', 'tag_id'),
            (new FkField('salutation_id', 'salutationId', SalutationDefinition::class))->setDescription('Unique identity of salutation.'),
            new ManyToOneAssociationField('salutation', 'salutation_id', SalutationDefinition::class, 'id', false),

            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new Required())->setDescription('Unique identity of language.'),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false),

            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required())->setDescription('Unique identity of the sales channel.'),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
        ]);
    }
}
