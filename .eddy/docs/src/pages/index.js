import { useEffect, useRef } from 'react';
import clsx from 'clsx';
import Layout from '@theme/Layout';
import Link from '@docusaurus/Link';
import { useBaseUrlUtils } from '@docusaurus/useBaseUrl';
import useBrokenLinks from '@docusaurus/useBrokenLinks';
import '@fontsource-variable/fraunces/full.css';
import '@fontsource-variable/fraunces/full-italic.css';
import '@fontsource-variable/figtree/wght.css';
import CopyCommand from '@site/src/components/CopyCommand';
import PausableImage from '@site/src/components/PausableImage';
import { prefersReducedMotion } from '@site/src/animation';
import styles from './index.module.css';

export const INSTALL_COMMAND = 'curl -fsSL https://eddy.drevops.com/install > install.php && php install.php';

export const DRUPAL_MAJORS = ['10', '11', '12'];
export const PHP_VERSIONS = ['8.3', '8.4', '8.5'];

export const PROJECTS_ID = 'projects-using-eddy';

const GITHUB_URL = 'https://github.com/drevops/eddy';

const NEW_TAB = <span className="visually-hidden"> (opens in new tab)</span>;

export const listOf = (items) => (items.length > 1 ? `${items.slice(0, -1).join(', ')} and ${items[items.length - 1]}` : items.join(''));

// The stops of the stream, from the installer to Drupal.org. A stop shows a
// recorded demo when one exists, and a list of notes otherwise.
const STATIONS = [
  {
    step: 'Source',
    title: 'Install',
    text: 'Answer a few questions: name, module or theme, Drupal versions, tools and a command wrapper. The installer keeps only what you pick.',
    command: 'php install.php',
    demo: { src: '/img/demos/install.svg', alt: 'The installer asking about the extension and rewriting the template to match' },
  },
  {
    step: 'Build',
    title: 'A real Drupal site',
    text: 'Drupal on your own PHP, served by PHP\'s webserver from an SQLite database. No containers to babysit.',
    command: 'ahoy build',
    demo: { src: '/img/demos/build.svg', alt: 'ahoy build assembling the codebase, starting the webserver and installing Drupal' },
  },
  {
    step: 'Filter',
    title: 'Coding standards',
    text: 'PHPCS, PHPStan, Drupal Rector, Twig CS Fixer, ESLint, Stylelint, CSpell and Prettier, set up for Drupal.',
    command: 'ahoy lint',
    demo: { src: '/img/demos/lint.svg', alt: 'ahoy lint running PHPCS, PHPStan, Rector, ESLint and CSpell' },
  },
  {
    step: 'Test the water',
    title: 'Tests',
    text: 'PHPUnit Unit, Kernel, Functional and FunctionalJavascript suites in your local Chrome or Selenium, plus Jest.',
    command: 'ahoy test',
    demo: { src: '/img/demos/test.svg', alt: 'ahoy test running the PHPUnit suites and the Jest tests' },
  },
  {
    step: 'Current',
    title: 'CI on every push',
    text: `GitHub Actions runs the same commands on PHP ${listOf(PHP_VERSIONS)} and Drupal ${listOf(DRUPAL_MAJORS)}, plus legacy and canary tiers.`,
    command: 'git push',
    notes: [
      [`lint-${DRUPAL_MAJORS.map((major) => `d${major}`).join(' · ')}`, 'coding standards'],
      ['test-php-min / max', 'on each major'],
      ['legacy · canary', 'the earliest supported release and the next beta'],
      ['Codecov', 'coverage from every test job'],
    ],
  },
  {
    step: 'Mouth',
    title: 'Drupal.org',
    text: 'Once CI passes, every tested commit and release tag is mirrored to Drupal.org, or any other git remote.',
    notes: [
      ['Branches', 'deploy the commit that CI tested'],
      ['Tags', 'deploy alone, and the branches stay put'],
      ['Never overtaken', 'by an older run'],
    ],
  },
];

const TOOLS = ['PHPUnit', 'Jest', 'PHPStan', 'Drupal Rector', 'PHPCS', 'Twig CS Fixer', 'ESLint', 'Stylelint', 'Prettier', 'CSpell', 'pcov', 'Codecov', 'GitHub Actions', 'GitLab CI', 'Drupal.org', 'Renovate', 'Composer', 'Drush', 'Make', 'Ahoy', 'Chrome', 'Selenium', 'Xdebug', 'SQLite', 'Cloudflare Tunnel'];

const PROJECTS = [
  { name: 'Testmode', repository: 'drevops/testmode', text: 'Alters existing site content and other configuration when running tests.' },
  { name: 'Generated Content', repository: 'drevops/generated_content', text: 'Generates content programmatically.' },
  { name: 'Integration Report', repository: 'drevops/integration_report', text: 'Reports on the availability of 3rd party endpoints.' },
  { name: 'Drupal Helpers', repository: 'drevops/drupal_helpers', text: 'Helper utilities for Drupal.' },
  { name: 'Deploy Steps', repository: 'drevops/deploy_steps', text: 'Runs repeatable run-on-every-deploy logic as discoverable plugins.' },
];

const LAYERS = [
  { depth: '0 m · surface', title: 'Template', text: 'Your repository: the extension, its CI workflows, the linter and test configuration, and the docs it ships with.' },
  { depth: '10 m · current', title: 'Tooling', text: 'The drevops/eddy-tooling package, whose eddy-* commands assemble, serve, provision and deploy the site, for you and for CI.' },
  { depth: '20 m · bed', title: 'Installer', text: 'A single command that downloads a release and shapes the template to your answers. An update renders the new release the same way and merges only what changed.' },
];

const DOCS = [
  { title: 'Introduction', to: '/introduction' },
  { title: 'Installation', to: '/installation' },
  { title: 'Development', to: '/development' },
  { title: 'Testing', to: '/testing' },
  { title: 'Continuous integration', to: '/continuous-integration' },
  { title: 'Deployment', to: '/deployment' },
  { title: 'Updating', to: '/updating' },
  { title: 'Contributing', to: '/contributing' },
];

const percent = (fraction) => `${Math.round(fraction * 100)}%`;

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

const Drop = () => (
  <div className={styles.drop} aria-hidden="true">
    <span className={styles.ring} />
    <span className={styles.ring} />
    <span className={styles.ring} />
    <span className={styles.ring} />
    <svg className={styles.mark} viewBox="60 12 160 222" focusable="false">
      <g fill="none" stroke="currentColor">
        <path strokeWidth="17.5" d="M140 22.8L70.26 145.3A80.25 80.25 0 1 0 209.74 145.3Z" />
        <path strokeWidth="18.5" d="M186.75 185A46.75 46.75 0 1 0 176.84 213.78" />
      </g>
      <path fill="currentColor" d="M93.25 175.5H195.19A56 56 0 0 1 195.73 190.5H93.25Z" />
    </svg>
  </div>
);

// Each stop draws its own stretch of the stream, through its node at the
// middle, so the stream meets every node whatever the height of the stop.
const Station = ({ index, step, title, text, command, demo, notes }) => {
  const { withBaseUrl } = useBaseUrlUtils();
  const gradient = `eddy-stream-${index}`;

  return (
    <li className={styles.station} style={{ '--stream-from': percent(index / STATIONS.length), '--stream-to': percent((index + 1) / STATIONS.length) }}>
      <svg className={styles.segment} viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true" focusable="false">
        <defs>
          <linearGradient id={gradient} x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" />
            <stop offset="1" />
          </linearGradient>
        </defs>
        <path stroke={`url(#${gradient})`} d="M50 0C90 15 90 35 50 50C10 65 10 85 50 100" />
      </svg>
      <span className={styles.node} aria-hidden="true" />
      <div className={styles.stationText} data-reveal="">
        <p className={styles.step}>
          <span aria-hidden="true">{index + 1} · </span>
          {step}
        </p>
        <h3 className={styles.stationTitle}>{title}</h3>
        <p className={styles.stationLead}>{text}</p>
        {command && <code className={styles.chip}>{command}</code>}
      </div>
      <div className={styles.media} data-reveal="">
        {demo ? (
          <PausableImage src={withBaseUrl(demo.src)} alt={demo.alt} width={800} height={441} />
        ) : (
          <ul className={styles.notes}>
            {notes.map(([term, detail]) => (
              <li key={term}>
                <b>{term}</b> {detail}
              </li>
            ))}
          </ul>
        )}
      </div>
    </li>
  );
};

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
        <header className={styles.hero}>
          <Drop />
          <p className={styles.eyebrow}>Template for contributed Drupal modules and themes</p>
          <h1 className={styles.title}>
            From one drop to <em>Drupal.org.</em>
          </h1>
          <p className={styles.lead}>You write the code. Eddy carries it the rest of the way: CI on GitHub Actions, a local Drupal site, coding standards and mirroring to Drupal.org.</p>
          <CopyCommand command={INSTALL_COMMAND} name="install command" className={styles.command} />
          <div className={styles.ctas}>
            <Link className={styles.round} to="/installation">
              Get started
            </Link>
            <Link className={styles.textLink} to="/introduction">
              Read the docs
            </Link>
          </div>
        </header>

        <section className={styles.section}>
          <h2 className={styles.sectionTitle}>Follow the stream</h2>
          <p className={styles.sectionLead}>The same commands carry your extension from your laptop to every tested release, and CI runs them too.</p>
          <ol className={styles.stations}>
            {STATIONS.map((station, index) => (
              <Station key={station.title} index={index} {...station} />
            ))}
          </ol>
        </section>

        <section className={styles.section}>
          <h2 className={styles.sectionTitle}>What floats along</h2>
          <p className={styles.sectionLead}>The tools and services that Eddy sets up for you.</p>
          <ul className={styles.floats} data-reveal="children">
            {TOOLS.map((tool) => (
              <li key={tool}>{tool}</li>
            ))}
          </ul>
        </section>

        <section className={styles.section}>
          <h2 id={PROJECTS_ID} className={styles.sectionTitle}>
            Downstream
          </h2>
          <p className={styles.sectionLead}>Contributed Drupal modules that start from Eddy.</p>
          <div className={styles.pebbles} data-reveal="children">
            {PROJECTS.map(({ name, repository, text }) => {
              const [owner, slug] = repository.split('/');

              return (
                <Link key={repository} href={`https://github.com/${repository}`} className={styles.pebble}>
                  <b>{name}</b>
                  <code>
                    {owner}/<wbr />
                    {slug}
                  </code>
                  <span className={styles.pebbleText}>{text}</span>
                  {NEW_TAB}
                </Link>
              );
            })}
          </div>
        </section>

        <div className={clsx(styles.deep, 'eddy-night')}>
          <svg className={styles.wave} viewBox="0 0 1440 70" preserveAspectRatio="none" aria-hidden="true" focusable="false">
            <path d="M0 70V48C140 22 300 14 460 38C620 62 760 66 920 40C1080 14 1240 10 1440 36V70Z" />
          </svg>

          <section className={styles.section}>
            <h2 className={styles.sectionTitle}>The deep end</h2>
            <p className={styles.sectionLead}>Under the surface, 3 parts keep every extension the same, and easy to keep current.</p>
            <div className={styles.layers} data-reveal="children">
              {LAYERS.map((layer) => (
                <div key={layer.title}>
                  <span className={styles.depth}>{layer.depth}</span>
                  <h3 className={styles.layerTitle}>{layer.title}</h3>
                  <p>{layer.text}</p>
                </div>
              ))}
            </div>
          </section>

          <section className={styles.section}>
            <h2 className={styles.sectionTitle}>Dive into the docs</h2>
            <p className={styles.sectionLead}>Maintained since 2015, with more than 60 releases, and free under GPL-2.0.</p>
            <div className={styles.docs} data-reveal="children">
              {DOCS.map((doc) => (
                <Link key={doc.to} to={doc.to}>
                  {doc.title}
                </Link>
              ))}
            </div>
          </section>

          <section className={styles.finale}>
            <h2 className={styles.finaleTitle}>Start with a drop.</h2>
            <CopyCommand command={INSTALL_COMMAND} name="install command" className={styles.command} />
            <div className={styles.ctas}>
              <Link className={styles.round} to="/installation">
                Get started
              </Link>
              <Link className={styles.textLink} href={GITHUB_URL}>
                Star on GitHub
                {NEW_TAB}
              </Link>
            </div>
          </section>
        </div>
      </main>
    </Layout>
  );
};

export default Home;
