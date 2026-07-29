---
title: "Setting — WooCommerce API Keys with Membership API Access"
audience: [implementer, support]
wp_admin_path: "Settings → Wicket Memberships"
php_class: Api_Key_Access
db_option_prefix: wicket_membership_plugin_options
---

# WooCommerce API Keys with Membership API Access

Controls which WooCommerce REST API keys are allowed to read and write membership records through the WordPress REST API.

## What It Does

The plugin exposes its three membership record types over the WordPress REST API:

- `/wp/v2/wicket_membership` — membership records
- `/wp/v2/wicket_mship_config` — membership configurations
- `/wp/v2/wicket_mship_tier` — membership tiers

These endpoints are restricted to administrators. Out of the box a server-to-server integration can only reach them with a WordPress **Application Password**, because WooCommerce refuses to authenticate its own API keys on anything outside its own endpoints.

This setting lifts that restriction for **specific keys you choose**. A selected key can then authenticate against the three endpoints above exactly as it would against a WooCommerce endpoint.

## When to Use It

Use it when an external system already integrates with this site over the WooCommerce REST API and now also needs membership data — an ERP, a reporting tool, a CRM sync.

Prefer an Application Password when the integration has nothing to do with WooCommerce. It is a narrower grant and does not involve this setting at all.

## Default

**Nothing selected.** With no keys selected the setting has no effect anywhere on the site — WooCommerce API keys behave exactly as they did before, and the membership endpoints remain reachable only by Application Password or a signed-in administrator.

## Requirements for a Key to Work

All of the following must be true. If any one fails the request is rejected.

| Requirement | If it fails |
|---|---|
| The key is selected in this setting | `401` |
| The key's owner is a WordPress administrator | `403` |
| The request uses HTTPS | Rejected — see *HTTPS is required* below |
| The key's permission covers the action (`Read` for GET, `Read/Write` for POST) | `401` from WooCommerce |

## Warnings and Gotchas

**Selecting a key widens it, it does not narrow it.** A selected key keeps all of its normal WooCommerce API access and gains membership access on top. It does not become a memberships-only key.

**Membership records contain personal data** — person identifiers, linked user accounts, statuses and organization links. Select only keys belonging to integrations that should see it.

**The key's owner must be an administrator.** A key created on a non-administrator account will be rejected with a `403` even after you select it. The owner is not shown in this list, so if a key stops working this is the first thing to check — find it under **WooCommerce → Settings → Advanced → REST API** and look at its user.

**Read-only keys cannot write.** A key with `Read` permission can list and view membership records but cannot create or change them. The list here does not show which is which; check the key's Permissions column in WooCommerce.

**Rotating a key means re-selecting it.** WooCommerce has no way to rotate a key in place — you revoke it and create a new one. The new key is a different key, so it will not be selected here, and the integration will start returning `401` until you select it. Revoked keys disappear from this list automatically.

**HTTPS is required.** These keys only work over HTTPS. On a plain HTTP request the credentials are refused. If a key works locally but not on another environment, confirm that environment is serving HTTPS and reporting it correctly to WordPress.

## How to Grant Access to a Key

1. Create the key in WooCommerce if it does not exist: **WooCommerce → Settings → Advanced → REST API → Add key**. Set the user to an administrator and choose the permission the integration needs.
2. Go to **Settings → Wicket Memberships**.
3. Find **WooCommerce API Keys with Membership API Access**.
4. Select the key. Keys are listed by name and the last characters of the consumer key, matching how WooCommerce lists them.
5. Save.

To revoke access, deselect the key and save. Access stops immediately; the key keeps its normal WooCommerce access.

| | |
|---|---|
| Option key | `wicket_membership_plugin_options['wicket_mship_api_allowed_keys']` |
| PHP access | `Wicket_Memberships\Api_Key_Access::get_allowed_key_ids()` |
| Stored as | Array of WooCommerce `key_id` integers |
| Default | _(empty — no keys selected)_ |

## Related

- [Grant an Integration Access to Membership Data](../guides/grant-api-access-to-memberships.md) — step-by-step guide
- [Membership CPT REST Access](../engineering/membership-cpt-rest-access.md) — technical reference
