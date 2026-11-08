export default class Pagination extends ShopwellComponent {

    static options = {
        useHref: false,
    };

    init() {
        this.items = this.el.querySelectorAll('a[data-page]');

        this.items.forEach(item => {
            item.addEventListener('click', this.handleClick.bind(this, item));
        });
    }

    handleClick(item, event) {
        if (!this.options.useHref) {
            event.preventDefault();
        }

        let page = parseInt(item.getAttribute('data-page') ?? 1, 10);

        ({ page } = Shopwell.emitInterception('Pagination:PreChange', { page }));

        Shopwell.emit('Pagination:Change', page);

        this.setActiveItem(page);
    }

    setActiveItem(page) {
        this.items.forEach(item => {
            item.parentElement.classList.remove('active');
        });

        this.el.querySelector(`.page-${page}`)?.classList.add('active');
    }

    destroy() {
        this.items.forEach(item => {
            item.removeEventListener('click', this.handleClick.bind(this, item));
        });
    }
}
