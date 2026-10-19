import { act, cleanup, renderHook } from '@testing-library/react';
import { freezeSvg, isSvgUrl, prefersReducedMotion, useStillSvg } from '../../src/animation';

const ANIMATED_SVG = '<svg><style>@keyframes i{0%{opacity:0}100%{opacity:1}}</style></svg>';
const STATIC_SVG = '<svg><rect width="1" height="1"/></svg>';

describe('prefersReducedMotion', () => {
  afterEach(() => {
    delete window.matchMedia;
  });

  test.each([
    { name: 'asks for less motion', matches: true },
    { name: 'has no preference', matches: false },
  ])('returns $matches when the system $name', ({ matches }) => {
    window.matchMedia = jest.fn(() => ({ matches }));

    expect(prefersReducedMotion()).toBe(matches);
    expect(window.matchMedia).toHaveBeenCalledWith('(prefers-reduced-motion: reduce)');
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

describe('isSvgUrl', () => {
  test.each([
    { src: '/img/demo.svg', expected: true },
    { src: '/img/demo.SVG', expected: true },
    { src: '/img/demo.svg?v=1', expected: true },
    { src: '/img/demo.svg#frame', expected: true },
    { src: '/img/demo.svg.png', expected: false },
    { src: '/img/photo.png', expected: false },
    { src: undefined, expected: false },
  ])('returns $expected for $src', ({ src, expected }) => {
    expect(isSvgUrl(src)).toBe(expected);
  });
});

describe('useStillSvg', () => {
  beforeEach(() => {
    let copies = 0;

    global.fetch = jest.fn();
    URL.createObjectURL = jest.fn(() => {
      copies++;

      return `blob:still-${copies}`;
    });
    URL.revokeObjectURL = jest.fn();
  });

  afterEach(() => {
    // Unmounts first, so the hook releases its copy through the mocks.
    cleanup();
    delete global.fetch;
    delete URL.createObjectURL;
    delete URL.revokeObjectURL;
  });

  const respondWith = (body, ok = true) => global.fetch.mockResolvedValue({ ok, text: () => Promise.resolve(body) });

  // Lets the fetch and every state update after it run.
  const settle = () => act(() => new Promise((resolve) => {
    setTimeout(resolve, 0);
  }));

  const renderStill = (src) => renderHook((props) => useStillSvg(props.src), { initialProps: { src } });

  test('copies an animated SVG', async () => {
    respondWith(ANIMATED_SVG);

    const { result } = renderStill('/img/demo.svg');

    expect(result.current).toBeNull();

    await settle();

    expect(result.current).toBe('blob:still-1');
    expect(global.fetch).toHaveBeenCalledWith('/img/demo.svg');
    expect(URL.createObjectURL.mock.calls[0][0].type).toBe('image/svg+xml');
  });

  test('copies nothing that is not an SVG', async () => {
    const { result } = renderStill('/img/photo.png');

    await settle();

    expect(result.current).toBeNull();
    expect(global.fetch).not.toHaveBeenCalled();
  });

  test.each([
    { name: 'an SVG without animations', mock: () => respondWith(STATIC_SVG) },
    { name: 'an SVG that fails to load', mock: () => respondWith('Not found', false) },
    { name: 'an SVG that the network drops', mock: () => global.fetch.mockRejectedValue(new Error('offline')) },
  ])('copies nothing of $name', async ({ mock }) => {
    mock();

    const { result } = renderStill('/img/demo.svg');

    await settle();

    expect(result.current).toBeNull();
    expect(URL.createObjectURL).not.toHaveBeenCalled();
  });

  test('returns no copy of the previous SVG once the source changes', async () => {
    respondWith(ANIMATED_SVG);

    const { result, rerender } = renderStill('/img/install.svg');

    await settle();
    rerender({ src: '/img/build.svg' });

    expect(result.current).toBeNull();
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:still-1');

    await settle();

    expect(result.current).toBe('blob:still-2');
    expect(global.fetch).toHaveBeenLastCalledWith('/img/build.svg');
  });

  test('releases the copy once unmounted', async () => {
    respondWith(ANIMATED_SVG);

    const { unmount } = renderStill('/img/demo.svg');

    await settle();
    unmount();

    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:still-1');
  });

  test('makes no copy once unmounted before the SVG loads', async () => {
    respondWith(ANIMATED_SVG);

    const { unmount } = renderStill('/img/demo.svg');

    unmount();
    await settle();

    expect(URL.createObjectURL).not.toHaveBeenCalled();
    expect(URL.revokeObjectURL).not.toHaveBeenCalled();
  });
});
