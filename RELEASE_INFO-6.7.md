# 6.7.0.0 (upcoming)

## Core

## Administration

### Organization settings layout

Organization settings now open a shared tree workspace, with organizations as independent roots and their units nested below them.
The index, create and detail routes reuse this workspace; the detail route accepts `unitId` and `parentId` query parameters to restore unit selection and creation context.
Extensions can use the sidebar and node action slots of `sw-settings-organization-unit-tree`, which now receives both organizations and units.
Extensions of the former `sw_settings_organization_detail_split`, `left`, `left_header`, `right`, and `no_selection` blocks should target `sw_settings_organization_detail_sidebar`, `tree`, or `unit_form` instead, using the `sw_settings_organization_detail_` prefix.
New organizations open the `sw.settings.organization.create` route, which reuses the detail form.
New units open an unsaved entity in the detail form with the selected parent prefilled.
The former organization and unit creation modal blocks are replaced by the detail form blocks.
Changes are persisted through Save and reverted through Cancel using the entity repository.

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
