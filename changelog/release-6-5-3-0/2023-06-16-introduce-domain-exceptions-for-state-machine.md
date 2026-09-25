---
title: Introduce domain exception for state machine
issue: NEXT-28609
author: Michel Bade
author_email: m.bade@shopwell.com
author_github: @cyl3x
---
# Core
* Deprecated the following exceptions in replacement for Domain Exceptions
    * `Shopwell\Core\System\StateMachine\Exception\StateMachineInvalidEntityIdException`
    * `Shopwell\Core\System\StateMachine\Exception\StateMachineInvalidStateFieldException`
    * `Shopwell\Core\System\StateMachine\Exception\StateMachineNotFoundException`
    * `Shopwell\Core\System\StateMachine\Exception\StateMachineStateNotFoundException`
    * `Shopwell\Core\System\StateMachine\Exception\StateMachineWithoutInitialStateException`
