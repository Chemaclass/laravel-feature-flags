# Scope Resolvers

A **scope** is whatever string id your app keys per-target flag overrides by. It is intentionally undefined by this library — pick any concept that fits your domain:

- a team id
- an organization id
- a user id
- a region code
- an A/B cohort name
- a customer id
- a partner id
- nothing at all (global-only)

The package only cares about strings.

## Default: `NullScopeResolver`

```php
final class NullScopeResolver implements FeatureScopeResolver
{
    public function resolve(Request $request): ?string
    {
        return null;
    }
}
```

Returns `null` for every request → only **global** flag rows are consulted. This is the default and the safest starting point.

## Bundled example: `UserTenantScopeResolver`

A pre-built resolver for apps that key flags by a "tenant"-like concept on the user model.

```php
final class UserTenantScopeResolver implements FeatureScopeResolver
{
    public function resolve(Request $request): ?string
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $tenant = $user->tenant ?? null;
        if (is_object($tenant) && isset($tenant->id)) {
            return (string) $tenant->id;
        }

        return isset($user->tenant_id) ? (string) $user->tenant_id : null;
    }
}
```

Opt-in via config:

```php
'scope' => [
    'column'   => 'scope_id',
    'resolver' => Chemaclass\FeatureFlags\Resolvers\UserTenantScopeResolver::class,
],
```

This class is just *one example* of how to derive a scope id from the request. Most apps will write their own.

## Writing your own resolver

Implement `Chemaclass\FeatureFlags\Contracts\FeatureScopeResolver`:

```php
namespace App\Support;

use Chemaclass\FeatureFlags\Contracts\FeatureScopeResolver;
use Illuminate\Http\Request;

final class TeamScopeResolver implements FeatureScopeResolver
{
    public function resolve(Request $request): ?string
    {
        $user = $request->user();

        return $user?->currentTeam?->id !== null
            ? (string) $user->currentTeam->id
            : null;
    }
}
```

Register in config:

```php
'scope' => [
    'column'   => 'scope_id',
    'resolver' => App\Support\TeamScopeResolver::class,
],
```

The resolver is resolved through the container, so you can constructor-inject dependencies.

## Common resolver patterns

### Header-based (API)

```php
public function resolve(Request $request): ?string
{
    return $request->header('X-Scope-Id');
}
```

### Subdomain-based

```php
public function resolve(Request $request): ?string
{
    $host = $request->getHost();
    $parts = explode('.', $host);

    return count($parts) > 2 ? $parts[0] : null;
}
```

### Composite (header → user attribute → null)

```php
public function resolve(Request $request): ?string
{
    return $request->header('X-Scope-Id')
        ?? $request->user()?->organization_id
        ?? null;
}
```

### Geographic / region

```php
public function resolve(Request $request): ?string
{
    return strtolower((string) $request->header('CF-IPCountry', 'unknown'));
}
```

### A/B cohort

```php
public function resolve(Request $request): ?string
{
    $userId = $request->user()?->id;

    return $userId !== null ? 'cohort-'.(crc32((string) $userId) % 2 === 0 ? 'A' : 'B') : null;
}
```

## When the resolver returns `null`

`isEnabled($key, null)` falls back to **global rows only** (rows where `scope_id IS NULL`). This is intentional — unauthenticated or scope-less requests still resolve flags via the global default.

## Manually passing a scope id

You don't have to use the resolver. Pass any string explicitly:

```php
$manager->isEnabled('async-exports', 'cohort-experiment-A');
```

This is handy for background jobs, queue workers, console commands, or anywhere there is no request.

## Next steps

- [Middleware guard](middleware.md)
- [Extending storage](extending.md)
