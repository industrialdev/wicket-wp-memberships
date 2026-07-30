---
title: "Connect an Integration to Membership Data"
audience: end-user
---

# Connect an Integration to Membership Data

Another system — a finance package, a reporting tool, a CRM — can read and update membership records on this site automatically. There are two ways to let it in, and you choose based on what the integration already does.

Only administrators can grant either kind of access, and an integration only ever sees membership data if you deliberately give it a credential.

## Which Way Should I Use?

| | Application Password | WooCommerce API Key |
|---|---|---|
| Best for | A tool that only needs membership data | A tool that already connects to your store |
| You create it under | A user's profile | WooCommerce settings |
| Extra step on the memberships settings page | No | **Yes** — you must tick the key |
| What it can reach | Everything that user can reach | Your store, plus membership data once ticked |
| Turn it off from | The user's profile | Either place |

**If you are not sure, use an Application Password.** It is the simpler of the two and does not touch your store settings.

Both require your site to be served securely (over `https://`). Neither works over an insecure connection.

---

## Option 1 — Application Password

This is built into WordPress. Nothing on the memberships settings page affects it.

An Application Password belongs to **a user account**, and it can do anything that user can do. So the account you create it under matters.

### Create One

1. Go to **Users** and open the profile of the account the integration should act as. It must be an administrator.
   - It is good practice to create a dedicated administrator account for each integration, rather than using a person's own login. If someone leaves and their account is removed, an integration tied to it stops working.
2. Scroll to **Application Passwords**.
3. Type a name you will recognise later, such as the name of the integration.
4. Click **Add New Application Password**.
5. Copy the password that appears and give it to whoever is configuring the integration, along with the account's username. **It is shown only once.**

The integration is now connected. There is no further step.

### Turn It Off

Go back to that user's profile, find the password in the **Application Passwords** list, and click **Revoke**. Access stops immediately.

### Things to Know

- The credential can reach everything that user account can reach, not just membership data. Keep the account's role to administrator and nothing broader.
- If you delete the user account, every Application Password on it stops working.
- Each integration should have its own, so you can turn one off without affecting the others.

---

## Option 2 — WooCommerce API Key

Use this when the integration already talks to your store using a WooCommerce key. Rather than issuing it a second credential, you let the key it already has reach membership data too.

This takes **two steps**: the key must exist in WooCommerce, and you must tick it on the memberships settings page. A key that is not ticked has no membership access at all.

### Create the Key, If It Does Not Exist

1. Go to **WooCommerce → Settings → Advanced → REST API**.
2. Click **Add key**.
3. Give it a description you will recognise — this is the name you will see on the memberships settings page.
4. Set **User** to an administrator account. This matters: see *Why is a key not working?* below.
5. Set **Permissions**:
   - **Read** if the integration only needs to look at membership data.
   - **Read/Write** if it also needs to create or change memberships.
6. Click **Generate API key** and pass the consumer key and secret to whoever is configuring the integration. **They are shown only once.**

### Tick the Key

1. Go to **Settings → Wicket Memberships**.
2. Find **WooCommerce API Keys with Membership API Access**.
3. Click the key you want to grant access to. Keys are listed by their name and the last characters of the key, the same way WooCommerce lists them.
4. To grant more than one, hold **Ctrl** (**Cmd** on a Mac) while clicking.
5. Click **Save Changes**.

Access starts straight away.

### Turn It Off

Deselect the key on that screen and save. It loses membership access immediately, and keeps whatever access it had to the rest of your store.

To remove it entirely, revoke the key in WooCommerce instead. It then disappears from the memberships list on its own.

### Things to Know

- **Ticking a key adds access, it does not restrict.** The key keeps everything it could already do in your store. It does not become a memberships-only key.
- **The list shows only the key's name and its last characters.** If two keys share a name, the last characters are how you tell them apart.
- **Recreating a key means ticking it again.** WooCommerce cannot change a key's secret without replacing the whole key, so a "rotated" key is a new key and starts out unticked.

---

## Why Is a Key Not Working?

Work through these in order. The symptom is usually an authentication or permission error reported by the integration.

**Is it an Application Password or a WooCommerce key?** The checks differ, so establish this first.

### For Either Kind

**The account it belongs to is not an administrator.** Membership data is administrator-only. For an Application Password, check the role on that user's profile. For a WooCommerce key, check the **User** column under **WooCommerce → Settings → Advanced → REST API** — the list on the memberships settings page does not show you the owner.

**The connection is not secure.** Both kinds only work over `https://`. If the integration is configured with an `http://` address, change it.

**The credential was revoked.** Check it still exists where it was created.

### For a WooCommerce Key Only

**It is not ticked.** The most common cause. Open **Settings → Wicket Memberships** and confirm it is still selected.

**It was recreated after being ticked.** A replacement key is a different key and will not be ticked. Tick the new one.

**It only has read access.** A key set to **Read** can look at membership data but cannot change it. If the integration fails only when saving, check **Permissions** in WooCommerce and create a **Read/Write** key if it needs one.

---

## What Access Covers

Either credential reaches membership records, membership configurations and membership tiers — the same information you can see under the **Memberships** menu.

Neither affects staff signing in to the site normally.

## A Note on Care

Membership records include personal information about your members and the organizations they belong to. Grant access only to systems that genuinely need it, use a separate credential per integration so you can withdraw one at a time, and remove access when an integration is retired.

If you are not sure whether an integration needs membership data, ask whoever maintains it before granting anything.
