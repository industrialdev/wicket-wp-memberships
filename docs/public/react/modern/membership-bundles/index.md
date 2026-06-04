---
title: Membership Bundles UI
---

# Membership Bundles UI

The membership bundles feature provides the admin UI for viewing and managing a single membership bundle record. It is mounted on the WordPress admin page for `wicket_mship_bundle` posts and is gated by the `WICKET_MSHIP_ENABLE_BUNDLES` environment flag.

## Entry Point

`pages/edit.js` reads three `data-*` attributes from the `#bundle_member_edit` DOM node and renders `MembershipBundlePage` as the React root.

```html
<div
  id="bundle_member_edit"
  data-bundle-group-uuid="..."
  data-list-url="..."
  data-individual-members-url="..."
></div>
```

## Component Tree

```
MembershipBundlePage
  AdminPageErrorBoundary
    MembershipBundlePageContent
      AdminNoticeStack
      RenewalProcessingOverlay
      MembershipBundleForm
        IntroBlockSection
        MembershipRecordsSection
          MembershipBundleRecordDetails  (per expanded row)
            MembershipBillingInfoSection
            MembershipOrderDetailsSection
            MembershipStatusSection
            MembershipActionsSection
            CancelMembershipBundleModal
            AddMemberToBundleModal
            CreateBundleRenewalOrderModal
            MembershipDetailsForm
              BundleMembersSection
```

## Components

| Component | Description |
|---|---|
| [MembershipBundlePage](./membership-bundle-page.md) | Top-level container; owns the error boundary, page heading, and back link. |
| [MembershipBundleForm](./membership-bundle-form.md) | Orchestrates the ordered set of page sections. |
| [useMembershipBundleBootstrap](./use-membership-bundle-bootstrap.md) | Hook that loads page data and polls during active renewal batches. |
| [IntroBlockSection](./intro-block-section.md) | Adapter that maps bundle `pageData` to the shared `IntroBlock` component. |
| [BundleMembersSection](./bundle-members-section.md) | Fetches and displays member counts broken down by tier. |
| [MembershipBundleOwnerSection](./membership-bundle-owner-section.md) | Adapter for the shared owner picker; calls the bundle ownership API on save. |
| [MembershipBundleRecordDetails](./membership-bundle-record-details.md) | Expanded row panel with billing, orders, status, dates, renewal type, and actions. |
| [AddMemberToBundleModal](./add-member-to-bundle-modal.md) | Three-step modal for adding an MDP person to the bundle. |
| [CancelMembershipBundleModal](./cancel-membership-bundle-modal.md) | Modal for choosing how individual memberships are handled on cancellation. |
| [CreateBundleRenewalOrderModal](./create-bundle-renewal-order-modal.md) | Modal that creates a WooCommerce renewal order off the bundle's subscription. |
| [RenewalProcessingOverlay](./renewal-processing-overlay.md) | Blocking overlay displayed while a renewal batch is in progress. |
