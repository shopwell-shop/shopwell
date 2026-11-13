# Default Storefront theme

## Source structure

The default theme retains the standard Storefront source structure and build entry points.
`theme.json` compiles `app/storefront/src/scss/base.scss` followed by `skin/shopwell/_base.scss`, then plugin styles.
The entries explicitly select the new foundation; unimported former partials are not part of the default stylesheet.
Bootstrap, the date picker and slider foundation remain available to existing interactions.

| Location | Responsibility |
| --- | --- |
| `scss/abstract` | Bootstrap variables, shared functions and mixins |
| `scss/base` | Document flow, accessibility and reset rules |
| `scss/component` | Shared runtime contracts such as forms, icons, loaders and offcanvas |
| `scss/layout` | Page containers, header geometry, navigation, search suggestions and consent positioning |
| `scss/page` | Account, product and checkout page composition |
| `scss/skin/shopwell` | Default palette, typography, surfaces, borders and component visual tokens |
| `views/components/Sw` | Reusable Twig UX components with their local SCSS and optional JavaScript |
| `views/storefront` | Page composition, route binding and legacy business-template entry points |

Component-local SCSS owns the component's geometry and responsive states.
It consumes Bootstrap variables and optional `--sw-*` properties rather than depending on a demo layout identifier or product.
Default visual values belong in `skin/shopwell`.
Do not import the former skin partials as a fallback when adding a missing page style.

## Extension contracts

Child themes continue using the normal Storefront theme inheritance and `theme.json` entry ordering.
Use theme configuration for brand colors and fonts, CSS custom properties for component surfaces and spacing, and the component's named Twig blocks or slots for markup changes.
For example, override `--sw-account-sidebar-width` for shell geometry or `--sw-teaser-background` for teaser surfaces in the child theme's stylesheet.
Keep form names, route parameters and `data-*` interaction hooks when replacing blocks.
Co-located component styles are discovered by the component build pipeline; no separate application or manually maintained component import list is required.

The default header composes the logo, desktop navigation and actions in the Bootstrap `header-row` in `storefront/layout/header/header.html.twig`.
Override `layout_header_navbar` there to replace the navigation region, or its nested `base_navigation` and `base_navigation_inner` blocks to keep part of the default wrapper.
The outer `storefront/layout/header.html.twig` still owns the header and initial offcanvas navigation content.
`layout_header_navigation_toggle` supplies the single menu trigger below `lg`; account and cart retain their business includes and plugin hooks.
The navbar template accepts `navigationContainerClass`, defaulting to `container`, so callers can reuse it inside an existing container.
The header search trigger keeps `id="header-search-toggle"` and `aria-controls="searchCollapse"`; the search skip link targets this visible trigger and the search widget focuses its associated input.

## Elements and backend requirements

| Element or UX | Reuse and extension | Backend requirement |
| --- | --- | --- |
| `Sw:Grid:Container`, text, image and product slider | Existing Studio composition primitives | Existing layout and product data |
| `Sw:Content:Teaser` | Image, title, text and action; card, split, hero, compact and icon layouts share one contract | New Studio type declaration and preset; no new entity or Administration module |
| `Sw:Search:Field` | Header and content search share the real suggestion service | New Studio type declaration; existing search routes |
| Header and footer templates | Existing Storefront blocks and business widgets compose the default shell | Existing pagelets and configuration; Content assignment integration is outside this visual refactor |
| `Sw:Content:ListingToolbar` | Generic GET search, conditions, navigation and filters; mobile bottom sheet | No generic backend; each page supplies its own validated filters |
| `Sw:Order:Card` | Full and compact presentation of a supplied order | Existing customer-scoped order route and associations |
| Account metrics and recent orders | Account page composition using actual wallet, rewards and order data | Overview loader and customer-scoped order counts |
| Campaign notification | Compose a static linked teaser in Studio | No notification entity is introduced; scheduled, dismissible or audience-targeted notices need explicit backend support |
| Editorial content | Link to actual published Blog content | Existing Blog types and publishing; do not store notification state in Blog |

The homepage is a demo layout assembled from reusable elements.
It does not require a special homepage component or a dedicated campaign backend.
Teasers expose `tone` (`subtle` or `emphasis`) and `linkStyle` (`link`, `button` or `stretched`) through the existing Studio property editor.
The `related` slot on `Sw:Search:Field` accepts text content for editorial search links; these links are separate from live suggestions and do not imply search statistics.
Product cards expose `--sw-product-card-media-ratio` (default `20 / 21`) for consistent image framing; `mediaHeight` still supplies an explicit height when set.
The default skin gives mobile product rails a partial next card to indicate horizontal scrolling.
The default header and footer retain the standard Storefront template entry points and pagelet data.
Header/footer Content assignment APIs remain independent of this theme rendering path.
The standalone footer UX newsletter slot is an extension point and does not implement subscription handling.

## Verification

Run Storefront Twig lint, Storefront ESLint, targeted Storefront Stylelint, translation validation, component tests and theme compilation after relevant changes.
Check desktop and mobile rendering through the real Storefront, including search suggestions, account filters, empty results, product details and cart interactions.
The HTML prototype and theme backup are kept outside this repository.
