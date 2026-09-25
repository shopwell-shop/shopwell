---
title: Unnecessary transitions won't fail any more
issue: NEXT-7683
author: Sebastian Seggewiss
author_email: s.seggewiss@shopwell.com 
author_github: seggewiss
---
# Core
* `\Shopwell\Core\System\StateMachine\StateMachineRegistry::transition` will no longer throw an `IllegalTransitionException` if the from and to state are the same
