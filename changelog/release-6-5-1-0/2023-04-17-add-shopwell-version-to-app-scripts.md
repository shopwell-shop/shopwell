---
title: Add shopwell version to app scripts
issue: NEXT-26010
---
# Core
* Changed `\Shopwell\Core\Framework\Script\Execution\ScriptExecutor` to add `shopwell.version` global variable to app scripts.
* Changed `\Shopwell\Core\Framework\Adapter\Twig\Extension\PhpSyntaxExtension` to add `version_compare` function to app scripts.
___
# Upgrade Information
## App scripts have access to shopwell version

App scripts now have access to the shopwell version via the `shopwell.version` global variable.
```twig
{% if version_compare('6.4', shopwell.version, '<=') %}
    {# 6.4 or lower compatible code #}
{% else %}
    {# 6.5 or higher compatible code #}    
{% endif %}
```
