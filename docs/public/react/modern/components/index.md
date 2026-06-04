---
title: Component Reference
---

# Component Reference

Quick reference for all shared components in `frontend/src/shared/`.

## Infrastructure

| Component | Category | Source file | Description |
|---|---|---|---|
| [AdminPageErrorBoundary](./admin-page-error-boundary) | Infrastructure | `shared/components/AdminPageErrorBoundary.js` | Class-based error boundary that catches render errors and shows a "Try again" fallback |
| [AdminLoadingSkeleton](./admin-loading-skeleton) | Infrastructure | `shared/components/AdminLoadingSkeleton.js` | Animated shimmer placeholder shown while page data is loading |
| [AdminNoticeStack](./admin-notice-stack) | Infrastructure | `shared/components/AdminNoticeStack.js` | Renders an ordered list of dismissible WordPress-style notices |

## Form Sections

| Component | Category | Source file | Description |
|---|---|---|---|
| [MembershipDetailsForm](./membership-details-form) | Form Sections | `shared/components/MembershipDetailsForm.js` | Composite form owning dates, renewal type, and optional owner fields |
| [MembershipStatusSection](./membership-status-section) | Form Sections | `shared/components/MembershipStatusSection.js` | Read-only status display with a "Manage Status" modal for transitions |
| [MembershipDatesSection](./membership-dates-section) | Form Sections | `shared/components/MembershipDatesSection.js` | Controlled three-field date picker row (start, end, expiry) |
| [MembershipOwnerSection](./membership-owner-section) | Form Sections | `shared/components/MembershipOwnerSection.js` | Owner async-select with save button, MDP link, and impersonation button |
| [MembershipBillingInfoSection](./membership-billing-info-section) | Form Sections | `shared/components/MembershipBillingInfoSection.js` | Billing summary block showing subscription ID and next payment date |
| [MembershipRecordsSection](./membership-records-section) | Form Sections | `shared/components/MembershipRecordsSection.js` | Expandable membership records table with caller-defined columns |
| [MembershipRenewalTypeSection](./membership-renewal-type-section) | Form Sections | `shared/components/MembershipRenewalTypeSection.js` | Renewal type selector with conditional sub-fields for form page or next tier |
| [CalendarSeasonsTable](./calendar-seasons-table) | Form Sections | `shared/components/CalendarSeasonsTable.js` | Read-only seasons table with per-row edit button |
| [SeasonConfigModal](./season-config-modal) | Form Sections | `shared/components/SeasonConfigModal.js` | Add/edit/archive season modal with validation |
| [IntroBlock](./intro-block) | Form Sections | `shared/components/IntroBlock.js` | Header block with entity title, action buttons, and metadata fields |
| [LocalizedCalloutModal](./localized-callout-modal) | Form Sections | `shared/components/LocalizedCalloutModal.js` | Per-locale callout editor (header, content, button label) |

## Primitive UI

| Component | Category | Source file | Description |
|---|---|---|---|
| [WicketModal](./wicket-modal) | Primitive UI | `shared/components/WicketModal.js` | Standard modal wrapper; renders null when closed |
| [WicketButton](./wicket-button) | Primitive UI | `shared/components/WicketButton.js` | `@wordpress/components` Button with `type="button"` default and optional dashicon |
| [Alert](./alert) | Primitive UI | `shared/components/Alert.js` | Dismissible success/error notice bar |
| [Pagination](./pagination) | Primitive UI | `shared/components/Pagination.js` | Previous/next buttons with editable page number input |

## Specialised Inputs

| Component | Category | Source file | Description |
|---|---|---|---|
| [MembershipOwnerAsyncSelect](./membership-owner-async-select) | Specialised Inputs | `shared/components/MembershipOwnerAsyncSelect.js` | Debounced async-select for MDP person by UUID |
| [OrgUuidAsyncSelect](./org-uuid-async-select) | Specialised Inputs | `shared/components/OrgUuidAsyncSelect.js` | Debounced async-select for MDP organisation by UUID |
| [MembershipDatePicker](./membership-date-picker) | Specialised Inputs | `shared/components/MembershipDatePicker.js` | Single labelled date picker field wrapping react-datepicker |
| [ModalPostSelector](./modal-post-selector) | Specialised Inputs | `shared/components/ModalPostSelector.js` | Modal-based picker for WP posts/pages or WooCommerce products |

## Hooks

| Component | Category | Source file | Description |
|---|---|---|---|
| [useResolvedOption](./use-resolved-option) | Hooks | `shared/hooks/useResolvedOption.js` | Resolves a single saved post or product ID into a `{ value, title }` option on mount |
