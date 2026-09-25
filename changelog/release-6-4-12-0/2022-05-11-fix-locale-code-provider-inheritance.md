---
title: Fix locale code provider on inherited locale code
issue: NEXT-21596
author: Silvio Kennecke
author_github: @silviokennecke
---
# Core
*  Changed `\Shopwell\Core\System\Locale\LanguageLocaleCodeProvider::getLanguages()` to resolve inherited language codes from parent language.
