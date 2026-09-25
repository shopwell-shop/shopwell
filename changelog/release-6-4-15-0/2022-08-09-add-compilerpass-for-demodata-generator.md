---
title: Add compilerpass for demodata generator
issue: NEXT-22797
author_github: @Dominik28111
---
# Core
* Added compilerpass `Shopwell\Core\Framework\DependencyInjection\CompilerPass\DemodataGeneratorCompilerPass`.
* Deprecated constructor of `Shopwell\Core\Framework\Demodata\Event\DemodataRequestCreatedEvent`, parameter `$input` will be required with v6.5.0.
* Added method `Shopwell\Core\Framework\Demodata\Event\DemodataRequestCreatedEvent::getInput()`, with v6.5.0 no null value will be returned anymore.
* Added method `Shopwell\Core\Framework\Demodata\Command\DemodataCommand::addDefault()`.
___
# Upgrade Information

## Demodata generator registration in DI

Demodata generators now accepts the following new attributes:
* `option-name`: Option name for the command, optional if command has no option.
* `option-default`: Default value for the number of items to generate (Default: 0).
* `option-description`: Description for the command line option, not required.

```xml
<service id="Shopwell\Core\Framework\Demodata\Generator\PropertyGroupGenerator">
    <argument type="service" id="property_group.repository" />
    
    <tag name="shopwell.demodata_generator" option-name="properties" option-default="10" option-description="Property group count (option count rand(30-300))"/>
</service>
```
