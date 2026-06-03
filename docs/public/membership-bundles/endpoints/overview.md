# REST API Overview

The Membership Bundles REST API is registered under the `wicket_member/v1` namespace on the WordPress REST API.

## [Base URL](#base-url)

```
/wp-json/wicket_member/v1/
```

## [Authentication](#authentication)

All endpoints require the requesting user to have the `wicket_memberships_admin` capability. Unauthenticated requests receive a `401` response. Authenticated requests from users without the capability receive a `403`.

The `ALLOW_LOCAL_IMPORTS` environment flag bypasses the permission check entirely. This is for internal automation only — do not rely on it in production integrations.

## [Request format](#request-format)

`GET` endpoints accept parameters as query string arguments.  
`POST` endpoints accept parameters as a JSON body with `Content-Type: application/json`, or as form-encoded data.

## [Response format](#response-format)

Successful responses return JSON. All mutation endpoints return a `WP_REST_Response` object. The HTTP status code is the primary indicator of success or failure.

| Status | Meaning |
|---|---|
| `200` | Success |
| `204` | Success, no content (used for no-op responses such as changing to the same owner) |
| `400` | Bad request — invalid parameters or a business rule violation |
| `401` | Not authenticated |
| `403` | Authenticated but not authorized |
| `404` | Post not found or wrong CPT |
| `500` | Server error — typically a downstream failure (MDP, WooCommerce) |

Error responses include a JSON body with an `error` key and a human-readable message. Most also include a `code` key with a machine-readable error code.

```json
{
    "error": "Bundle is not in a manageable status.",
    "code": "invalid_bundle_status"
}
```

## [Error codes](#error-codes)

These error codes appear across multiple endpoints:

| Code | Meaning |
|---|---|
| `invalid_bundle_status` | The bundle is not in a status that permits the requested operation |
| `invalid_membership` | The membership post was not found or is the wrong CPT |
| `membership_not_in_bundle` | The membership does not belong to the specified bundle |
| `invalid_user` | The WP user could not be resolved |
| `bundle_ended` | Today is past the bundle's end date |
| `bundle_no_dates` | The bundle has no date meta |
| `missing_user_id` | A user ID is required but was not provided |

## [Endpoint groups](#endpoint-groups)

| Group | Prefix | What it covers |
|---|---|---|
| [Bundles](bundles.md) | `/membership_bundles`, `/bundle` | Create, retrieve, update, filter, and inspect bundles |
| [Bundle Members](bundle-members.md) | `/bundle/{id}/` | Add, remove, and move individual member seats |
| [Bundle Status](bundle-status.md) | `/bundle/admin/`, `/bundle/{id}/cancel` | Status transitions, cancellation, and renewal orders |
| [Bundle Config Dates](bundle-config-dates.md) | `/bundle_config/{id}/` | Calculate membership dates from a config record |
