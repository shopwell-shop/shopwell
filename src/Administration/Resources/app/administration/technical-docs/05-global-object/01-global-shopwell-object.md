# Global Shopwell Object

The global Shopwell object serves as the central registry and facade for core extensibility in the Shopwell 6 Administration. It provides a unified interface for plugin authors and core developers to access various registries, services, and utilities.

## Why a Global Object?

The global Shopwell object exists for **practical reasons**:

1. **Plugin Developer Experience**: Provides a simple, predictable API that doesn't require complex dependency injection knowledge
2. **Backward Compatibility**: Maintains API stability across Shopwell versions for existing plugins
3. **Discoverability**: Easy to explore available APIs via `window.Shopwell` in browser dev tools
4. **Universal Access**: Available everywhere without imports or injection - crucial for runtime plugin loading

The object is exposed as `window.Shopwell` during application bootstrap, making it globally accessible to all code running in the administration.

## Core Structure

The global Shopwell object exposes the following essential registries:

### Component System (Most Important)
```javascript
Shopwell.Component = {
    register: Function,    // Register new components - primary plugin API
    extend: Function,      // Extend existing components
    override: Function,    // Override existing components
    // ...additional methods
}
```

### Module System
```javascript
Shopwell.Module = {
    register: Function,           // Register new admin modules
    getModuleRegistry: Function,  // Get all registered modules
    // ...additional methods
}
```

### Service Layer
```javascript
Shopwell.Service = ServiceFactory; // Service factory for dependency injection
```

### State Management
```javascript
Shopwell.State = StateFactory(); // Legacy Vuex state (deprecated)
Shopwell.Store = Store.instance; // Current Pinia store instance
```

### API Services
```javascript
Shopwell.ApiService = {
    register: Function,    // Register API services
    getByName: Function,   // Get API service by name
    // ...additional methods
}
```

### Feature Flags
```javascript
Shopwell.Feature = {
    isActive: Function   // Check if feature is active - commonly used
}
```

### Essential Utilities
```javascript
Shopwell.Utils = utils;           // Collection of utility functions
Shopwell.Data = data;             // DAL utilities and repositories
Shopwell.Context = useContext();  // Current application context
Shopwell.Defaults = { /* ... */ }; // System default IDs and values
```

## Creation Process

1. **Initialization**: Created in `src/core/shopwell.ts` as `ShopwellClass` singleton
2. **Container Setup**: Built on BottleJS dependency injection container
3. **Global Assignment**: Set as `window.Shopwell` in `src/index.ts` during bootstrap
4. **Runtime Access**: Available immediately to all plugins and components

## Common Usage Patterns

### Plugin Registration (Primary Use Case)
```javascript
// Most common usage - registering components
Shopwell.Component.register('my-plugin-component', {
    template: '<div>My Component</div>'
});

// Registering modules
Shopwell.Module.register('my-plugin-module', {
    routes: { /* ... */ },
    navigation: { /* ... */ }
});
```

### Service Access
```javascript
const httpClient = Shopwell.Service('httpClient');
const repositoryFactory = Shopwell.Service('repositoryFactory');
```

### Feature Flag Checks
```javascript
if (Shopwell.Feature.isActive('MY_FEATURE')) {
    // Feature-specific code
}
```

## Trade-offs

### Why Global is Beneficial Here
- **Plugin Ecosystem**: Thousands of plugins need consistent, simple API access
- **Runtime Loading**: Plugins load at runtime and need immediate API access
- **Developer Onboarding**: Lower barrier to entry for plugin developers
- **Debugging**: Easy inspection and testing in browser console

### Drawbacks
- **Testing Complexity**: Global dependencies make unit testing harder
- **Tree-shaking**: Harder to eliminate unused code
- **Tight Coupling**: Components become dependent on global state
- **Namespace Pollution**: Large global API surface

## Modern Alternatives

While the global object remains for compatibility, newer patterns include:
- **Composition API**: `useContext()`, service injection via composables
- **Direct Imports**: Import specific services/factories directly
- **Dependency Injection**: Use the underlying BottleJS container

The global object represents a **pragmatic compromise** between developer experience and modern architecture patterns, prioritizing ecosystem stability and ease of use for the large Shopwell plugin community.
