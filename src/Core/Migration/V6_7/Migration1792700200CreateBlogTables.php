<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * Creates the model of the built-in blog: an article or video page, archived by categories,
 * written by an author and commented on by the reader.
 *
 * A `blog` is not disableable on purpose - visibility is only decided by `published_at`, where a
 * future date is a scheduled post and the absence of one means it goes live with the next save.
 * Every text a reader sees lives in the translation tables, the slug is unique per language.
 *
 * The type of a blog (`post` or `video`) decides which editor the administration shows and which
 * template the storefront renders. Type specific fields do not pile up on the main table: the
 * video columns live in the 1:1 `blog_video` table.
 *
 * A `blog_comment` references its parent comment directly - the tree is walked in PHP, the table
 * only carries the edge. Deleting a comment takes its whole subtree with it.
 *
 * @internal
 */
#[Package('framework')]
class Migration1792700200CreateBlogTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792700200;
    }

    public function update(Connection $connection): void
    {
        // the blog references its author, so the author comes first
        $this->createBlogAuthorTable($connection);
        $this->createBlogAuthorTranslationTable($connection);
        $this->createBlogTable($connection);
        $this->createBlogTranslationTable($connection);
        $this->createBlogCategoryTable($connection);
        $this->createBlogCategoryTranslationTable($connection);
        $this->createBlogCategoryAssignmentTable($connection);
        $this->createBlogVideoTable($connection);
        $this->createBlogCommentTable($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }

    private function createBlogAuthorTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `blog_author` (
                `id` BINARY(16) NOT NULL,
                `email` VARCHAR(255) NULL,
                `media_id` BINARY(16) NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.blog_author.media_id` (`media_id`),
                CONSTRAINT `fk.blog_author.media_id` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `json.blog_author.custom_fields` CHECK (json_valid(`custom_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    private function createBlogAuthorTranslationTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `blog_author_translation` (
                `blog_author_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `description` LONGTEXT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`blog_author_id`, `language_id`),
                KEY `idx.blog_author_translation.language_id` (`language_id`),
                CONSTRAINT `fk.blog_author_translation.blog_author_id` FOREIGN KEY (`blog_author_id`) REFERENCES `blog_author` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.blog_author_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * The cms page references stay restricted: a layout that is still attached to a blog or a
     * category can not be deleted without breaking the page it renders.
     */
    private function createBlogTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `blog` (
                `id` BINARY(16) NOT NULL,
                `type` VARCHAR(32) NOT NULL DEFAULT \'post\',
                `published_at` DATETIME(3) NULL,
                `author_id` BINARY(16) NULL,
                `cover_media_id` BINARY(16) NULL,
                `cms_page_id` BINARY(16) NULL,
                `cms_page_version_id` BINARY(16) NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.blog.type` (`type`),
                KEY `idx.blog.published_at` (`published_at`),
                KEY `idx.blog.author_id` (`author_id`),
                KEY `idx.blog.cover_media_id` (`cover_media_id`),
                KEY `idx.blog.cms_page_id` (`cms_page_id`, `cms_page_version_id`),
                CONSTRAINT `fk.blog.author_id` FOREIGN KEY (`author_id`) REFERENCES `blog_author` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.blog.cover_media_id` FOREIGN KEY (`cover_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.blog.cms_page_id` FOREIGN KEY (`cms_page_id`, `cms_page_version_id`) REFERENCES `cms_page` (`id`, `version_id`) ON UPDATE CASCADE,
                CONSTRAINT `json.blog.custom_fields` CHECK (json_valid(`custom_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    private function createBlogTranslationTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `blog_translation` (
                `blog_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(255) NOT NULL,
                `teaser` LONGTEXT NULL,
                `content` LONGTEXT NULL,
                `meta_title` VARCHAR(255) NULL,
                `meta_description` VARCHAR(512) NULL,
                `keywords` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`blog_id`, `language_id`),
                UNIQUE KEY `uniq.blog_translation.slug.language_id` (`slug`, `language_id`),
                KEY `idx.blog_translation.language_id` (`language_id`),
                CONSTRAINT `fk.blog_translation.blog_id` FOREIGN KEY (`blog_id`) REFERENCES `blog` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.blog_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    private function createBlogCategoryTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `blog_category` (
                `id` BINARY(16) NOT NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `cms_page_id` BINARY(16) NULL,
                `cms_page_version_id` BINARY(16) NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.blog_category.cms_page_id` (`cms_page_id`, `cms_page_version_id`),
                CONSTRAINT `fk.blog_category.cms_page_id` FOREIGN KEY (`cms_page_id`, `cms_page_version_id`) REFERENCES `cms_page` (`id`, `version_id`) ON UPDATE CASCADE,
                CONSTRAINT `json.blog_category.custom_fields` CHECK (json_valid(`custom_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    private function createBlogCategoryTranslationTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `blog_category_translation` (
                `blog_category_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(255) NOT NULL,
                `description` LONGTEXT NULL,
                `meta_title` VARCHAR(255) NULL,
                `meta_description` VARCHAR(512) NULL,
                `keywords` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`blog_category_id`, `language_id`),
                UNIQUE KEY `uniq.blog_category_translation.slug.language_id` (`slug`, `language_id`),
                KEY `idx.blog_category_translation.language_id` (`language_id`),
                CONSTRAINT `fk.blog_category_translation.blog_category_id` FOREIGN KEY (`blog_category_id`) REFERENCES `blog_category` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.blog_category_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    private function createBlogCategoryAssignmentTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `blog_category_assignment` (
                `id` BINARY(16) NOT NULL,
                `blog_id` BINARY(16) NOT NULL,
                `blog_category_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.blog_category_assignment.blog_category` (`blog_id`, `blog_category_id`),
                KEY `idx.blog_category_assignment.blog_category_id` (`blog_category_id`),
                CONSTRAINT `fk.blog_category_assignment.blog_id` FOREIGN KEY (`blog_id`) REFERENCES `blog` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.blog_category_assignment.blog_category_id` FOREIGN KEY (`blog_category_id`) REFERENCES `blog_category` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    private function createBlogVideoTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `blog_video` (
                `id` BINARY(16) NOT NULL,
                `blog_id` BINARY(16) NOT NULL,
                `video_url` VARCHAR(2048) NOT NULL,
                `poster_media_id` BINARY(16) NULL,
                `duration_seconds` INT NULL,
                `provider` VARCHAR(64) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.blog_video.blog_id` (`blog_id`),
                KEY `idx.blog_video.poster_media_id` (`poster_media_id`),
                CONSTRAINT `fk.blog_video.blog_id` FOREIGN KEY (`blog_id`) REFERENCES `blog` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.blog_video.poster_media_id` FOREIGN KEY (`poster_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * The name and email of a guest commenter are the only identity a guest has, so the flood
     * protection looks them up by email within one blog. The status starts at `0` - every comment
     * waits for moderation unless the shop owner opens the gate.
     */
    private function createBlogCommentTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `blog_comment` (
                `id` BINARY(16) NOT NULL,
                `blog_id` BINARY(16) NOT NULL,
                `parent_id` BINARY(16) NULL,
                `customer_id` BINARY(16) NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `external_user` VARCHAR(255) NULL,
                `external_email` VARCHAR(255) NULL,
                `title` VARCHAR(255) NULL,
                `content` LONGTEXT NOT NULL,
                `status` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.blog_comment.blog_id_created_at` (`blog_id`, `created_at`),
                KEY `idx.blog_comment.blog_status_created_at` (`blog_id`, `status`, `created_at`),
                KEY `idx.blog_comment.email` (`external_email`, `blog_id`, `created_at`),
                KEY `idx.blog_comment.parent_id` (`parent_id`),
                KEY `idx.blog_comment.customer_id` (`customer_id`),
                KEY `idx.blog_comment.sales_channel_id` (`sales_channel_id`),
                KEY `idx.blog_comment.language_id` (`language_id`),
                CONSTRAINT `fk.blog_comment.blog_id` FOREIGN KEY (`blog_id`) REFERENCES `blog` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.blog_comment.parent_id` FOREIGN KEY (`parent_id`) REFERENCES `blog_comment` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.blog_comment.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.blog_comment.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.blog_comment.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }
}
