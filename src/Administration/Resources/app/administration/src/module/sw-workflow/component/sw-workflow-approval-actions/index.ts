/** @sw-package framework */
import template from './sw-workflow-approval-actions.html.twig';
import './sw-workflow-approval-actions.scss';

/**
 * 同意 / 拒绝。
 *
 * ## 为什么拒绝必须填意见
 *
 * 后端 `FlowService::reject()` 会拒掉空意见（"为什么被拒"是这条单子
 * 唯一的解释来源，缺了它下一手只能去问发起人）。前端在这里先把按钮禁掉，
 * 让用户当场知道规则，而不是点了之后吃一个 400。
 *
 * 同意则允许空意见：绝大多数通过不需要额外说明，强制填写只会让人敲"同意"两个字。
 */
/** @private */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-workflow-approval-actions',

    template,

    emits: ['approve', 'reject'],

    props: {
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },

        /** 会签里"我是第几票"，用于提示当前进度 */
        progress: {
            type: String,
            required: false,
            default: '',
        },
    },

    data() {
        return {
            comment: '',
        };
    },

    computed: {
        canReject() {
            return !this.isLoading && this.comment.trim() !== '';
        },

        canApprove() {
            return !this.isLoading;
        },
    },

    methods: {
        onApprove() {
            if (!this.canApprove) {
                return;
            }

            this.$emit('approve', this.comment.trim());
        },

        onReject() {
            if (!this.canReject) {
                return;
            }

            this.$emit('reject', this.comment.trim());
        },
    },
});
