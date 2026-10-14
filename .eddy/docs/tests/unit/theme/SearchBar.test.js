import { render, screen, waitFor } from '@testing-library/react';
import SearchBar, { patchSearchMarkup } from '../../../src/theme/SearchBar';

describe('SearchBar', () => {
  afterEach(() => {
    jest.restoreAllMocks();
  });

  test('fills the gaps in the markup of the search plugin', () => {
    render(<SearchBar />);

    expect(screen.getByRole('combobox', { name: 'Search' })).toHaveAttribute('aria-controls', 'listbox-1');
    expect(screen.getByRole('option', { name: 'See all results' })).toHaveAttribute('href', '/search?q=port');
    expect(screen.getByRole('button', { name: 'Clear search' })).toBeInTheDocument();
  });

  test('fills the gaps in markup that a query draws later', async () => {
    render(<SearchBar />);

    const link = document.createElement('a');

    link.href = '/search?q=webserver';
    link.textContent = 'See all 31 results';
    screen.getByRole('listbox').append(link);

    await waitFor(() => expect(link).toHaveAttribute('role', 'option'));
  });

  test('links the combobox once the widget names its listbox', async () => {
    render(<SearchBar />);

    const input = document.createElement('input');

    input.setAttribute('role', 'combobox');
    screen.getByRole('listbox').before(input);
    input.setAttribute('aria-owns', 'listbox-2');

    await waitFor(() => expect(input).toHaveAttribute('aria-controls', 'listbox-2'));
  });

  test('stops watching the markup once removed', () => {
    const disconnect = jest.spyOn(MutationObserver.prototype, 'disconnect');
    const { unmount } = render(<SearchBar />);

    unmount();

    expect(disconnect).toHaveBeenCalledTimes(1);
  });
});

describe('patchSearchMarkup', () => {
  test.each([
    { name: 'keeps an existing aria-controls', html: '<input role="combobox" aria-owns="a" aria-controls="b">', selector: 'input', attribute: 'aria-controls', expected: 'b' },
    { name: 'skips a combobox without a listbox', html: '<input role="combobox">', selector: 'input', attribute: 'aria-controls', expected: null },
    { name: 'keeps the role of a link that has one', html: '<div role="listbox"><a href="/" role="button">x</a></div>', selector: 'a', attribute: 'role', expected: 'button' },
    { name: 'skips a link outside a listbox', html: '<a href="/">x</a>', selector: 'a', attribute: 'role', expected: null },
    { name: 'keeps an existing clear button label', html: '<button class="searchClearButton_qk4g" aria-label="Reset">✕</button>', selector: 'button', attribute: 'aria-label', expected: 'Reset' },
    { name: 'skips other buttons', html: '<button class="searchHint">K</button>', selector: 'button', attribute: 'aria-label', expected: null },
  ])('$name', ({ html, selector, attribute, expected }) => {
    const root = document.createElement('div');

    root.innerHTML = html;
    patchSearchMarkup(root);

    expect(root.querySelector(selector).getAttribute(attribute)).toBe(expected);
  });
});
