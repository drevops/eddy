import { useRef } from 'react';
import { act, cleanup, render, screen, within } from '@testing-library/react';
import useBrokenLinks from '@docusaurus/useBrokenLinks';
import Home, { DRUPAL_MAJORS, INSTALL_COMMAND, PHP_VERSIONS, PROJECTS_ID, listOf, useReveal } from '../../../src/pages';

// Records what the hook observes, and plays the entries a test passes it.
class FakeObserver {
  static instances = [];

  constructor(callback, options) {
    this.callback = callback;
    this.options = options;
    this.observed = new Set();
    this.disconnected = false;
    FakeObserver.instances.push(this);
  }

  observe(element) {
    this.observed.add(element);
  }

  unobserve(element) {
    this.observed.delete(element);
  }

  disconnect() {
    this.disconnected = true;
    this.observed.clear();
  }

  trigger(entries) {
    act(() => this.callback(entries));
  }
}

let reducedMotion;

beforeEach(() => {
  reducedMotion = false;
  window.matchMedia = jest.fn((query) => ({ matches: query === '(prefers-reduced-motion: reduce)' && reducedMotion }));
  global.fetch = jest.fn(() => Promise.resolve({ ok: true, text: () => Promise.resolve('<svg/>') }));
});

afterEach(() => {
  cleanup();
  delete window.matchMedia;
  delete global.fetch;
});

describe('Home', () => {
  // Lets the demo fetches settle.
  const renderHome = async () => {
    const view = render(<Home />);

    await act(async () => {});

    return view;
  };

  const stops = (container) => Array.from(container.querySelector('ol').children);

  test('describes the page to search engines and the browser', async () => {
    await renderHome();

    const layout = screen.getByTestId('layout');

    expect(layout).toHaveAttribute('data-title', 'Template for contributed Drupal modules and themes');
    expect(layout.getAttribute('data-description')).toMatch(/^Eddy is a template for contributed Drupal modules and themes/);
  });

  test('opens with the promise and the 2 ways in', async () => {
    await renderHome();

    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('From one drop to Drupal.org.');
    expect(screen.getAllByRole('link', { name: 'Get started' }).map((link) => link.getAttribute('href'))).toEqual(['/installation', '/installation']);
    expect(screen.getByRole('link', { name: 'Read the docs' })).toHaveAttribute('href', '/introduction');
  });

  test('offers the install command in the hero and the closing call', async () => {
    const { container } = await renderHome();

    expect(screen.getAllByRole('button', { name: 'Copy the install command' })).toHaveLength(2);
    expect(Array.from(container.querySelectorAll('code')).filter((code) => code.textContent === `$ ${INSTALL_COMMAND}`)).toHaveLength(2);
  });

  test('follows the stream through 6 stops, with the command of each', async () => {
    const { container } = await renderHome();
    const list = container.querySelector('ol');

    expect(within(list).getAllByRole('heading', { level: 3 }).map((heading) => heading.textContent)).toEqual(['Install', 'A real Drupal site', 'Coding standards', 'Tests', 'CI on every push', 'Drupal.org']);
    expect(stops(container).map((stop) => stop.querySelector('.chip')?.textContent)).toEqual(['php install.php', 'ahoy build', 'ahoy lint', 'ahoy test', 'git push', undefined]);
  });

  test('plays a demo at each of the local stops', async () => {
    await renderHome();

    expect(screen.getAllByRole('img').map((image) => [image.getAttribute('src'), image.getAttribute('alt')])).toEqual([
      ['/base/img/demos/install.svg', 'The installer asking about the extension and rewriting the template to match'],
      ['/base/img/demos/build.svg', 'ahoy build assembling the codebase, starting the webserver and installing Drupal'],
      ['/base/img/demos/lint.svg', 'ahoy lint running PHPCS, PHPStan, Rector, ESLint and CSpell'],
      ['/base/img/demos/test.svg', 'ahoy test running the PHPUnit suites and the Jest tests'],
    ]);
  });

  test('lists the CI jobs and the deployment rules in place of a demo', async () => {
    const { container } = await renderHome();
    const notes = stops(container).slice(4).map((stop) => within(stop).getAllByRole('listitem').map((item) => item.textContent));

    expect(notes).toEqual([
      ['lint-d10 · d11 · d12 coding standards', 'test-php-min / max on each major', 'legacy · canary the earliest supported release and the next beta', 'Codecov coverage from every test job'],
      ['Branches deploy the commit that CI tested', 'Tags deploy alone, and the branches stay put', 'Never overtaken by an older run'],
    ]);
  });

  test('states the PHP and Drupal versions that CI tests', async () => {
    await renderHome();

    expect(screen.getByText(/^GitHub Actions runs the same commands on PHP 8\.3, 8\.4 and 8\.5 and Drupal 10, 11 and 12,/)).toBeInTheDocument();
    expect(DRUPAL_MAJORS).toEqual(['10', '11', '12']);
    expect(PHP_VERSIONS).toEqual(['8.3', '8.4', '8.5']);
  });

  test('draws a stretch of the stream for each stop, with a gradient of its own', async () => {
    const { container } = await renderHome();

    expect(stops(container).map((stop) => [stop.style.getPropertyValue('--stream-from'), stop.style.getPropertyValue('--stream-to')])).toEqual([
      ['0%', '17%'],
      ['17%', '33%'],
      ['33%', '50%'],
      ['50%', '67%'],
      ['67%', '83%'],
      ['83%', '100%'],
    ]);

    stops(container).forEach((stop, index) => {
      expect(stop.querySelector('linearGradient')).toHaveAttribute('id', `eddy-stream-${index}`);
      expect(stop.querySelector('path')).toHaveAttribute('stroke', `url(#eddy-stream-${index})`);
    });
  });

  test('shows the tools', async () => {
    await renderHome();

    const section = screen.getByRole('heading', { name: 'What floats along' }).closest('section');

    expect(within(section).getAllByRole('listitem')).toHaveLength(25);
  });

  test.each([
    ['Introduction', '/introduction'],
    ['Installation', '/installation'],
    ['Development', '/development'],
    ['Testing', '/testing'],
    ['Continuous integration', '/continuous-integration'],
    ['Deployment', '/deployment'],
    ['Updating', '/updating'],
    ['Contributing', '/contributing'],
  ])('links the docs page %s at %s', async (name, href) => {
    await renderHome();

    expect(screen.getByRole('link', { name })).toHaveAttribute('href', href);
  });

  test('anchors the projects section for the link checker', async () => {
    await renderHome();

    expect(screen.getByRole('heading', { name: 'Downstream' })).toHaveAttribute('id', PROJECTS_ID);
    expect(useBrokenLinks().collectAnchor).toHaveBeenCalledWith(PROJECTS_ID);
  });

  test.each([
    ['Testmode', 'drevops/testmode'],
    ['Generated Content', 'drevops/generated_content'],
    ['Integration Report', 'drevops/integration_report'],
    ['Drupal Helpers', 'drevops/drupal_helpers'],
    ['Deploy Steps', 'drevops/deploy_steps'],
  ])('links the %s project to %s', async (name, repository) => {
    await renderHome();

    const link = screen.getByRole('link', { name: new RegExp(`^${name}`) });

    expect(link).toHaveAttribute('href', `https://github.com/${repository}`);
    expect(within(link).getByText(repository)).toContainHTML('/<wbr>');
  });

  test('says that every external link opens a new tab', async () => {
    const { container } = await renderHome();
    const external = Array.from(container.querySelectorAll('a[href^="https://"]'));

    expect(external).toHaveLength(6);
    external.forEach((link) => {
      expect(link).toHaveAccessibleName(/ \(opens in new tab\)$/);
    });
    expect(screen.getByRole('link', { name: 'Star on GitHub (opens in new tab)' })).toHaveAttribute('href', 'https://github.com/drevops/eddy');
  });

  test('gives the deep end the text colors of dark water', async () => {
    await renderHome();

    expect(screen.getByRole('heading', { name: 'The deep end' }).closest('.eddy-night')).not.toBeNull();
    expect(screen.getByRole('heading', { name: 'Downstream' }).closest('.eddy-night')).toBeNull();
  });

  test('hides every drawing from assistive technology', async () => {
    const { container } = await renderHome();
    const drawings = Array.from(container.querySelectorAll('svg'));

    expect(drawings).toHaveLength(8);
    drawings.forEach((svg) => {
      expect(svg.closest('[aria-hidden="true"]')).not.toBeNull();
    });
  });
});

describe('listOf', () => {
  test.each([
    { items: [], expected: '' },
    { items: ['10'], expected: '10' },
    { items: ['10', '11'], expected: '10 and 11' },
    { items: ['10', '11', '12'], expected: '10, 11 and 12' },
  ])('joins $items as "$expected"', ({ items, expected }) => {
    expect(listOf(items)).toBe(expected);
  });
});

describe('useReveal', () => {
  const Blocks = () => {
    const root = useRef(null);

    useReveal(root);

    return (
      <div ref={root}>
        <p data-reveal="" data-testid="above">
          Above
        </p>
        <p data-reveal="" data-testid="below">
          Below
        </p>
        <div data-reveal="children" data-testid="grid">
          <span>Card</span>
        </div>
        <p data-testid="plain">Plain</p>
      </div>
    );
  };

  beforeEach(() => {
    FakeObserver.instances = [];
    global.IntersectionObserver = FakeObserver;

    // The block marked 'above' starts in view, and the others below the fold.
    jest.spyOn(Element.prototype, 'getBoundingClientRect').mockImplementation(function boundingRect() {
      return { top: this.dataset.testid === 'above' ? 100 : window.innerHeight + 100 };
    });
  });

  afterEach(() => {
    jest.restoreAllMocks();
    delete global.IntersectionObserver;
  });

  const revealed = (id) => screen.getByTestId(id).dataset.revealed;

  test('hides the blocks below the fold and leaves the rest alone', () => {
    render(<Blocks />);

    const [observer] = FakeObserver.instances;

    expect(revealed('above')).toBeUndefined();
    expect(revealed('below')).toBe('false');
    expect(revealed('grid')).toBe('false');
    expect(revealed('plain')).toBeUndefined();
    expect(Array.from(observer.observed)).toEqual([screen.getByTestId('below'), screen.getByTestId('grid')]);
    expect(observer.options).toEqual({ rootMargin: '0px 0px -10% 0px' });
  });

  test('shows a block once it scrolls into view and stops watching it', () => {
    render(<Blocks />);

    const [observer] = FakeObserver.instances;

    observer.trigger([
      { target: screen.getByTestId('below'), isIntersecting: true },
      { target: screen.getByTestId('grid'), isIntersecting: false },
    ]);

    expect(revealed('below')).toBe('true');
    expect(revealed('grid')).toBe('false');
    expect(Array.from(observer.observed)).toEqual([screen.getByTestId('grid')]);
  });

  test('stops watching once unmounted', () => {
    const { unmount } = render(<Blocks />);

    unmount();

    expect(FakeObserver.instances[0].disconnected).toBe(true);
  });

  test.each([
    {
      name: 'for a reader who prefers reduced motion',
      setUp: () => {
        reducedMotion = true;
      },
    },
    {
      name: 'without IntersectionObserver',
      setUp: () => {
        delete global.IntersectionObserver;
      },
    },
  ])('leaves every block visible $name', ({ setUp }) => {
    setUp();

    render(<Blocks />);

    expect(['above', 'below', 'grid', 'plain'].map(revealed)).toEqual([undefined, undefined, undefined, undefined]);
    expect(FakeObserver.instances).toHaveLength(0);
  });
});
