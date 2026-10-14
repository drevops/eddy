// @ts-check
// @see https://docusaurus.io/docs/api/docusaurus-config

import {themes as prismThemes} from 'prism-react-renderer';

// The release workflow sets the version. Other builds, such as the Netlify
// development site and pull request previews, stay out of the analytics and
// the search engines.
const isRelease = Boolean(process.env.RELEASE_VERSION);

/** @type {import('@docusaurus/types').Config} */
const config = {
  title: 'Eddy',
  tagline: 'Template for contributed Drupal modules and themes',
  favicon: 'img/favicon.svg',
  noIndex: !isRelease,

  // The release workflow sets both from the GitHub Pages settings, so a
  // custom domain needs no change here.
  url: process.env.DOCS_URL || 'https://drevops.github.io',
  baseUrl: process.env.DOCS_BASE_URL || '/',

  // GitHub Pages serves '<page>.html' at '/<page>', without the redirect that
  // a '<page>/index.html' directory page needs.
  trailingSlash: false,

  organizationName: 'drevops',
  projectName: 'eddy',

  onBrokenLinks: 'throw',
  onBrokenAnchors: 'throw',

  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },

  presets: [
    [
      'classic',
      /** @type {import('@docusaurus/preset-classic').Options} */
      ({
        docs: {
          routeBasePath: '/',
          sidebarPath: './sidebars.js',
          path: 'content',
          editUrl: 'https://github.com/drevops/eddy/edit/main/.eddy/docs/',
        },
        blog: false,
        theme: {
          customCss: './src/css/custom.css',
        },
        gtag: isRelease ? {trackingID: 'G-LZ472DBP7Z', anonymizeIP: true} : undefined,
      }),
    ],
  ],

  markdown: {
    mermaid: true,
    hooks: {
      onBrokenMarkdownLinks: 'throw',
    },
  },

  themes: [
    [
      '@easyops-cn/docusaurus-search-local',
      /** @type {import("@easyops-cn/docusaurus-search-local").PluginOptions} */
      ({
        // @see https://github.com/easyops-cn/docusaurus-search-local#theme-options
        searchBarPosition: 'left',
        docsDir: 'content',
        docsRouteBasePath: '/',
        indexBlog: false,
        hashed: true,
        highlightSearchTermsOnTargetPage: true,
        explicitSearchResultPath: true,
      }),
    ],
    '@docusaurus/theme-mermaid',
  ],

  themeConfig:
  /** @type {import('@docusaurus/preset-classic').ThemeConfig} */
    ({
      image: 'img/social-card.png',
      metadata: [
        {name: 'keywords', content: 'drupal, module, theme, contrib, template, scaffold, github actions, drupal.org'},
      ],
      docs: {
        sidebar: {
          hideable: true,
          autoCollapseCategories: true,
        },
      },
      navbar: {
        logo: {
          alt: 'Eddy',
          src: 'img/logo.svg',
          srcDark: 'img/logo-dark.svg',
        },
        items: [
          {
            label: 'Download',
            href: 'https://github.com/drevops/eddy/releases/latest',
            position: 'right',
            title: 'Download the latest release',
          },
          {
            href: 'https://github.com/drevops/eddy',
            label: 'GitHub',
            position: 'right',
            title: 'View source on GitHub',
          },
          {
            type: 'search',
            position: 'right',
          },
        ],
      },
      footer: {
        style: 'dark',
        links: [
          {
            title: 'Docs',
            items: [
              {label: 'Installation', to: '/installation'},
              {label: 'Development', to: '/development'},
              {label: 'Updating', to: '/updating'},
              {label: 'Contributing', to: '/contributing'},
            ],
          },
          {
            title: 'Project',
            items: [
              {label: 'GitHub', href: 'https://github.com/drevops/eddy'},
              {label: 'Releases', href: 'https://github.com/drevops/eddy/releases'},
              {label: 'Issues', href: 'https://github.com/drevops/eddy/issues'},
            ],
          },
          {
            title: 'Related',
            items: [
              {label: 'Tooling package', href: 'https://github.com/drevops/eddy-tooling'},
              {label: 'Vortex', href: 'https://www.vortextemplate.com'},
            ],
          },
        ],
        copyright: `Eddy version: ${process.env.RELEASE_VERSION || 'development'}<br/>Drupal is a <a class="footer__copyright-link" href="https://www.drupal.org/about/trademark">registered trademark</a> of <a class="footer__copyright-link" href="https://dri.es/">Dries Buytaert</a>.<br/>Copyright ©${new Date().getFullYear()} <a class="footer__copyright-link" href="https://www.drevops.com/">DrevOps®</a>. Built with <a class="footer__copyright-link" href="https://docusaurus.io/">Docusaurus</a>.`,
      },
      prism: {
        theme: prismThemes.github,
        darkTheme: prismThemes.dracula,
        additionalLanguages: ['bash', 'json', 'php'],
      },
      mermaid: {
        theme: {light: 'neutral', dark: 'dark'},
      },
      colorMode: {
        defaultMode: 'light',
        disableSwitch: false,
        respectPrefersColorScheme: true,
      },
    }),
};

export default config;
