import { defineConfig, type TransformPageContext } from 'vitepress'
import { withMermaid } from 'vitepress-plugin-mermaid'

export default withMermaid(defineConfig({
  title: 'Wicket Memberships',
  description: 'Developer documentation for the Wicket Memberships WordPress plugin',
  base: '/wicket-wp-memberships/',

  lastUpdated: true,

  transformPageData(pageData) {
    const title = pageData.frontmatter.title
      ? `${pageData.frontmatter.title} — Wicket Memberships`
      : 'Wicket Memberships — Developer Documentation'
    pageData.frontmatter.head ??= []
    pageData.frontmatter.head.push(
      ['meta', { property: 'og:title', content: title }],
      ['meta', { property: 'og:description', content: pageData.description || 'Developer documentation for the Wicket Memberships WordPress plugin.' }],
      ['meta', { name: 'twitter:title', content: title }],
    )
  },

  head: [
    ['meta', { property: 'og:type', content: 'website' }],
    ['meta', { property: 'og:title', content: 'Wicket Memberships — Developer Documentation' }],
    ['meta', { property: 'og:description', content: 'Public API reference for the Wicket Memberships WordPress plugin — classes, REST endpoints, and conceptual models.' }],
    ['meta', { name: 'twitter:card', content: 'summary' }],
    ['meta', { name: 'twitter:title', content: 'Wicket Memberships — Developer Documentation' }],
    ['meta', { name: 'twitter:description', content: 'Public API reference for the Wicket Memberships WordPress plugin.' }],
  ],

  themeConfig: {
    nav: [
      { text: 'Home', link: '/' },
      { text: 'Membership Bundles', link: '/membership-bundles/' },
      { text: 'React Frontend', link: '/react/' },
    ],

    sidebar: [
      {
        text: 'Overview',
        items: [
          { text: 'Introduction', link: '/' },
        ],
      },
      {
        text: 'Membership Bundles',
        collapsed: true,
        items: [
          { text: 'Overview', link: '/membership-bundles/' },
          { text: 'Getting Started', link: '/membership-bundles/getting-started' },
          {
            text: 'Concepts',
            collapsed: true,
            items: [
              { text: 'Bundle Lifecycle', link: '/membership-bundles/concepts/bundle-lifecycle' },
              { text: 'Renewal Types', link: '/membership-bundles/concepts/renewal-types' },
              { text: 'Member Handling', link: '/membership-bundles/concepts/member-handling' },
            ],
          },
          {
            text: 'Class Reference',
            collapsed: true,
            items: [
              { text: 'Membership_Bundle', link: '/membership-bundles/classes/membership-bundle' },
              { text: 'Membership_Bundle_Config', link: '/membership-bundles/classes/membership-bundle-config' },
              { text: 'Membership_Bundle_Admin_Controller', link: '/membership-bundles/classes/membership-bundle-admin-controller' },
            ],
          },
          {
            text: 'REST API',
            collapsed: true,
            items: [
              { text: 'Overview', link: '/membership-bundles/endpoints/overview' },
              { text: 'Bundles', link: '/membership-bundles/endpoints/bundles' },
              { text: 'Bundle Members', link: '/membership-bundles/endpoints/bundle-members' },
              { text: 'Bundle Status', link: '/membership-bundles/endpoints/bundle-status' },
              { text: 'Bundle Config Dates', link: '/membership-bundles/endpoints/bundle-config-dates' },
            ],
          },
        ],
      },
      {
        text: 'React Frontend',
        collapsed: true,
        items: [
          { text: 'Overview', link: '/react/' },
          { text: 'Architecture & Patterns', link: '/react/architecture' },
          {
            text: 'Shared Utilities',
            collapsed: true,
            items: [
              { text: 'API Service Layer', link: '/react/shared/api' },
              { text: 'Constants & Utilities', link: '/react/shared/constants' },
              { text: 'Styled Elements', link: '/react/shared/styled-elements' },
            ],
          },
          {
            text: 'Modern',
            collapsed: true,
            items: [
              { text: 'Architecture Overview', link: '/react/modern/' },
              {
                text: 'Components',
                collapsed: true,
                items: [
                  { text: 'Component Reference', link: '/react/modern/components/' },
                  { text: 'AdminPageErrorBoundary', link: '/react/modern/components/admin-page-error-boundary' },
                  { text: 'AdminLoadingSkeleton', link: '/react/modern/components/admin-loading-skeleton' },
                  { text: 'AdminNoticeStack', link: '/react/modern/components/admin-notice-stack' },
                  { text: 'WicketModal', link: '/react/modern/components/wicket-modal' },
                  { text: 'WicketButton', link: '/react/modern/components/wicket-button' },
                  { text: 'Alert', link: '/react/modern/components/alert' },
                  { text: 'Pagination', link: '/react/modern/components/pagination' },
                  { text: 'IntroBlock', link: '/react/modern/components/intro-block' },
                  { text: 'MembershipDetailsForm', link: '/react/modern/components/membership-details-form' },
                  { text: 'MembershipStatusSection', link: '/react/modern/components/membership-status-section' },
                  { text: 'MembershipDatesSection', link: '/react/modern/components/membership-dates-section' },
                  { text: 'MembershipOwnerSection', link: '/react/modern/components/membership-owner-section' },
                  { text: 'MembershipBillingInfoSection', link: '/react/modern/components/membership-billing-info-section' },
                  { text: 'MembershipRecordsSection', link: '/react/modern/components/membership-records-section' },
                  { text: 'MembershipRenewalTypeSection', link: '/react/modern/components/membership-renewal-type-section' },
                  { text: 'CalendarSeasonsTable', link: '/react/modern/components/calendar-seasons-table' },
                  { text: 'SeasonConfigModal', link: '/react/modern/components/season-config-modal' },
                  { text: 'MembershipOwnerAsyncSelect', link: '/react/modern/components/membership-owner-async-select' },
                  { text: 'OrgUuidAsyncSelect', link: '/react/modern/components/org-uuid-async-select' },
                  { text: 'MembershipDatePicker', link: '/react/modern/components/membership-date-picker' },
                  { text: 'ModalPostSelector', link: '/react/modern/components/modal-post-selector' },
                  { text: 'LocalizedCalloutModal', link: '/react/modern/components/localized-callout-modal' },
                  { text: 'useResolvedOption', link: '/react/modern/components/use-resolved-option' },
                ],
              },
              {
                text: 'Membership Bundles',
                collapsed: true,
                items: [
                  { text: 'Overview', link: '/react/modern/membership-bundles/' },
                  { text: 'MembershipBundlePage', link: '/react/modern/membership-bundles/membership-bundle-page' },
                  { text: 'MembershipBundleForm', link: '/react/modern/membership-bundles/membership-bundle-form' },
                  { text: 'useMembershipBundleBootstrap', link: '/react/modern/membership-bundles/use-membership-bundle-bootstrap' },
                  { text: 'BundleMembersSection', link: '/react/modern/membership-bundles/bundle-members-section' },
                  { text: 'AddMemberToBundleModal', link: '/react/modern/membership-bundles/add-member-to-bundle-modal' },
                  { text: 'CancelMembershipBundleModal', link: '/react/modern/membership-bundles/cancel-membership-bundle-modal' },
                  { text: 'RenewalProcessingOverlay', link: '/react/modern/membership-bundles/renewal-processing-overlay' },
                  { text: 'CreateBundleRenewalOrderModal', link: '/react/modern/membership-bundles/create-bundle-renewal-order-modal' },
                  { text: 'IntroBlockSection', link: '/react/modern/membership-bundles/intro-block-section' },
                  { text: 'MembershipBundleOwnerSection', link: '/react/modern/membership-bundles/membership-bundle-owner-section' },
                  { text: 'MembershipBundleRecordDetails', link: '/react/modern/membership-bundles/membership-bundle-record-details' },
                ],
              },
              {
                text: 'Bundle Configs',
                collapsed: true,
                items: [
                  { text: 'Overview', link: '/react/modern/bundle-configs/' },
                  { text: 'BundleConfigPage', link: '/react/modern/bundle-configs/bundle-config-page' },
                  { text: 'BundleConfigForm', link: '/react/modern/bundle-configs/bundle-config-form' },
                  { text: 'useBundleConfigBootstrap', link: '/react/modern/bundle-configs/use-bundle-config-bootstrap' },
                  { text: 'Form Utilities', link: '/react/modern/bundle-configs/form-utils' },
                ],
              },
              {
                text: 'Create Bundle',
                collapsed: true,
                items: [
                  { text: 'Overview', link: '/react/modern/create-bundle/' },
                  { text: 'CreateMembershipBundlePage', link: '/react/modern/create-bundle/create-membership-bundle-page' },
                ],
              },
            ],
          },
          {
            text: 'Legacy',
            collapsed: true,
            items: [
              { text: 'Overview', link: '/react/legacy/' },
              { text: 'Members (Legacy)', link: '/react/legacy/members' },
              { text: 'Membership Configs (Legacy)', link: '/react/legacy/membership-configs' },
              { text: 'Membership Tiers (Legacy)', link: '/react/legacy/membership-tiers' },
            ],
          },
        ],
      },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/industrialdev/wicket-wp-memberships' },
    ],

    search: {
      provider: 'local',
    },

    editLink: {
      pattern: 'https://github.com/industrialdev/wicket-wp-memberships/edit/feature/users-multi-tier-renewal-subscriptions-merged-membership-groups/docs/public/:path',
      text: 'Edit this page on GitHub',
    },

    footer: {
      message: 'Wicket Memberships Plugin — Internal Developer Documentation',
    },

    darkModeSwitchLabel: 'Appearance',

    lastUpdated: {
      text: 'Last updated',
      formatOptions: {
        dateStyle: 'medium',
        timeStyle: 'short',
      },
    },

    docFooter: {
      prev: '← Previous',
      next: 'Next →',
    },
  },

  markdown: {
    lineNumbers: true,
  },

  mermaid: {},
}))
