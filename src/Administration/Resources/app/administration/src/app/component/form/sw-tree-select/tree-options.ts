/** @sw-package framework */
/** @private */
export type TreeSelectOption = {
    value: string;
    label: string;
    description?: string;
    selectable?: boolean;
    children?: TreeSelectOption[];
};
/** @private */
export type TreeSelectRow = TreeSelectOption & { depth: number };

/** @private */
export function visibleTreeOptions(options: TreeSelectOption[], expanded: string[], term: string): TreeSelectRow[] {
    const needle = term.trim().toLocaleLowerCase();
    const matches = (option: TreeSelectOption): boolean =>
        `${option.label} ${option.description ?? ''}`.toLocaleLowerCase().includes(needle) ||
        !!option.children?.some(matches);
    const rows: TreeSelectRow[] = [];
    const walk = (nodes: TreeSelectOption[], depth: number) =>
        nodes.forEach((option) => {
            if (needle && !matches(option)) return;
            rows.push({ ...option, depth });
            if (needle || expanded.includes(option.value)) walk(option.children ?? [], depth + 1);
        });
    walk(options, 0);
    return rows;
}

/** @private */
export function selectedTreePath(options: TreeSelectOption[], value: string, ancestors: string[] = []): string {
    for (const option of options) {
        const path = [...ancestors, option.label];
        if (option.value === value) return path.join(' / ');
        const nested = selectedTreePath(option.children ?? [], value, path);
        if (nested) return nested;
    }
    return '';
}
