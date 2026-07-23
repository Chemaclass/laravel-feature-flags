# Laravel Pennant bridge

Already using [Laravel Pennant](https://laravel.com/docs/pennant)? The optional bridge lets
`Feature::active()` resolve through this package, so you can adopt it as your persistence +
admin layer without rewriting call sites.

The bridge is **off by default** and has zero impact when Pennant is absent.

## Setup

1. Install Pennant (it's a `suggest`, not a hard dependency):

   ```bash
   composer require laravel/pennant
   ```

2. Enable the bridge and point Pennant at it:

   ```php
   // config/feature-flags.php
   'pennant' => [
       'enabled' => true,
       'driver'  => 'feature-flags',
   ],
   ```

   ```php
   // config/pennant.php
   'default' => 'feature-flags',
   'stores'  => [
       'feature-flags' => ['driver' => 'feature-flags'],
       // ...existing stores
   ],
   ```

3. Use Pennant as usual — values come from this package:

   ```php
   use Laravel\Pennant\Feature;

   Feature::active('new-dashboard');            // → FeatureFlag::isEnabled('new-dashboard')
   Feature::for('team-1')->active('new-dashboard');
   ```

## Scope mapping

Pennant scopes are mapped to this package's string scope ids:

| Pennant scope | Mapped scope id |
|---------------|-----------------|
| `null` | `null` (global) |
| string / scalar | the value as a string |
| `FeatureScopeable` | `toFeatureIdentifier('feature-flags')` |
| Eloquent model | the model's key |

## Limitations

- Values are boolean (this package's model). Pennant's rich values are cast to `bool`.
- `setForAllScopes()` writes the global (null-scope) row, which every scope falls back to.
- Reads are the primary path; writes/deletes go through `FeatureFlagManager`.
