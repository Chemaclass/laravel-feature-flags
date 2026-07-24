# HTTP API & JS client

A JSON endpoint so non-PHP frontends and other services can evaluate flags. Reads through the
same manager as everything else, so targeting rules, rollout, environments, prerequisites and
the kill-switch all apply.

## Enable it

Off by default — turning it on exposes flag evaluations over HTTP, so pick middleware that
matches how public the data is:

```php
// config/feature-flags.php
'api' => [
    'enabled' => true,
    'prefix' => 'feature-flags/api',
    'middleware' => ['api'],      // add 'auth:sanctum', 'throttle:60,1', a token guard, …
],
```

```dotenv
FEATURE_FLAGS_API_ENABLED=true
```

## Endpoint

`GET|POST {prefix}/evaluate`

Body (all optional):

```json
{
  "keys": ["new-dashboard", "beta-search"],
  "scope": "team-42",
  "context": { "plan": "pro", "country": "DE" }
}
```

- `keys` — omit to evaluate **every** flag.
- `scope` — omit to resolve the scope from the bound `FeatureScopeResolver` (same as the middleware).
- `context` — attributes for targeting rules.

Response:

```json
{
  "scope": "team-42",
  "flags": { "new-dashboard": true, "beta-search": false },
  "variants": { "new-dashboard": { "name": "blue", "payload": { "cta": "Go" } }, "beta-search": null }
}
```

## JavaScript client

Publish the bundled zero-dependency client:

```bash
php artisan vendor:publish --tag=feature-flags-js
# → resources/js/vendor/feature-flags.js
```

```js
import { createFeatureFlags } from './vendor/feature-flags.js';

const ff = createFeatureFlags({
  endpoint: '/feature-flags/api/evaluate',
  scope: currentUser.id,
  context: { plan: currentUser.plan, country: 'DE' },
  // headers: { Authorization: `Bearer ${token}` }, // if your middleware needs it
});

await ff.load();                    // fetch all flags for the scope+context
if (ff.isEnabled('new-dashboard')) render(<NewDashboard />);
const v = ff.variant('homepage');   // { name, payload } | null
```

`load(['a','b'])` fetches specific keys; `all()` returns the current flag map; `isLoaded()`
reports whether a fetch has completed.

## Notes

- The endpoint accepts `GET` and `POST`; use `POST` when sending a `context` body.
- Every request is a fresh evaluation (request-memoized server-side). Cache on the client if you
  call it often, or bootstrap once on page load.
