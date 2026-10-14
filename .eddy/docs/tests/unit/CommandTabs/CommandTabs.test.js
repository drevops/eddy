import { render, screen } from '@testing-library/react';
import CommandTabs from '../../../src/components/CommandTabs';

describe('CommandTabs', () => {
  test.each([
    {
      name: 'every wrapper',
      props: { make: 'make build', ahoy: 'ahoy build', none: 'vendor/bin/eddy-assemble' },
      expected: [
        ['make', 'Make', 'make build'],
        ['ahoy', 'Ahoy', 'ahoy build'],
        ['none', 'No wrapper', 'vendor/bin/eddy-assemble'],
      ],
    },
    {
      name: 'wrapper order, whatever the prop order',
      props: { none: 'vendor/bin/eddy-info', ahoy: 'ahoy info', make: 'make info' },
      expected: [
        ['make', 'Make', 'make info'],
        ['ahoy', 'Ahoy', 'ahoy info'],
        ['none', 'No wrapper', 'vendor/bin/eddy-info'],
      ],
    },
    {
      name: 'no tab for a wrapper without a command',
      props: { make: 'make lint', ahoy: 'ahoy lint' },
      expected: [
        ['make', 'Make', 'make lint'],
        ['ahoy', 'Ahoy', 'ahoy lint'],
      ],
    },
    {
      name: 'no tab for an empty command',
      props: { make: 'make lint', ahoy: '', none: ' \n ' },
      expected: [['make', 'Make', 'make lint']],
    },
    {
      name: 'blank lines around a command trimmed',
      props: { ahoy: '\nahoy start\nahoy provision\n' },
      expected: [['ahoy', 'Ahoy', 'ahoy start\nahoy provision']],
    },
  ])('renders $name', ({ props, expected }) => {
    render(<CommandTabs {...props} />);

    const tabs = screen.getAllByTestId('tab').map((tab) => [tab.dataset.value, tab.getAttribute('aria-label'), tab.textContent]);

    expect(tabs).toEqual(expected);
  });

  test('syncs the choice across the page and the URL', () => {
    render(<CommandTabs make="make build" />);

    const tabs = screen.getByTestId('tabs');

    expect(tabs).toHaveAttribute('data-group-id', 'command-wrapper');
    expect(tabs).toHaveAttribute('data-query-string', 'true');
  });

  test.each([
    { name: 'as Bash by default', props: {}, expected: 'bash' },
    { name: 'in the given language', props: { language: 'json' }, expected: 'json' },
  ])('highlights the commands $name', ({ props, expected }) => {
    render(<CommandTabs make="make build" {...props} />);

    expect(screen.getByText('make build')).toHaveAttribute('data-language', expected);
  });

  test.each([
    { name: 'no commands', props: {} },
    { name: 'only empty commands', props: { make: '', ahoy: '  ' } },
  ])('throws with $name', ({ props }) => {
    jest.spyOn(console, 'error').mockImplementation(() => {});

    expect(() => render(<CommandTabs {...props} />)).toThrow('CommandTabs needs a command for at least 1 of make, ahoy or none.');
  });
});
