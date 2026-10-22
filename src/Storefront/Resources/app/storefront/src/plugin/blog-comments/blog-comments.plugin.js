import Plugin from 'src/plugin-system/plugin.class';

/**
 * Targeting of a reply.
 *
 * Answering a comment is a piece of state that has to survive without javascript, so it is markup
 * first: the form carries an empty hidden `parentId`, and this script fills it. A visitor who presses
 * "reply", then submits, answers the right comment whether or not the script loaded - the browser
 * always sends the hidden field.
 *
 * The script adds three conveniences on top of that: it says who is being answered, it moves the focus
 * to the textarea, and it lets the visitor take the answer back without reloading the page.
 *
 * The form itself is submitted natively. There is no fetch here on purpose: an ajax submit would have
 * to duplicate the server side error handling to render a flash message, and the redirect that
 * follows a plain submit already lands the visitor back in the thread with the message rendered by the
 * server.
 *
 * @sw-package discovery
 */
export default class BlogCommentsPlugin extends Plugin {
    static options = {
        commentSelector: '[data-blog-comment]',
        replySelector: '[data-blog-comment-reply]',
        cancelSelector: '[data-blog-comment-cancel-reply]',
        formSelector: '[data-blog-comment-form]',
        parentFieldSelector: '[data-blog-comment-parent]',
        replyingSelector: '[data-blog-comment-replying]',
        replyingLabelSelector: '[data-blog-comment-replying-label]',
        contentSelector: 'textarea[name="content"]',
        levelLabelAttribute: 'data-comment-level',
    };

    init() {
        this._form = this.el.querySelector(this.options.formSelector);
        this._parentField = this.el.querySelector(this.options.parentFieldSelector);
        this._replyingBox = this.el.querySelector(this.options.replyingSelector);
        this._replyingLabel = this.el.querySelector(this.options.replyingLabelSelector);

        if (!this._form || !this._parentField) {
            return;
        }

        this._template = this._form.getAttribute('data-replying-template')
            || (this._replyingLabel ? this._replyingLabel.textContent.trim() : '');

        this._registerReplies();
        this._registerCancel();
    }

    _registerReplies() {
        this.el.addEventListener('click', (event) => {
            const button = event.target.closest(this.options.replySelector);

            if (!button || !this.el.contains(button)) {
                return;
            }

            event.preventDefault();

            const id = button.getAttribute('data-comment-id');
            const author = button.getAttribute('data-comment-author') || '';

            if (!id) {
                return;
            }

            this._parentField.value = id;

            if (this._replyingBox) {
                this._replyingBox.hidden = false;
            }

            if (this._replyingLabel) {
                this._replyingLabel.textContent = this._template.replace('%name%', author);
            }

            const content = this._form.querySelector(this.options.contentSelector);

            if (content) {
                content.focus({ preventScroll: true });
            }

            this._form.scrollIntoView({
                behavior: this._reducedMotion() ? 'auto' : 'smooth',
                block: 'center',
            });
        });
    }

    _registerCancel() {
        const cancel = this._form.querySelector(this.options.cancelSelector);

        if (!cancel) {
            return;
        }

        cancel.addEventListener('click', (event) => {
            event.preventDefault();

            this._parentField.value = '';

            if (this._replyingBox) {
                this._replyingBox.hidden = true;
            }
        });
    }

    _reducedMotion() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }
}
