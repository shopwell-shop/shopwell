# Administration menus

Core and active plugins declare their menu defaults in `Resources/config/admin-menu.xml`.
The container validates and compiles these files; navigation requests do not read XML.
Frontend modules register the corresponding page routes, privileges and navigation bindings.
The database stores menu customizations and literal translated titles.

## Declare menus

Use stable, readable IDs with a plugin-specific prefix.
Nested menus inherit their parent; a top-level `parent` attribute attaches a plugin entry to a core menu.
Do not add `parent` to a nested menu.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<admin-menu>
    <menu id="example-tools" type="folder" parent="sw-system" position="60">
        <title lang="en-GB">Tools</title>
        <title lang="zh-CN">工具</title>
        <menu id="example-tools-group" type="group" group="tools" position="10">
            <title lang="en-GB">Tools</title>
            <title lang="zh-CN">工具</title>
            <menu id="example-reports" privilege="example.viewer" position="10">
                <title lang="en-GB">Reports</title>
                <title lang="zh-CN">报表</title>
                <custom-fields lang="zh-CN">
                    <custom-field name="example_description">查看业务报表</custom-field>
                    <custom-field name="example_options" type="json">{"compact":true}</custom-field>
                </custom-fields>
            </menu>
        </menu>
    </menu>
</admin-menu>
```

`title` contains display text, never a snippet key.
The `lang` attribute is a DAL locale code, such as `en-GB` or `zh-CN`; it is unrelated to Administration snippet filenames.
Translations are persisted into `admin_menu_item_translation`, including each language's standard DAL `customFields`.
Custom fields default to strings; `type="json"` accepts JSON values.
A custom-fields locale must also have a title.
Only installed languages can be persisted; the visible navigation can fall back to the XML title for the requested interface locale.

`menu` is the default type for a registered page.
`folder` contains children, `group` is a non-navigable section, `link` requires an HTTP(S) `link` attribute, and `hidden` excludes an entry from navigation.
`position` orders siblings and `group` identifies a settings section.
Explicit group IDs such as `example-tools-group` are stable identifiers, not generated hashes.
Duplicate IDs, missing parents and parent cycles are rejected before synchronization.
A plugin may attach to a core parent or its own entries, but not another plugin's entries.

Register the page binding in the Administration module:

```js
navigation: [{
    id: 'example-reports',
    path: 'example.reports.index',
    privilege: 'example.viewer',
}],
```

Keep labels, hierarchy and positions in XML so there is one source for the defaults.
Programmatic declarations can implement `AdminMenuDefinitionProviderInterface` and use the `shopwell.admin_menu_definition_provider` service tag when static XML is insufficient.
Providers declare their own entries; duplicate identities are not an override mechanism.

## Synchronization and customization

Rebuild the container after editing XML.
Run `bin/console admin-menu:sync` to synchronize core defaults.
Plugin lifecycle handling refreshes plugin XML before synchronization, seeds and updates plugin menus, hides deactivated entries and preserves administrator customizations.
This refresh also discovers a menu file added or removed by a plugin update without reading files during navigation requests.
Core default changes refresh unmodified entries; entries marked `configOverridden` keep their stored translations, custom fields and placement.
Changing an ID changes its identity, so keep IDs stable when renaming a displayed title.

The visible and route-whitelist endpoints accept an optional `locale` query parameter for literal interface-language titles.
The visible endpoint's `managedKeys` prevents hidden provider entries from being restored by frontend declarations.

## Runtime visibility event

Subscribe to `AdminMenuVisibilityEvent` for conditions that cannot be expressed as static declarations or role menu selections, such as tenant feature availability or licensing.
The event is dispatched once for each navigation build, after stored and synthesized entries are assembled and before hierarchy rendering and role selection.
Listeners receive the request `Context` and optional interface locale, and may call `hide()` with declared internal keys.
Hiding a parent excludes its subtree without changing the database.
Multiple listeners accumulate hidden keys; a listener cannot restore a key hidden by another.

```php
public static function getSubscribedEvents(): array
{
    return [AdminMenuVisibilityEvent::class => 'filterNavigation'];
}

public function filterNavigation(AdminMenuVisibilityEvent $event): void
{
    if (!$this->features->isFinanceEnabled($event->getContext())) {
        $event->hide('sw-finance');
    }
}
```

Import `Shopwell\Core\System\AdminMenu\Event\AdminMenuVisibilityEvent` in the subscriber.
`features` in this example is the plugin's own feature service.
Menu visibility does not authorize page or API access; enforce those permissions at their routes.
The event does not mutate menu definitions or bypass administrator role selections.

For persisted menu changes, reuse DAL events such as `admin_menu_item.written`, `admin_menu_item.deleted` and `admin_menu_item_translation.written`, or `EntityWrittenContainerEvent` when the whole write is needed.
There are no duplicate AdminMenu create/update/delete events.
