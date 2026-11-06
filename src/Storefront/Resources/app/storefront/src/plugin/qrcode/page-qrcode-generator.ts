import Plugin from 'src/plugin-system/plugin.class';
import QRCode from 'qrcode';

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */

export default class PageQrcodeGeneratorPlugin extends Plugin {
    private el: HTMLElement;

    private options: {
        content: string | null;
        errorCorrectionLevel: string;
        width: number;
        params?: Record<string, string>;
    };

    public static options = {
        content: null,
        errorCorrectionLevel: 'H',
        width: 300,
    };

    /**
     * initialize plugin
     */
    public init() {
        const qrLink = new URL(window.location.href);
        if (this.options.params) {
            for (const key in this.options.params) {
                qrLink.searchParams.set(key, this.options.params[key]);
            }
        }
        // eslint-disable-next-line
        QRCode.toCanvas(
            this.options.content ?? qrLink.toString(),
            this.options,
            (err: Error | null, canvas: HTMLCanvasElement) => {
                if (err) return;
                if (this.options.params?.autostartAr) {
                    canvas.dataset.arModelId = this.options.params.autostartAr;
                }
                this.el.appendChild(canvas);
            },
        );
    }
}
