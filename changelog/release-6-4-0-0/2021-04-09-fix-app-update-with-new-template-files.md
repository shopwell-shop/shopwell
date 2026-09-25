---
title: Fix app update with new template files
issue: NEXT-14692
---
# Core
* Changed `\Shopwell\Core\Framework\App\Lifecycle\Persister\TemplatePersister::updateTemplates` to mark newly created templates as active based on the active state of the app itself.
