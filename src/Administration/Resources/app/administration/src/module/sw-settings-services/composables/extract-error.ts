/**
 * @sw-package framework
 */
import type { AxiosError } from 'axios';

/**
 * @private
 */
export default function (exception: unknown): string {
    let message: string = 'unknown error';

    if (exception instanceof Error) {
        message = exception.message;
    }

    if (isShopwellHttpErrorResponse(exception)) {
        message = exception.response?.data.errors[0]?.detail ?? 'unknown error';
    }

    return message;
}

function isAxiosError(exception: unknown): exception is AxiosError<unknown> {
    return exception instanceof Error && exception.name === 'AxiosError';
}

function isShopwellHttpErrorResponse(exception: unknown): exception is AxiosError<{ errors: ShopwellHttpError[] }> {
    return isAxiosError(exception) && typeof exception.response !== 'undefined';
}
