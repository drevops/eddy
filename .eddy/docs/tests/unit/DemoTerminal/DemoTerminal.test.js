import { act, cleanup, fireEvent, render, screen } from '@testing-library/react';
import DemoTerminal from '../../../src/components/DemoTerminal';

const ANIMATED_SVG = '<svg><style>@keyframes i{0%{opacity:0}100%{opacity:1}}</style></svg>';
const STATIC_SVG = '<svg><rect width="1" height="1"/></svg>';

const DEMOS = [
  { name: 'install', src: '/img/install.svg', alt: 'The installer', caption: 'Installs.' },
  { name: 'build', src: '/img/build.svg', alt: 'The build', caption: 'Builds.' },
  { name: 'test', src: '/img/test.svg', alt: 'The tests', caption: 'Tests.' },
];

describe('DemoTerminal', () => {
  let reducedMotion;

  beforeEach(() => {
    let copies = 0;

    reducedMotion = false;
    global.fetch = jest.fn();
    respondWith(STATIC_SVG);
    URL.createObjectURL = jest.fn(() => {
      copies++;

      return `blob:still-${copies}`;
    });
    URL.revokeObjectURL = jest.fn();
    window.matchMedia = jest.fn((query) => ({ matches: query === '(prefers-reduced-motion: reduce)' && reducedMotion }));
  });

  afterEach(() => {
    // Unmounts first, so the component releases its copy through the mocks.
    cleanup();
    delete global.fetch;
    delete URL.createObjectURL;
    delete URL.revokeObjectURL;
    delete window.matchMedia;
  });

  const respondWith = (svg) => global.fetch.mockImplementation(() => Promise.resolve({ ok: true, text: () => Promise.resolve(svg) }));

  // Lets the fetch and every state update after it run.
  const settle = () => act(() => new Promise((resolve) => {
    setTimeout(resolve, 0);
  }));

  const renderTerminal = async () => {
    const view = render(<DemoTerminal demos={DEMOS} label="Demos" />);

    await settle();

    return view;
  };

  const tab = (name) => screen.getByRole('tab', { name });

  const demo = () => screen.getByRole('tabpanel').querySelector('img');

  const expectSelected = (name) => {
    expect(tab(name)).toHaveAttribute('aria-selected', 'true');
    expect(tab(name)).toHaveAttribute('tabindex', '0');
    expect(screen.getByRole('tabpanel')).toHaveAttribute('aria-labelledby', tab(name).id);
    expect(screen.getAllByRole('tab').filter((item) => item.getAttribute('tabindex') === '0')).toHaveLength(1);
  };

  test('shows the first demo through the base URL', async () => {
    await renderTerminal();

    expect(screen.getByRole('tablist', { name: 'Demos' })).toBeInTheDocument();
    expect(screen.getAllByRole('tab').map((item) => item.textContent)).toEqual(['install', 'build', 'test']);
    expectSelected('install');
    expect(demo()).toHaveAttribute('src', '/base/img/install.svg');
    expect(demo()).toHaveAttribute('alt', 'The installer');
    expect(screen.getByRole('tabpanel')).toHaveAttribute('id', tab('build').getAttribute('aria-controls'));
    expect(screen.getByText('Installs.').closest('figcaption')).toBeInTheDocument();
  });

  test('keeps a caption that holds code in 1 element beside its icon', async () => {
    const demos = [{ ...DEMOS[0], caption: <><code>ahoy build</code> builds.</> }];

    render(<DemoTerminal demos={demos} label="Demos" />);
    await settle();

    const caption = screen.getByText('ahoy build').closest('figcaption');

    expect(Array.from(caption.children).map((child) => child.tagName.toLowerCase())).toEqual(['svg', 'span']);
    expect(caption.lastElementChild).toHaveTextContent('ahoy build builds.');
  });

  test('switches the demo on a click', async () => {
    await renderTerminal();

    fireEvent.click(tab('build'));

    expectSelected('build');
    expect(demo()).toHaveAttribute('src', '/base/img/build.svg');
    expect(screen.getByText('Builds.')).toBeInTheDocument();
  });

  test.each([
    { key: 'ArrowRight', from: 'install', expected: 'build' },
    { key: 'ArrowRight', from: 'test', expected: 'install' },
    { key: 'ArrowLeft', from: 'build', expected: 'install' },
    { key: 'ArrowLeft', from: 'install', expected: 'test' },
    { key: 'Home', from: 'test', expected: 'install' },
    { key: 'End', from: 'install', expected: 'test' },
  ])('moves from $from to $expected on $key', async ({ key, from, expected }) => {
    await renderTerminal();

    fireEvent.click(tab(from));
    tab(from).focus();

    expect(fireEvent.keyDown(tab(from), { key })).toBe(false);
    expectSelected(expected);
    expect(document.activeElement).toBe(tab(expected));
  });

  test.each(['Enter', 'Tab', 'a'])('leaves the %s key to the browser', async (key) => {
    await renderTerminal();

    expect(fireEvent.keyDown(tab('install'), { key })).toBe(true);
    expectSelected('install');
  });

  test('pauses on the still copy and plays again', async () => {
    respondWith(ANIMATED_SVG);

    await renderTerminal();

    fireEvent.click(screen.getByRole('button', { name: 'Pause the demo' }));

    expect(demo()).toHaveAttribute('src', 'blob:still-1');

    fireEvent.click(screen.getByRole('button', { name: 'Play the demo' }));

    expect(demo()).toHaveAttribute('src', '/base/img/install.svg');
  });

  test('starts paused for a reader who prefers reduced motion', async () => {
    reducedMotion = true;
    respondWith(ANIMATED_SVG);

    await renderTerminal();

    expect(screen.getByRole('button', { name: 'Play the demo' })).toBeInTheDocument();
    expect(demo()).toHaveAttribute('src', 'blob:still-1');
  });

  test('stays paused on the next demo, without showing the copy of the previous one', async () => {
    respondWith(ANIMATED_SVG);

    await renderTerminal();

    fireEvent.click(screen.getByRole('button', { name: 'Pause the demo' }));
    fireEvent.click(tab('build'));

    expect(demo()).toHaveAttribute('src', '/base/img/build.svg');
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:still-1');
    expect(screen.queryByRole('button', { name: /the demo/ })).not.toBeInTheDocument();

    await settle();

    expect(demo()).toHaveAttribute('src', 'blob:still-2');
    expect(screen.getByRole('button', { name: 'Play the demo' })).toBeInTheDocument();
  });

  test('offers no pause control for a demo without animations', async () => {
    await renderTerminal();

    expect(screen.queryByRole('button', { name: /the demo/ })).not.toBeInTheDocument();
    expect(demo()).toHaveAttribute('src', '/base/img/install.svg');
  });
});
