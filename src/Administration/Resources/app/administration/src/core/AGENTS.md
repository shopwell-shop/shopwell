# Core Layer - AGENTS.md

> **Detailed Docs**: `technical-docs/04-data-layer/` for Repository/Entity patterns

## Critical Rules

### Dependency Flow (STRICT)
```
Modules → App → Core  ✅
Core → App/Module     ❌ NEVER (breaks architecture)
```

### Access Pattern
```ts
// ✅ ALWAYS - Enables decoration/override
Shopwell.Component, Shopwell.Service(), Shopwell.Store

// ❌ NEVER - Bypasses plugin system
import { Component } from 'src/core/factory/component.factory';
```

## Key Directories

### `factory/` - Creation Patterns
- **component.factory**: Component registration (register, extend, override, wrapComponentConfig)
- **service.factory**: DI container (`Shopwell.Service().register()`)
- **state.factory**: Pinia stores (`Shopwell.Store.register()`)
- **module.factory**: Module registration
- **http.factory**: Axios with auth

### `data/` - Repository System (See: AGENTS.md)
- **repository-factory**: Creates repositories
- **entity**: Reactive entities with `.getOrigin()`, `.getDraft()`
- **criteria**: Query builder from `@shopwell-ag/meteor-admin-sdk`
- **changeset-generator**: Minimal diff (only changed fields)
- **error-resolver**: Maps API errors to fields

### `service/` - Core Services
- **api.service**: Base for domain services
- **login.service**: Auth, token refresh
- **validation.service**: Form validation
- **utils/**: Pure functions (format, string, object, types)

### `helper/` - Utilities
- **sanitizer**: XSS prevention
- **retry**: Exponential backoff
- **device**: Mobile/desktop detection

## Anti-Patterns

❌ Core importing from app/module
❌ Direct entity manipulation without repository
❌ Large Criteria page sizes (>100)
❌ Bypassing Shopwell.X global (breaks decoration)

**See**: `src/core/data/AGENTS.md` for Repository details
