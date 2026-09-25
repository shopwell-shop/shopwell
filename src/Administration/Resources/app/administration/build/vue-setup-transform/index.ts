/**
 * @sw-package framework
 */

/**
 * Converts Shopwell's native setup SFC dialect into plain Vue SFC source before Vue compilation.
 *
 * This module owns the per-file transform boundary: parse the SFC, analyze script and template
 * semantics, lower the Shopwell setup block into source edits, and apply them - while leaving
 * cross-file component-name checks to the build integration. Every edit comes from lowering; nothing
 * generated is decided here.
 */

import { lowerShopwellSetupBlock } from './lower';
import { analyzeShopwellSetupScript, type ShopwellSetupScriptAnalysis } from './script-analyzer';
import { applySourceEdits, type AppliedSourceEdits } from './source-edits/apply-source-edits';
import {
    analyzeBaseTemplate,
    analyzeOverrideTemplate,
    emptyTemplateAnalysis,
    type TemplateAnalysis,
} from './template-analyzer';
import { parseShopwellSetupSfc } from './sfc-parser';
import type { ShopwellSetupBlock } from './utils/shopwell-setup-block';
import { ShopwellSetupTransformError } from './utils/transform-error';

type ShopwellSetupTransformResult = {
    code: string;
    map: AppliedSourceEdits['map'];
    mode: 'base' | 'override';
    componentName: string;
    filename: string;
    // Static names of the base `<sw-block name="...">` blocks this component owns (empty for overrides).
    // Emitted for a later branch to build a cross-file block-ownership registry.
    ownedBlockNames: string[];
    // Static names of the blocks this override `<sw-block extends="...">` extends (empty for base).
    // The registry's other half, for a later branch to cross-check against the emitted ownership.
    extendedBlockNames: string[];
};

/**
 * Moves block-relative analyzer errors to the start of the original script body.
 */
function withBlockOffset(error: unknown, block: ShopwellSetupBlock): unknown {
    if (!(error instanceof ShopwellSetupTransformError) || error.index !== null) {
        return error;
    }

    return new ShopwellSetupTransformError(error.message, block.contentStart);
}

/**
 * Converts a Shopwell setup SFC into plain Vue-compatible code before Vue compiles it.
 */
function transformShopwellSetupSfc(source: string, filename = 'anonymous.vue'): ShopwellSetupTransformResult | null {
    const block = parseShopwellSetupSfc(source, filename);

    if (!block) {
        return null;
    }

    let analysis: ShopwellSetupScriptAnalysis;
    let edits: ReturnType<typeof lowerShopwellSetupBlock>;
    let templateAnalysis: TemplateAnalysis = emptyTemplateAnalysis();

    try {
        analysis = analyzeShopwellSetupScript(block.content, {
            mode: block.mode,
            lang: block.lang,
            scriptOffset: block.contentStart,
        });
        templateAnalysis = analysis.mode === 'base' ? analyzeBaseTemplate(block) : analyzeOverrideTemplate(block, analysis);

        edits = lowerShopwellSetupBlock(block, analysis, templateAnalysis);
    } catch (error) {
        throw withBlockOffset(error, block);
    }

    const transformed = applySourceEdits(source, filename, edits);

    return {
        code: transformed.code,
        map: transformed.map,
        mode: block.mode,
        // Exposed so the build integration can maintain a per-compilation registry and reject two
        // SFCs that resolve to the same extendable component name. Cross-file enforcement lives with
        // the loader/compilation layer; this transform stays a pure per-file step.
        componentName: block.componentName,
        filename,
        ownedBlockNames: templateAnalysis.ownedBlockNames,
        extendedBlockNames: templateAnalysis.extendedBlockNames,
    };
}

/**
 * Runs the shared transform for callers that only need diagnostics.
 */
function validateShopwellSetupSfc(source: string, filename = 'anonymous.vue'): void {
    transformShopwellSetupSfc(source, filename);
}

/**
 * @private
 */
export {
    type ShopwellSetupTransformResult,
    ShopwellSetupTransformError,
    transformShopwellSetupSfc,
    validateShopwellSetupSfc,
};
