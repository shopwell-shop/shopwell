# 6.7.0.0 (upcoming)

## Core

### Free growth alongside paid membership

Customers continue earning tier points during paid membership.
The effective tier is the higher of the earned tier and the valid paid tier; expiry falls back to the earned tier immediately when read.
Paid tier qualification is available as `loyalty_account.paidTierId`, independently of the effective `tierId`.
Existing running subscriptions are migrated automatically.

### After-sales refund destination

Administration refund confirmations default to the original payment sources and can instead credit a registered customer's wallet.
The approve, receive and refund actions accept optional `refundDestination` (`original` or `wallet`); the first payout attempt fixes this value on the return.
Wallet payouts also reduce the collected payment's refundable amount and use the return number for idempotency.
Guest returns support the original payment sources only.

### Blog publishing and content layouts

New Blog entries default to drafts (`active=false`).
Publish an entry by setting `active=true`; a future `publishedAt` schedules its publication.
Blog entries reuse system tags and support native `blog_content_layout` assignments with `rootSource=blog`.
Tags expose their related entries directly through the native `blogs` association.
The Blog installation baseline provisions shared article, video, and listing layouts automatically.
The repair command `bin/console content:blog:provision-layouts` remains available.
The type-specific configuration keys are `core.content_system.default_blog_post_content_layout` and `core.content_system.default_blog_video_content_layout`, with `core.content_system.default_blog_content_layout` as the shared fallback.
The shared homepage/category listing default is configured through `core.content_system.default_blog_listing_content_layout` and uses `rootSource=none`.
Blog layout associations extend `content_layout` through `extensions.blogContentLayouts`.
Explicit Blog assignments take precedence over legacy standalone body layouts, which remain supported.
Register custom types through `BlogTypeRegistry::addType($type, $template, $schemaType)` instead of type handlers.
`GET /api/_action/blog/types` lists registered type names.

## Administration

### Return settings per sales channel

Return rules support sales channel overrides and inheritance from the global configuration.
Return reasons have a separate settings entry and remain shared across sales channels.
The return rules page uses the standard `system.system_config` permission; reason management continues to use the return permissions.

### Configurable administration menus

Menu sections can now be managed as independent `group` nodes in both navigation and settings.
Legacy provider `groupName` declarations and translated group titles are migrated automatically.
The optional `routeKey` field selects a page without changing a system or plugin node’s `internalKey` identity.
Settings overview groups now follow the visible administration menu configuration, including translated group titles and custom links.
The visible menu endpoint returns `groupName`, `groupTitle`, and `managedKeys` so clients can apply stored groups without restoring hidden provider entries from module declarations.
The route whitelist includes installed plugin providers alongside core declarations.

### Organization settings layout

Organization settings now open a shared tree workspace, with organizations as independent roots and their units nested below them.
The index, create and detail routes reuse this workspace; the detail route accepts `unitId` and `parentId` query parameters to restore unit selection and creation context.
Extensions can use the sidebar and node action slots of `sw-settings-organization-unit-tree`, which now receives both organizations and units.
Extensions of the former `sw_settings_organization_detail_split`, `left`, `left_header`, `right`, and `no_selection` blocks should target `sw_settings_organization_detail_sidebar`, `tree`, or `unit_form` instead, using the `sw_settings_organization_detail_` prefix.
New organizations open the `sw.settings.organization.create` route, which reuses the detail form.
New units open an unsaved entity in the detail form with the selected parent prefilled.
The former organization and unit creation modal blocks are replaced by the detail form blocks.
Changes are persisted through Save and reverted through Cancel using the entity repository.

### Blog editor and extension points

The Blog editor provides a wide content canvas, a compact settings sidebar, and separate Content, Layout and SEO tabs.
Register custom editors through `registerBlogType($type, { component, label, icon })`; existing lazy editor loaders remain supported.
Named editors use the Administration component factory, so component extensions apply.
Plugins can extend `sw-blog-detail-content`, `sw-blog-detail-settings`, and `sw-blog-detail-tabs` through the standard component section and tab extension APIs.
Tags use the system tag selector within the editor and do not add a navigation item.
Blog navigation now groups content, categories, authors, and comments under a localized menu folder.
Category management uses one workspace with a persistent category tree and an inline editor with General, Content, Layout, and SEO tabs.
The category tree follows the system menu tree with drag handles, positional drop previews, sibling and child creation, and expand/collapse actions.
Category search uses the standard page search bar, retains matching ancestor paths, and restores expanded branches when cleared.
Root creation uses the page actions; sibling and child creation use the node action menu.
New categories open an unsaved full form in the same workspace and are persisted only through Save.
New authors open the `sw.blog.author.create` route and use the full detail form; records are persisted only through Save.
Categories support parent and child relationships, drag and drop ordering, and menu actions for moving categories.
Tree changes persist immediately after resolving unsaved editor changes.
Moving a category into itself or its descendants is rejected, and categories with children must be emptied before deletion.
The Layout tab displays the effective global layout and supports copying it or restoring the shared default.

### Today dashboard statistics

The Administration homepage now shows today's product sales, successful refunds, wallet top-ups, membership activations, and hourly sales in the operator's timezone.
`GET /api/_admin/dashboard/today` provides these statistics in the system currency, with comparisons to the same local time yesterday for all four metric cards.
The endpoint derives its timezone from the authenticated user profile.
Product sales exclude wallet top-ups and membership purchases, and duplicate successful payment attempts do not create additional sales.
Posted wallet top-ups are reported separately with amount and count, subject to wallet transaction read permission.
Membership activations count distinct customers by successful activation time, including renewals, subject to membership purchase read permission.
Wallet amounts use current exchange factors and are accumulated in system currency minor units.
The former order-amount endpoint has been replaced by the today endpoint.

## Storefront
