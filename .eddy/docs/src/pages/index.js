import { useEffect, useRef } from 'react';
import clsx from 'clsx';
import Layout from '@theme/Layout';
import Link from '@docusaurus/Link';
import useBrokenLinks from '@docusaurus/useBrokenLinks';
import '@fontsource-variable/lexend/wght.css';
import CopyCommand from '@site/src/components/CopyCommand';
import DemoTerminal from '@site/src/components/DemoTerminal';
import Icon from '@site/src/components/Icon';
import ToolTicker from '@site/src/components/ToolTicker';
import { prefersReducedMotion } from '@site/src/animation';
import styles from './index.module.css';

export const INSTALL_COMMAND = 'curl -fsSL https://eddy.drevops.com/install > install.php && php install.php';

export const DRUPAL_MAJORS = ['10', '11', '12'];
export const PHP_VERSIONS = ['8.3', '8.4', '8.5'];

export const PROJECTS_ID = 'projects-using-eddy';

const GITHUB_URL = 'https://github.com/drevops/eddy';

export const listOf = (items) => (items.length > 1 ? `${items.slice(0, -1).join(', ')} and ${items[items.length - 1]}` : items.join(''));

const DEMOS = [
  {
    name: 'install',
    src: '/img/demos/install.svg',
    alt: 'The installer asking about the extension and rewriting the template to match',
    caption: 'The installer asks about your extension, then renames and rewrites the template to match.',
  },
  {
    name: 'build',
    src: '/img/demos/build.svg',
    alt: 'ahoy build assembling the codebase, starting the webserver and installing Drupal',
    caption: (
      <>
        <code>ahoy build</code> assembles the codebase, starts the webserver and installs Drupal.
      </>
    ),
  },
  {
    name: 'lint',
    src: '/img/demos/lint.svg',
    alt: 'ahoy lint running PHPCS, PHPStan, Rector, ESLint and CSpell',
    caption: (
      <>
        <code>ahoy lint</code> runs every linter you kept, the way the CI lint jobs do.
      </>
    ),
  },
  {
    name: 'test',
    src: '/img/demos/test.svg',
    alt: 'ahoy test running the PHPUnit suites and the Jest tests',
    caption: (
      <>
        <code>ahoy test</code> runs the PHPUnit suites and the Jest tests against the site.
      </>
    ),
  },
];

const STATS = [
  { value: '2015', label: 'Maintained since' },
  { value: '60+', label: 'Releases shipped' },
  { value: DRUPAL_MAJORS.join(' · '), label: 'Drupal majors in CI' },
  { value: 'GPL-2.0', label: 'Free and open source' },
];

const TOOLS = [
  ['PHPUnit', 'Jest', 'PHPStan', 'Drupal Rector', 'PHPCS', 'Twig CS Fixer', 'ESLint', 'Stylelint', 'Prettier', 'CSpell', 'pcov', 'Codecov'],
  ['GitHub Actions', 'GitLab CI', 'Drupal.org', 'Renovate', 'Composer', 'Drush', 'Make', 'Ahoy', 'Chrome', 'Selenium', 'Xdebug', 'SQLite', 'Cloudflare Tunnel'],
];

const FEATURES = [
  {
    icon: 'ci',
    title: 'CI on GitHub Actions',
    text: `PHP ${listOf(PHP_VERSIONS)} on Drupal ${listOf(DRUPAL_MAJORS)}, plus legacy and canary tiers, with coverage on Codecov. Works with Drupal.org GitLab CI too.`,
    to: '/continuous-integration',
  },
  {
    icon: 'laptop',
    title: 'Local development',
    text: 'A real Drupal site built on your machine\'s PHP, served by PHP\'s own webserver from an SQLite database.',
    to: '/development',
  },
  {
    icon: 'shield',
    title: 'Coding standards',
    text: 'PHPCS, PHPStan, Drupal Rector, Twig CS Fixer, ESLint, Stylelint, CSpell and Prettier, set up for Drupal.',
    to: '/development/coding-standards',
  },
  {
    icon: 'flask',
    title: 'Tests',
    text: 'PHPUnit Unit, Kernel, Functional and FunctionalJavascript suites in a local Chrome or Selenium, plus Jest.',
    to: '/testing',
  },
  {
    icon: 'send',
    title: 'Deployment',
    text: 'Every tested commit and release tag mirrored to Drupal.org, or any other git remote, once CI passes.',
    to: '/deployment',
  },
  {
    icon: 'refresh',
    title: 'Dependency updates',
    text: 'Renovate opens grouped pull requests that keep your dependencies current.',
    to: '/updating/renovate',
  },
  {
    icon: 'file',
    title: 'Docs for your extension',
    text: 'A README with CI and version badges, and a CONTRIBUTING guide that lists the commands your setup uses.',
    to: '/installation#what-the-installer-changes',
  },
  {
    icon: 'sparkles',
    title: 'Ready for AI agents',
    text: 'AGENTS.md and CLAUDE.md tell coding agents how to build, lint and test, and Claude Code can merge new Eddy releases for you.',
    to: '/updating#updating-with-claude-code',
  },
];

const PARTS = [
  {
    icon: 'layers',
    title: 'Template',
    text: 'Your repository: the extension, its CI workflows, the linter and test configuration, and the docs it ships with.',
  },
  {
    icon: 'terminal',
    title: 'Tooling',
    text: 'The drevops/eddy-tooling package. Its eddy-* commands assemble, serve, provision and deploy the site, for you and for CI.',
  },
  {
    icon: 'wand',
    title: 'Installer',
    text: 'A single command downloads a release, asks about your extension and removes everything you didn\'t pick.',
  },
];

const REASONS = [
  {
    icon: 'repeat',
    title: 'Same commands, locally and in CI',
    text: 'The commands you run are the ones CI runs, so a failure in CI reproduces on your machine with the same Drupal and PHP.',
  },
  {
    icon: 'zap',
    title: 'No containers to manage',
    text: 'The site builds on the PHP and Composer you already have, and browser tests drive your local Chrome. Selenium in Docker is there when you want it.',
  },
  {
    icon: 'merge',
    title: 'Updates that keep your changes',
    text: 'An update merges only what changed between your Eddy release and the latest one, so your own edits stay put.',
  },
];

const STEPS = [
  {
    title: 'Run the installer',
    text: 'Download it into a new, empty directory and run it. It fetches the latest release for you.',
    command: INSTALL_COMMAND,
    name: 'install command',
  },
  {
    title: 'Answer a few questions',
    text: 'Name your extension, then pick module or theme, the Drupal versions to test, the tools to keep and a command wrapper.',
  },
  {
    title: 'Build the site',
    text: 'Pick Make or Ahoy as the wrapper, and 1 command assembles Drupal, starts the webserver and installs your extension.',
    command: 'make build',
    name: 'build command',
  },
];

const DOCS = [
  { icon: 'info', title: 'Introduction', text: 'What Eddy is and how it fits together.', to: '/introduction' },
  { icon: 'download', title: 'Installation', text: 'From an empty directory to the first deployment.', to: '/installation' },
  { icon: 'code', title: 'Development', text: 'The local site and the daily commands.', to: '/development' },
  { icon: 'flask', title: 'Testing', text: 'PHPUnit and Jest, locally and in CI.', to: '/testing' },
  { icon: 'ci', title: 'Continuous integration', text: 'The workflows and the version matrix.', to: '/continuous-integration' },
  { icon: 'send', title: 'Deployment', text: 'Mirroring to Drupal.org once CI passes.', to: '/deployment' },
  { icon: 'refresh', title: 'Updating', text: 'Merging new Eddy releases into your extension.', to: '/updating' },
  { icon: 'users', title: 'Contributing', text: 'Working on Eddy itself.', to: '/contributing' },
];

const PROJECTS = [
  { name: 'Testmode', repository: 'drevops/testmode', text: 'Alters existing site content and other configuration when running tests.' },
  { name: 'Generated Content', repository: 'drevops/generated_content', text: 'Generates content programmatically.' },
  { name: 'Integration Report', repository: 'drevops/integration_report', text: 'Reports on the availability of 3rd party endpoints.' },
  { name: 'Drupal Helpers', repository: 'drevops/drupal_helpers', text: 'Helper utilities for Drupal.' },
  { name: 'Deploy Steps', repository: 'drevops/deploy_steps', text: 'Runs repeatable run-on-every-deploy logic as discoverable plugins.' },
];

// The opacity of each ring of the ripple, from the center out.
const RINGS = [0.34, 0.28, 0.23, 0.19, 0.155, 0.125, 0.1, 0.08, 0.06].map((opacity, index) => ({ opacity, radius: 70 + index * 52 }));

// Hides the blocks below the fold and shows each one as it scrolls into view.
// Without JavaScript, or for a reader who prefers reduced motion, every block
// stays visible.
export const useReveal = (root) => {
  useEffect(() => {
    if (prefersReducedMotion() || typeof IntersectionObserver === 'undefined') {
      return undefined;
    }

    const observer = new IntersectionObserver(
      (entries) => {
        entries
          .filter((entry) => entry.isIntersecting)
          .forEach((entry) => {
            entry.target.dataset.revealed = 'true';
            observer.unobserve(entry.target);
          });
      },
      { rootMargin: '0px 0px -10% 0px' },
    );

    root.current.querySelectorAll('[data-reveal]').forEach((block) => {
      // A block already in view stays as it is, so nothing on screen flickers.
      if (block.getBoundingClientRect().top < window.innerHeight) {
        return;
      }

      block.dataset.revealed = 'false';
      observer.observe(block);
    });

    return () => observer.disconnect();
  }, [root]);
};

const Ripples = ({ className }) => (
  <svg className={clsx(styles.ripples, className)} viewBox="0 0 1000 1000" aria-hidden="true" focusable="false">
    {RINGS.map(({ opacity, radius }, index) => (
      <circle key={radius} cx="500" cy="500" r={radius} strokeOpacity={opacity} style={{ '--ring': index }} />
    ))}
  </svg>
);

const SectionHead = ({ eyebrow, title, lead, id, centered = false }) => (
  <div className={clsx(styles.sectionHead, centered && styles.centered)} data-reveal="">
    <p className={styles.eyebrow}>{eyebrow}</p>
    <h2 id={id} className={styles.sectionTitle}>
      {title}
    </h2>
    {lead && <p className={styles.lead}>{lead}</p>}
  </div>
);

const Card = ({ icon, title, text, to, className }) => (
  <article className={clsx(styles.card, className)}>
    <span className={styles.iconTile}>
      <Icon name={icon} />
    </span>
    <h3 className={styles.cardTitle}>
      {to ? (
        <Link to={to} className={styles.stretchedLink}>
          {title}
        </Link>
      ) : (
        title
      )}
    </h3>
    <p className={styles.cardText}>{text}</p>
  </article>
);

const Hero = () => (
  <section className={styles.hero}>
    <div className={styles.wrap}>
      <div className={styles.heroGrid}>
        <div className={styles.heroText}>
          <p className={styles.badge}>Template for contributed Drupal modules and themes</p>
          <h1 className={styles.heroTitle}>
            <span className={styles.titleLine}>You write the code.</span>{' '}
            <span className={styles.titleLine}>
              <span className={styles.highlight}>Eddy handles the rest.</span>
            </span>
          </h1>
          <p className={styles.heroLead}>
            Start your Drupal module or theme from Eddy and get CI on GitHub Actions, a local Drupal site, coding standards checks and mirroring to Drupal.org, all run by the same commands on your machine and in CI.
          </p>
          <div className={styles.actions}>
            <Link className={clsx(styles.button, styles.primary)} to="/installation">
              Get started
              <Icon name="arrowRight" className={styles.buttonIcon} />
            </Link>
            <Link className={clsx(styles.button, styles.ghost)} to="/introduction">
              Read the docs
            </Link>
          </div>
        </div>
        <div className={styles.heroMedia}>
          <Ripples className={styles.heroRipples} />
          <DemoTerminal demos={DEMOS} label="Demos" />
        </div>
      </div>
      <div className={styles.install}>
        <p className={styles.installLabel}>Install with 1 command</p>
        <CopyCommand command={INSTALL_COMMAND} name="install command" />
        <p className={styles.installNote}>
          <Icon name="check" className={styles.noteIcon} />
          Run it in a new, empty directory. Needs PHP 8.3 or newer.
        </p>
      </div>
    </div>
  </section>
);

const Stats = () => (
  <section className={styles.stats}>
    <div className={styles.wrap}>
      <dl className={styles.statsGrid} data-reveal="children">
        {STATS.map(({ value, label }) => (
          <div key={label} className={styles.stat}>
            <dt className={styles.statLabel}>{label}</dt>
            <dd className={styles.statValue}>{value}</dd>
          </div>
        ))}
      </dl>
    </div>
  </section>
);

const Home = () => {
  const root = useRef(null);

  useReveal(root);
  useBrokenLinks().collectAnchor(PROJECTS_ID);

  return (
    <Layout
      title="Template for contributed Drupal modules and themes"
      description="Eddy is a template for contributed Drupal modules and themes, with CI on GitHub Actions, a local Drupal site, coding standards checks and mirroring to Drupal.org."
    >
      <main ref={root} className={styles.home}>
        <Hero />
        <Stats />

        <div className={styles.ticker}>
          <ToolTicker rows={TOOLS} label="Tools and services that Eddy sets up" />
        </div>

        <section className={styles.section}>
          <div className={styles.wrap}>
            <SectionHead eyebrow="What you get" title="Everything your extension needs, already wired up" lead="Each piece is configured to work with the others, and the installer keeps only the ones you pick." />
            <div className={styles.featureGrid} data-reveal="children">
              {FEATURES.map((feature) => (
                <Card key={feature.title} {...feature} />
              ))}
            </div>
          </div>
        </section>

        <section className={styles.section}>
          <div className={styles.wrap}>
            <SectionHead centered eyebrow="How it fits together" title="Template, tooling and installer" lead="Your repository stays small, because the commands that build, test and deploy it come from a package." />
            <div className={styles.threeGrid} data-reveal="children">
              {PARTS.map((part) => (
                <Card key={part.title} {...part} className={styles.part} />
              ))}
            </div>
          </div>
        </section>

        <section className={styles.section}>
          <div className={styles.wrap}>
            <SectionHead
              eyebrow="Why Eddy"
              title="Why maintainers choose Eddy"
              lead={
                <>
                  Eddy builds, lints and tests itself the way it does your extension, and deploys itself to <Link href="https://github.com/drevops/eddy_destination">drevops/eddy_destination</Link> as a working example.
                </>
              }
            />
            <div className={styles.threeGrid} data-reveal="children">
              {REASONS.map((reason) => (
                <Card key={reason.title} {...reason} />
              ))}
            </div>
          </div>
        </section>

        <section className={styles.section}>
          <div className={styles.wrap}>
            <SectionHead eyebrow="Get going" title="Up and running in 3 steps" />
            <ol className={styles.steps} data-reveal="children">
              {STEPS.map((step, index) => (
                <li key={step.title} className={styles.step}>
                  <span className={styles.stepNumber} aria-hidden="true">
                    <span>{index + 1}</span>
                  </span>
                  <div className={styles.stepBody}>
                    <h3 className={styles.stepTitle}>{step.title}</h3>
                    <p className={styles.cardText}>{step.text}</p>
                    {step.command && <CopyCommand compact command={step.command} name={step.name} className={styles.stepCommand} />}
                  </div>
                </li>
              ))}
            </ol>
          </div>
        </section>

        <section className={styles.section}>
          <div className={styles.wrap}>
            <SectionHead eyebrow="Keep exploring" title="Jump into the docs" />
            <div className={styles.docsGrid} data-reveal="children">
              {DOCS.map((doc) => (
                <Link key={doc.title} to={doc.to} className={styles.docCard}>
                  <span className={clsx(styles.iconTile, styles.iconTileSmall)}>
                    <Icon name={doc.icon} />
                  </span>
                  <span className={styles.docBody}>
                    <span className={styles.docTitle}>
                      {doc.title}
                      <Icon name="arrowUpRight" className={styles.docArrow} />
                    </span>
                    <span className={styles.cardText}>{doc.text}</span>
                  </span>
                </Link>
              ))}
            </div>
          </div>
        </section>

        <section className={styles.section}>
          <div className={styles.wrap}>
            <SectionHead id={PROJECTS_ID} eyebrow="In the wild" title="Projects using Eddy" lead="Contributed Drupal modules that start from Eddy." />
            <div className={styles.projectsGrid} data-reveal="children">
              {PROJECTS.map((project) => (
                <Link key={project.repository} href={`https://github.com/${project.repository}`} className={styles.docCard}>
                  <span className={clsx(styles.iconTile, styles.iconTileSmall)}>
                    <Icon name="package" />
                  </span>
                  <span className={styles.docBody}>
                    <span className={styles.docTitle}>
                      {project.name}
                      <Icon name="arrowUpRight" className={styles.docArrow} />
                    </span>
                    <span className={styles.repository}>{project.repository}</span>
                    <span className={styles.cardText}>{project.text}</span>
                  </span>
                </Link>
              ))}
            </div>
          </div>
        </section>

        <section className={clsx(styles.section, styles.ctaSection)}>
          <div className={styles.wrap}>
            <div className={styles.cta} data-reveal="">
              <Ripples className={styles.ctaRipples} />
              <h2 className={styles.ctaTitle}>Spend your time on the code, not the setup.</h2>
              <p className={styles.ctaLead}>Free and open source, and tested on every change the same way your extension will be.</p>
              <CopyCommand command={INSTALL_COMMAND} name="install command" className={styles.ctaCommand} />
              <div className={clsx(styles.actions, styles.ctaActions)}>
                <Link className={clsx(styles.button, styles.primary)} to="/installation">
                  Get started
                  <Icon name="arrowRight" className={styles.buttonIcon} />
                </Link>
                <Link className={clsx(styles.button, styles.ghost)} href={GITHUB_URL}>
                  <Icon name="github" className={styles.buttonIcon} />
                  Star on GitHub
                </Link>
              </div>
            </div>
          </div>
        </section>
      </main>
    </Layout>
  );
};

export default Home;
