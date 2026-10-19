import { fireEvent, render, screen, within } from '@testing-library/react';
import ToolTicker from '../../../src/components/ToolTicker';

const ROWS = [
  ['PHPUnit', 'Jest', 'PHPStan'],
  ['Composer', 'Drush'],
];

describe('ToolTicker', () => {
  test('lists every tool once to assistive technology', () => {
    render(<ToolTicker rows={ROWS} label="Tools" />);

    const group = screen.getByRole('group', { name: 'Tools' });

    expect(within(group).getAllByRole('list')).toHaveLength(2);
    expect(within(group).getAllByRole('listitem').map((item) => item.textContent)).toEqual(ROWS.flat());
  });

  test('draws each row twice for a seamless loop', () => {
    render(<ToolTicker rows={ROWS} label="Tools" />);

    const lists = screen.getAllByRole('list', { hidden: true });

    expect(lists.map((list) => list.getAttribute('aria-hidden'))).toEqual([null, 'true', null, 'true']);
    expect(lists.map((list) => list.textContent)).toEqual(['PHPUnitJestPHPStan', 'PHPUnitJestPHPStan', 'ComposerDrush', 'ComposerDrush']);
  });

  test('pauses the loop and plays it again', () => {
    render(<ToolTicker rows={ROWS} label="Tools" />);

    const group = screen.getByRole('group', { name: 'Tools' });

    expect(group).not.toHaveClass('paused');

    fireEvent.click(screen.getByRole('button', { name: 'Pause the tool list' }));

    expect(group).toHaveClass('paused');

    fireEvent.click(screen.getByRole('button', { name: 'Play the tool list' }));

    expect(group).not.toHaveClass('paused');
  });
});
