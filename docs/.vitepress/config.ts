import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'LaraWebhook',
  description: 'Secure webhook handling for Laravel',
  base: '/larawebhook/',

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/logo.svg' }],
  ],

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/getting-started' },
      { text: 'Services', link: '/services/' },
      { text: 'API', link: '/facade-api' },
      {
        text: 'Links',
        items: [
          { text: 'GitHub', link: 'https://github.com/proxynth/larawebhook' },
          { text: 'Packagist', link: 'https://packagist.org/packages/proxynth/larawebhook' },
          { text: 'Changelog', link: 'https://github.com/proxynth/larawebhook/blob/main/CHANGELOG.md' },
        ],
      },
    ],

    sidebar: [
      {
        text: 'Introduction',
        items: [
          { text: 'What is LaraWebhook?', link: '/' },
          { text: 'Getting Started', link: '/getting-started' },
          { text: 'Configuration', link: '/configuration' },
        ],
      },
      {
        text: 'Services',
        items: [
          { text: 'Overview', link: '/services/' },
          { text: 'Stripe', link: '/services/stripe' },
          { text: 'GitHub', link: '/services/github' },
          { text: 'Slack', link: '/services/slack' },
          { text: 'Shopify', link: '/services/shopify' },
        ],
      },
      {
        text: 'Features',
        items: [
          { text: 'Facade & Enum API', link: '/facade-api' },
          { text: 'Dashboard & REST API', link: '/dashboard' },
          { text: 'Failure Notifications', link: '/notifications' },
          { text: 'Best Practices', link: '/best-practices' },
        ],
      },
      {
        text: 'Advanced',
        items: [
          { text: 'Extending (Add Services)', link: '/extending' },
        ],
      },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/proxynth/larawebhook' },
    ],

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2024 Proxynth',
    },

    search: {
      provider: 'local',
    },

    editLink: {
      pattern: 'https://github.com/proxynth/larawebhook/edit/main/docs/:path',
      text: 'Edit this page on GitHub',
    },
  },
})
