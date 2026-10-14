import MDXComponents from '../../../src/theme/MDXComponents';
import PausableImage from '../../../src/components/PausableImage';
import ScrollableTable from '../../../src/components/ScrollableTable';

describe('MDXComponents', () => {
  test.each([
    { element: 'a', expected: 'a' },
    { element: 'img', expected: PausableImage },
    { element: 'table', expected: ScrollableTable },
  ])('renders Markdown $element elements', ({ element, expected }) => {
    expect(MDXComponents[element]).toBe(expected);
  });
});
