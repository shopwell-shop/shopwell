/** @sw-package framework */
import template from './sw-workflow-studio-toolbar.html.twig';
import './sw-workflow-studio-toolbar.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    props: {
        canUndo: { type: Boolean, default: false },
        canRedo: { type: Boolean, default: false },
        allowEdit: { type: Boolean, default: true },
        downloadUrl: { type: String, required: true },
    },
    emits: [
        'undo',
        'redo',
        'templates',
    ],
});
