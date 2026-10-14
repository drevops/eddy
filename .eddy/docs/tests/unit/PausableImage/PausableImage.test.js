import { act, cleanup, fireEvent, render, screen } from '@testing-library/react';
import PausableImage, { freezeSvg } from '../../../src/components/PausableImage';

const ANIMATED_SVG = '<svg><style>@keyframes i{0%{opacity:0}100%{opacity:1}}</style></svg>';
const STATIC_SVG = '<svg><rect width="1" height="1"/></svg>';

describe('PausableImage', () => {
  let reducedMotion;

  beforeEach(() => {
    reducedMotion = false;
    global.fetch = jest.fn();
    URL.createObjectURL = jest.fn(() => 'blob:still');
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

  const respondWith = (body, ok = true) => global.fetch.mockResolvedValue({ ok, text: () => Promise.resolve(body) });

  // Lets the fetch and every state update after it run.
  const settle = () => act(() => new Promise((resolve) => {
    setTimeout(resolve, 0);
  }));

  test('renders an image that is not an SVG as it is', async () => {
    render(<PausableImage src="/img/photo.png" alt="Photo" />);
    await settle();

    expect(screen.getByRole('img', { name: 'Photo' })).toHaveAttribute('src', '/img/photo.png');
    expect(global.fetch).not.toHaveBeenCalled();
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  test.each([
    { name: 'an SVG without animations', mock: () => respondWith(STATIC_SVG) },
    { name: 'an SVG that fails to load', mock: () => respondWith('Not found', false) },
    { name: 'an SVG that the network drops', mock: () => global.fetch.mockRejectedValue(new Error('offline')) },
  ])('adds no control to $name', async ({ mock }) => {
    mock();

    render(<PausableImage src="/assets/images/demo.svg" alt="Demo" />);
    await settle();

    expect(global.fetch).toHaveBeenCalledWith('/assets/images/demo.svg');
    expect(screen.getByRole('img', { name: 'Demo' })).toHaveAttribute('src', '/assets/images/demo.svg');
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
    expect(URL.createObjectURL).not.toHaveBeenCalled();
  });

  test('pauses an animated SVG on a still copy and plays it again', async () => {
    respondWith(ANIMATED_SVG);

    render(<PausableImage src="/assets/images/demo.svg?v=1" alt="Demo" />);

    const image = screen.getByRole('img', { name: 'Demo' });

    expect(image.parentElement).toHaveClass('pausableImage');

    fireEvent.click(await screen.findByRole('button', { name: 'Pause animation' }));

    expect(image).toHaveAttribute('src', 'blob:still');
    expect(URL.createObjectURL.mock.calls[0][0].type).toBe('image/svg+xml');

    fireEvent.click(screen.getByRole('button', { name: 'Play animation' }));

    expect(image).toHaveAttribute('src', '/assets/images/demo.svg?v=1');
  });

  test('starts paused when the reader prefers reduced motion', async () => {
    reducedMotion = true;
    respondWith(ANIMATED_SVG);

    render(<PausableImage src="/assets/images/demo.svg" alt="Demo" />);

    expect(await screen.findByRole('button', { name: 'Play animation' })).toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'Demo' })).toHaveAttribute('src', 'blob:still');
  });

  test('releases the still copy once removed', async () => {
    respondWith(ANIMATED_SVG);

    const { unmount } = render(<PausableImage src="/assets/images/demo.svg" alt="Demo" />);

    await screen.findByRole('button', { name: 'Pause animation' });
    unmount();

    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:still');
  });

  test('makes no still copy once removed before the SVG loads', async () => {
    respondWith(ANIMATED_SVG);

    const { unmount } = render(<PausableImage src="/assets/images/demo.svg" alt="Demo" />);

    unmount();
    await settle();

    expect(URL.createObjectURL).not.toHaveBeenCalled();
    expect(URL.revokeObjectURL).not.toHaveBeenCalled();
  });
});

describe('freezeSvg', () => {
  test.each([
    { name: 'an SVG', svg: '<svg><g/></svg>', before: '<svg><g/>' },
    { name: 'an SVG with a trailing newline', svg: '<svg><g/></svg>\n', before: '<svg><g/>' },
    { name: 'a nested SVG', svg: '<svg><svg><g/></svg></svg>', before: '<svg><svg><g/></svg>' },
  ])('finishes every animation of $name on its last frame', ({ svg, before }) => {
    const frozen = freezeSvg(svg);

    expect(frozen.startsWith(`${before}<style>`)).toBe(true);
    expect(frozen.endsWith('</style></svg>')).toBe(true);
    expect(frozen).toContain('animation-iteration-count:1!important');
    expect(frozen).toContain('animation-fill-mode:forwards!important');
  });
});
