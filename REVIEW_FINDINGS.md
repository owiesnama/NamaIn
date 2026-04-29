# Review Findings

## Finding 1: Bind Admin Routes To The Main Domain

- **Priority:** P1
- **File:** `routes/web.php`
- **Lines:** 62-89
- **Status:** Added

The admin route group has no domain constraint, so these `__admin` routes can also match on tenant subdomains. That violates the main-domain-only scope and can make impersonation redirects land on `tenant.namain.test/__admin`.

**Recommendation:** Wrap the admin group, including the stop route if kept here, in `Route::domain(config('app.domain'))` or otherwise force URL generation to the main host.

## Finding 2: Restore Null Tenant After Impersonation

- **Priority:** P1
- **File:** `app/Actions/Admin/StopImpersonationAction.php`
- **Lines:** 23-29
- **Status:** Added

When the impersonated user originally had `current_tenant_id = null`, this condition skips restoration because `$previousTenantId !== null` is false. Stopping impersonation then leaves that real user permanently pointed at the impersonated tenant.

**Recommendation:** Track whether the session key exists and update to null as well.

## Finding 3: Group Search Conditions Before Status Filter

- **Priority:** P2
- **File:** `app/Http/Controllers/Admin/TenantsController.php`
- **Lines:** 25-29
- **Status:** Added

The search callback adds `where name ... orWhere slug ...` directly to the root query. When `status` is also present, SQL precedence allows name matches to bypass the status constraint.

**Recommendation:** Wrap the search in a nested `where(fn ($query) => ...)` so status applies to both name and slug matches.

## Finding 4: Tenant-List Impersonation Route Is Missing User

- **Priority:** P2
- **File:** `resources/js/Pages/Admin/Tenants/Index.vue`
- **Lines:** 84-87
- **Status:** Added

`admin.impersonate.start` is registered as `tenants/{tenant}/users/{user}/impersonate`, but this call passes only the tenant ID. Clicking the list-page impersonate button will fail URL generation or post to an invalid route.

**Recommendation:** Either remove this button from the index, include the owner in the tenant payload, or route users through the tenant detail page.

## Finding 5: Registered Route Points To Missing Controller Method

- **Priority:** P2
- **File:** `routes/web.php`
- **Line:** 77
- **Status:** Added

This route targets `TenantUsersController@index`, but that controller currently only defines `store` and `destroy`. A direct visit to `admin.tenants.users.index` will hit a missing method at runtime.

**Recommendation:** Add the method, remove the route, or point it at the existing tenant show flow.

## Finding 6: Invitations Index Renders A Missing Page

- **Priority:** P3
- **File:** `app/Http/Controllers/Admin/TenantInvitationsController.php`
- **Line:** 34
- **Status:** Added

The controller returns `Admin/Tenants/Invitations`, but only `Index.vue` and `Show.vue` exist under `resources/js/Pages/Admin/Tenants`. Visiting this route will fail Inertia component resolution unless the page is added or the route is removed.

**Recommendation:** Add the missing page or remove the route/controller action until the UI exists.
