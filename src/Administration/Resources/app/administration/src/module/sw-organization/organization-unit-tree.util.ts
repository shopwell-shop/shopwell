/**
 * @sw-package framework
 */
type Unit = EntitySchema.Entity<'organization_unit'>;

/** @private */
export function getUnitPath(units: Unit[], id: string | null): string[] {
    const path: string[] = [];
    const visited = new Set<string>();
    let unit = units.find((item) => item.id === id);
    while (unit && !visited.has(unit.id)) {
        visited.add(unit.id);
        path.unshift(unit.name ?? unit.translated?.name ?? '');
        unit = units.find((item) => item.id === unit?.parentId);
    }
    return path;
}

/** @private */
export function getDescendantIds(units: Unit[], id: string | null): Set<string> {
    const descendants = new Set<string>();
    const stack = id ? [id] : [];
    while (stack.length) {
        const current = stack.pop();
        if (!current || descendants.has(current)) {
            continue;
        }
        descendants.add(current);
        units.filter((unit) => unit.parentId === current).forEach((unit) => stack.push(unit.id));
    }
    return descendants;
}
