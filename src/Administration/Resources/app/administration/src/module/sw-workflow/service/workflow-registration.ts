/** @sw-package framework */
/** Registers one owned contribution; stale disposers cannot remove a later registration. @private */
export function registerWorkflowContribution<T>(registry: Map<string, T>, name: string, contribution: T): () => void {
    if (!name.includes(':')) {
        throw new Error(`Workflow registrations require a namespace: ${name}`);
    }

    if (registry.has(name)) {
        throw new Error(`Duplicate workflow registration: ${name}`);
    }

    registry.set(name, contribution);
    let active = true;

    return () => {
        if (!active) {
            return;
        }

        active = false;

        if (registry.get(name) === contribution) {
            registry.delete(name);
        }
    };
}
