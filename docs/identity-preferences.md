# Identity profile and preferences (SDK 2.1)

The SDK preserves the standard UserInfo claims in `Identity\UserProfile` and emits `Events\UserProfileSynchronized` before logging in the local user. The event contains the local model, typed profile and configured issuer. Its profile payload excludes OAuth credentials; the backend user model keeps its normal hidden token attributes. A standard `sub` works without the legacy `id`; inconsistent subjects are rejected before writing the local model.

```php
// config/usersau.php — enable only after the identity provider supports these scopes.
'scopes' => ['openid', 'profile', 'email', 'preferences:read', 'preferences:write'],
'profile_photo_column' => 'profile_photo_path',
```

Listen to `UserProfileSynchronized` in the consuming app to persist authorized claims. The DTO supports all fourteen profile fields plus independently scoped email, phone and address fields. Missing optional values remain absent. Use `profile->subject()`, `profile->toArray()` and `profile->preferences()`. Do not use names/emails as account identifiers. The deployment has one configured issuer; changing it requires an explicit identity migration, not automatic reuse of matching subject IDs.

```php
$client = app(\Usersau\UsersauLaravelClient\Identity\IdentityClient::class);
$current = $client->fetch($user);
$saved = $client->update($user, ['theme' => 'dark'], $current['version']);
```

The local user model must provide usersau_id, usersau_access_token and usersau_refresh_token. Host and refresh credentials come only from services.usersau.host/client_id/client_secret. Keep credentials hidden from model serialization. SDK 2 requires PHP ^8.5.12 and Laravel/Illuminate ^12.70.0, matching Auth and Kshop. Older framework versions are outside its supported runtime.

The provider exposes GET/PATCH `/api/interface-preferences`; PATCH accepts only locale, timezone, theme and expected_version. Locale uses language tags, timezone uses IANA identifiers, theme is light/dark/system. Read needs preferences:read, write needs preferences:write. These scopes do not grant profile/email/verification edits. `urn:usersau:preferences` contains the same snapshot in UserInfo when authorized.

`Identity\PreferencesException` exposes status and an optional current preferences snapshot:

- 409: stale version; do not silently report the requested update as saved.
- 401/403: user must sign in/authorize again. Refreshing an old token cannot add scopes.
- 502/503: invalid subject/response or unavailable authority. Retain the last confirmed state.

Requests use a bearer header, fixed configured issuer, five-second total/two-second connection timeouts, and do not follow redirects. A 401 can refresh once; concurrent refreshes serialize on the local user row. Refresh never requests expanded scopes. Responses must match usersau_id. Exceptions do not include tokens or remote response bodies.

Persist the full successful snapshot and version. Use field-level updates, serialize writes in the browser, and ignore responses from previous accounts. Auth is authoritative; local database copies are caches, not a distributed transaction. Fetch on login/application entry/focus to converge across devices.

Shared-saas 5.11 supplies the central/admin storage and browser integration. Its guide describes middleware order, session/CSRF proxy endpoints, rollout and migrations: [identity-profile-preferences](https://github.com/sicaboy/shared-saas/blob/v5.11.0/docs/identity-profile-preferences.md).

SDK 2 requests profile/email and preference read/write scopes by default. Upgrade Auth and deploy its migration before upgrading the coordinated central/admin consumers. Existing tokens need a new authorization grant; refresh cannot add permissions. Applications can explicitly configure scopes for staged operations. Normal OAuth login obtains the configured preference scopes.

SDK 2.1 requires Socialite provider 2. The provider maps standard sub/picture, retains full scoped raw claims and rejects inconsistent identities. Install both source releases before updating consumers.
