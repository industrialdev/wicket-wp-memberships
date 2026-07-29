---
title: "Grant an Integration Access to Membership Data"
audience: end-user
---

# Grant an Integration Access to Membership Data

Another system — a finance package, a reporting tool, a CRM — can read and update membership records on this site automatically. If it already connects to your store using a WooCommerce API key, you can let that same key reach membership data by ticking it on the memberships settings page.

Nothing is shared until you tick a key. No key has membership access by default.

## Before You Start

- You need to be an administrator.
- The integration must already have a WooCommerce API key on this site, or you need to create one.
- Your site must be served over a secure (HTTPS) connection. Membership data is never sent over an insecure one.

## Give a Key Access

1. Go to **Settings → Wicket Memberships**.
2. Find **WooCommerce API Keys with Membership API Access**.
3. Click the key you want to give access to. Each key is listed by its name and the last few characters of its key, the same way it appears in WooCommerce.
4. To select more than one key, hold **Ctrl** (**Cmd** on a Mac) while clicking.
5. Click **Save Changes**.

Access starts straight away.

## Take Access Away

Deselect the key on the same screen and save. The integration loses access to membership data immediately. It keeps whatever access it had to the rest of your store.

## Create a Key First, If You Need One

1. Go to **WooCommerce → Settings → Advanced → REST API**.
2. Click **Add key**.
3. Give it a description you will recognise later — the name shown on the memberships settings page is this description.
4. Set **User** to an administrator account. This matters: see *Why a key might not work* below.
5. Set **Permissions**:
   - **Read** if the integration only needs to look at membership data.
   - **Read/Write** if it also needs to create or change memberships.
6. Click **Generate API key** and give the consumer key and secret to whoever is setting up the integration. **You only see them once.**
7. Now follow *Give a Key Access* above.

## Why a Key Might Not Work

If the integration reports an authentication or permission error, work through these in order.

**The key is not ticked.** The most common cause. Open **Settings → Wicket Memberships** and confirm it is still selected.

**The key belongs to a non-administrator.** A key only works if the user account it was created under is an administrator. The settings list does not show you the owner, so check it under **WooCommerce → Settings → Advanced → REST API** — the **User** column. If it is not an administrator, create a replacement key under one and tick that instead.

**The key was recreated.** WooCommerce cannot change a key's password without replacing the whole key. If someone revoked a key and made a new one — even with the same name — it is a new key and will not be ticked. Tick the new one.

**The integration only has read access.** A key set to **Read** can look at membership data but cannot change it. If the integration is failing only when it tries to save something, check the key's **Permissions** in WooCommerce and create a **Read/Write** key if needed.

**The connection is not secure.** Membership data is only served over HTTPS. If the integration is configured with an `http://` address, change it to `https://`.

## What Ticking a Key Actually Does

**It adds access, it does not restrict.** A ticked key keeps everything it could already do in your store and gains membership access as well. Ticking it does not turn it into a memberships-only key.

**It covers membership records, configurations and tiers** — the same information you can see under the Memberships menu.

**It does not affect people signing in.** Staff logging into the site are unaffected by this setting, and so is any integration using a different kind of connection.

## A Note on Care

Membership records include personal information about your members and the organizations they belong to. Only tick keys belonging to systems that genuinely need it, and remove access when an integration is retired.

If you are not sure whether an integration needs membership data, ask whoever maintains it before ticking its key.
