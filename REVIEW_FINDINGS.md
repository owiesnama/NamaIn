# Review Findings

## Finding 1: Restore The Admin Guard When Stopping Impersonation

- **Priority:** P1
- **File:** `app/Actions/Admin/StopImpersonationAction.php`
- **Line:** 53
- **Status:** Added

`StartImpersonationAction` logs the admin guard out before logging in as the tenant user, but `StopImpersonationAction` only logs the admin back into the `web` guard. The stop controller then redirects to `admin.dashboard`, which is protected by `auth:admin`, so the admin can be sent to the admin login page after stopping impersonation.

**Recommendation:** Log the restored admin into the `admin` guard as well, or avoid logging that guard out in the first place. Add a test that follows the stop redirect and asserts the admin guard is authenticated.

## Finding 2: Invoice Update Authorization Points To Permissions That Do Not Exist

- **Priority:** P1
- **File:** `app/Policies/InvoicePolicy.php`
- **Lines:** 27-31
- **Status:** Added

`update()` now checks `sales.update` and `purchases.update`, but the permission seeder only defines view/create/return/delete permissions for sales and purchases. Because purchase receiving and sale delivery now authorize through this policy, non-owner roles can be locked out of workflows they previously could perform.

**Recommendation:** Either seed and backfill these update permissions and assign them to the right roles, or map this policy to existing domain permissions such as create, receive, or delivery permissions.

## Finding 3: Scope Invoice Payload IDs To The Current Tenant Before Creating Transactions

- **Priority:** P2
- **File:** `app/Http/Requests/CreateInvoiceRequest.php`
- **Lines:** 30-37
- **Status:** Added

Invoice creation still accepts raw invocable/product/unit/storage/account IDs without tenant-scoped `exists` validation, then writes those IDs directly into transactions. With the stricter `TenantScope`, a cross-tenant invocable becomes `null` and can cause a 500, while product/storage/unit IDs can still be persisted because the transactions migration does not enforce foreign keys.

**Recommendation:** Add tenant-scoped validation for every submitted ID, require a non-empty products array, and reject mismatched product/unit/storage combinations before creating the invoice.
