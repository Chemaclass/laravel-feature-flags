/**
 * Tiny browser client for chemaclass/laravel-feature-flags.
 *
 * Talks to the package's JSON API (POST {prefix}/evaluate). Framework-agnostic,
 * zero dependencies, works with any bundler or a plain <script type="module">.
 *
 * @example
 *   import { createFeatureFlags } from './vendor/feature-flags.js';
 *
 *   const ff = createFeatureFlags({
 *     endpoint: '/feature-flags/api/evaluate',
 *     scope: currentUser.id,
 *     context: { plan: currentUser.plan, country: 'DE' },
 *   });
 *
 *   await ff.load();                 // fetch all flags for the scope+context
 *   ff.isEnabled('new-dashboard');   // => true | false
 *   ff.variant('homepage');          // => { name, payload } | null
 */
export function createFeatureFlags(options = {}) {
  const {
    endpoint = '/feature-flags/api/evaluate',
    scope = null,
    context = {},
    headers = {},
    fetchImpl = (typeof fetch !== 'undefined' ? fetch : null),
  } = options;

  if (!fetchImpl) {
    throw new Error('createFeatureFlags: no fetch implementation available; pass options.fetchImpl.');
  }

  let flags = {};
  let variants = {};
  let loaded = false;

  /**
   * Evaluate flags for the configured scope + context.
   * @param {string[]|null} keys  Specific keys, or null for every flag.
   * @returns {Promise<object>} the raw API response.
   */
  async function load(keys = null) {
    const body = { scope, context };
    if (Array.isArray(keys)) body.keys = keys;

    const res = await fetchImpl(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...headers },
      body: JSON.stringify(body),
    });

    if (!res.ok) {
      throw new Error(`feature-flags: request failed with ${res.status}`);
    }

    const data = await res.json();
    flags = data.flags || {};
    variants = data.variants || {};
    loaded = true;
    return data;
  }

  return {
    load,
    isEnabled: (key) => flags[key] === true,
    variant: (key) => variants[key] ?? null,
    all: () => ({ ...flags }),
    isLoaded: () => loaded,
  };
}

export default createFeatureFlags;
