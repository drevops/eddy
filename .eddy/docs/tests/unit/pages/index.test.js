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
  // Lets the demo fetch settle.
  const renderHome = async () => {
    const view = render(<Home />);

    await act(async () => {});

    return view;
  };

  test('describes the page to search engines and the browser', async () => {
    await renderHome();

    const layout = screen.getByTestId('layout');

    expect(layout).toHaveAttribute('data-title', 'Template for contributed Drupal modules and themes');
    expect(layout.getAttribute('data-description')).toMatch(/^Eddy is a template for contributed Drupal modules and themes/);
  });

  test('opens with the promise and the 2 ways in', async () => {
    await renderHome();

    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('You write the code. Eddy handles the rest.');
    expect(screen.getAllByRole('link', { name: 'Get started' }).map((link) => link.getAttribute('href'))).toEqual(['/installation', '/installation']);
    expect(screen.getByRole('link', { name: 'Read the docs' })).toHaveAttribute('href', '/introduction');
  });

  test('offers the install command in the hero, the first step and the closing call', async () => {
    const { container } = await renderHome();

    expect(screen.getAllByRole('button', { name: 'Copy the install command' })).toHaveLength(3);
    expect(Array.from(container.querySelectorAll('code')).filter((code) => code.textContent === `$ ${INSTALL_COMMAND}`)).toHaveLength(3);
    expect(screen.getByRole('button', { name: 'Copy the build command' })).toBeInTheDocument();
  });

  test('states the PHP and Drupal versions that CI tests', async () => {
    await renderHome();

    expect(screen.getByText(/^PHP 8\.3, 8\.4 and 8\.5 on Drupal 10, 11 and 12,/)).toBeInTheDocument();
    expect(screen.getByText('10 · 11 · 12')).toBeInTheDocument();
    expect(DRUPAL_MAJORS).toEqual(['10', '11', '12']);
    expect(PHP_VERSIONS).toEqual(['8.3', '8.4', '8.5']);
  });

  test('shows the demos and the tools', async () => {
    await renderHome();

    expect(within(screen.getByRole('tablist', { name: 'Demos' })).getAllByRole('tab').map((tab) => tab.textContent)).toEqual(['install', 'build', 'lint', 'test']);
    expect(within(screen.getByRole('group', { name: 'Tools and services that Eddy sets up' })).getAllByRole('listitem').length).toBeGreaterThan(20);
  });

  test.each([
    ['CI on GitHub Actions', '/continuous-integration'],
    ['Local development', '/development'],
    ['Coding standards', '/development/coding-standards'],
    ['Tests', '/testing'],
    ['Deployment', '/deployment'],
    ['Dependency updates', '/updating/renovate'],
    ['Docs for your extension', '/installation#what-the-installer-changes'],
    ['Ready for AI agents', '/updating#updating-with-claude-code'],
  ])('links the %s feature to %s', async (name, href) => {
    await renderHome();

    expect(screen.getByRole('link', { name })).toHaveAttribute('href', href);
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
  ])('links the docs card %s to %s', async (name, href) => {
    await renderHome();

    expect(screen.getByRole('link', { name: new RegExp(`^${name} `) })).toHaveAttribute('href', href);
  });

  test('anchors the projects section for the link checker', async () => {
    await renderHome();

    expect(screen.getByRole('heading', { name: 'Projects using Eddy' })).toHaveAttribute('id', PROJECTS_ID);
    expect(useBrokenLinks().collectAnchor).toHaveBeenCalledWith(PROJECTS_ID);
  });

  test.each(['testmode', 'generated_content', 'integration_report', 'drupal_helpers', 'deploy_steps'])('links the %s project to its repository', async (name) => {
    await renderHome();

    const link = screen.getByRole('link', { name: new RegExp(`drevops/${name} `) });

    expect(link).toHaveAttribute('href', `https://github.com/drevops/${name}`);
  });

  test('links the example destination and the repository', async () => {
    await renderHome();

    expect(screen.getByRole('link', { name: 'drevops/eddy_destination' })).toHaveAttribute('href', 'https://github.com/drevops/eddy_destination');
    expect(screen.getByRole('link', { name: 'Star on GitHub' })).toHaveAttribute('href', 'https://github.com/drevops/eddy');
  });

  test('draws the ripples as decoration', async () => {
    const { container } = await renderHome();
    const ripples = container.querySelectorAll('svg[viewBox="0 0 1000 1000"]');

    expect(ripples).toHaveLength(2);
    ripples.forEach((svg) => {
      expect(svg).toHaveAttribute('aria-hidden', 'true');
      expect(svg.querySelectorAll('circle')).toHaveLength(9);
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
