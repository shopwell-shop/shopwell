---
title: Copy app snippets after system language change
issue: #11223
author: Frederik Schmitt
author_email: f.schmitt@shopwell.com
author_github: @fschmtt
---
# Core
* Changed `Shopwell\Core\Maintenance\System\Service\SystemLanguageChangeEvent` to include both `$previousLocaleCode` (e.g. `en-GB`) and `$newLocaleCode` (e.g. `en-US`) properties.
___
# Administration
* Added `Shopwell\Administration\Framework\App\Subscriber\SystemLanguageChangedSubscriber` to update app admin snippets after a system language change.
