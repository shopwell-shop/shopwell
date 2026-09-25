/**
 * @sw-package framework
 */
import type { HttpClient } from 'src/core/factory/http-client.types';

const HttpClient = Shopwell.Classes._private.HttpFactory;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeHttpClient(): HttpClient {
    return HttpClient(Shopwell.Context.api);
}
