import { act, cleanup, fireEvent, render, screen } from '@testing-library/react';
import CopyCommand, { copyText, tokenize } from '../../../src/components/CopyCommand';

const INSTALL = 'curl -fsSL https://eddy.drevops.com/install > install.php && php install.php';

describe('tokenize', () => {
  const kinds = (command) => tokenize(command).filter((token) => token.kind !== 'space').map(({ text, kind }) => `${kind}:${text}`);

  test.each([
    {
      name: 'a program and its argument',
      command: 'make build',
      expected: ['program:make', 'argument:build'],
    },
    {
      name: 'a redirect, whose target is an argument',
      command: 'ls -la > out.txt',
      expected: ['program:ls', 'flag:-la', 'operator:>', 'argument:out.txt'],
    },
    {
      name: 'commands joined by operators',
      command: 'a || b | c ; d',
      expected: ['program:a', 'operator:||', 'program:b', 'operator:|', 'program:c', 'operator:;', 'program:d'],
    },
    {
      name: 'the install command',
      command: INSTALL,
      expected: ['program:curl', 'flag:-fsSL', 'argument:https://eddy.drevops.com/install', 'operator:>', 'argument:install.php', 'operator:&&', 'program:php', 'argument:install.php'],
    },
  ])('tags $name', ({ command, expected }) => {
    expect(kinds(command)).toEqual(expected);
  });

  test('keeps the spaces between the words', () => {
    const tokens = tokenize('make  build');

    expect(tokens.map((token) => token.text).join('')).toBe('make  build');
    expect(tokens[1]).toEqual({ text: '  ', kind: 'space' });
  });
});

describe('copyText', () => {
  let field;

  beforeEach(() => {
    const create = document.createElement.bind(document);

    jest.spyOn(document, 'createElement').mockImplementation((tag) => {
      field = create(tag);

      return field;
    });
  });

  afterEach(() => {
    jest.restoreAllMocks();
    delete navigator.clipboard;
    delete document.execCommand;
  });

  test('writes through the Clipboard API', async () => {
    navigator.clipboard = { writeText: jest.fn(() => Promise.resolve()) };

    await expect(copyText('make build')).resolves.toBe(true);
    expect(navigator.clipboard.writeText).toHaveBeenCalledWith('make build');
  });

  test.each([
    { name: 'the Clipboard API is missing', clipboard: undefined },
    { name: 'the Clipboard API refuses', clipboard: { writeText: () => Promise.reject(new Error('Denied')) } },
  ])('copies a selection when $name', async ({ clipboard }) => {
    navigator.clipboard = clipboard;
    document.execCommand = jest.fn(() => {
      expect(field.value).toBe('make build');
      expect([field.selectionStart, field.selectionEnd]).toEqual([0, 'make build'.length]);

      return true;
    });

    await expect(copyText('make build')).resolves.toBe(true);
    expect(document.execCommand).toHaveBeenCalledWith('copy');
    expect(field.isConnected).toBe(false);
  });

  test.each([
    { name: 'refuses', execCommand: () => false },
    { name: 'throws', execCommand: () => { throw new Error('Unsupported'); } },
  ])('reports failure when the selection copy $name', async ({ execCommand }) => {
    document.execCommand = jest.fn(execCommand);

    await expect(copyText('make build')).resolves.toBe(false);
    expect(field.isConnected).toBe(false);
  });
});

describe('CopyCommand', () => {
  beforeEach(() => {
    jest.useFakeTimers();
    navigator.clipboard = { writeText: jest.fn(() => Promise.resolve()) };
  });

  afterEach(() => {
    // Unmounts first, so the component clears its timer under the fake timers.
    cleanup();
    jest.useRealTimers();
    delete navigator.clipboard;
    delete document.execCommand;
  });

  const copy = async (name) => {
    fireEvent.click(screen.getByRole('button', { name }));

    // Lets the clipboard promise settle.
    await act(async () => {});
  };

  test('shows the command after a prompt that is not read out', () => {
    const { container } = render(<CopyCommand command={INSTALL} name="install command" />);
    const code = container.querySelector('code');

    expect(code.textContent).toBe(`$ ${INSTALL}`);
    expect(code.querySelector('[aria-hidden="true"]').textContent).toBe('$ ');
  });

  test.each([
    { path: 'https://eddy.drevops.com/install', expected: 'https://<wbr>eddy.drevops.com/<wbr>install' },
    { path: '/var/www', expected: '/<wbr>var/<wbr>www' },
    { path: 'vendor/bin/', expected: 'vendor/<wbr>bin/' },
    { path: 'install.php', expected: 'install.php' },
  ])('offers a line break after each run of slashes in $path', ({ path, expected }) => {
    const { container } = render(<CopyCommand command={`ls ${path}`} name="command" />);
    const argument = Array.from(container.querySelectorAll('code span')).find((span) => span.textContent === path);

    expect(argument.innerHTML).toBe(expected);
  });

  test('copies the command and says so until the time runs out', async () => {
    render(<CopyCommand command={INSTALL} name="install command" />);

    await copy('Copy the install command');

    expect(navigator.clipboard.writeText).toHaveBeenCalledWith(INSTALL);
    expect(screen.getByRole('button', { name: 'Copied the install command' })).toHaveClass('copied');
    expect(screen.getByRole('status')).toHaveTextContent('Copied the install command to the clipboard.');

    act(() => {
      jest.advanceTimersByTime(1999);
    });

    expect(screen.getByRole('button', { name: 'Copied the install command' })).toBeInTheDocument();

    act(() => {
      jest.advanceTimersByTime(1);
    });

    expect(screen.getByRole('button', { name: 'Copy the install command' })).not.toHaveClass('copied');
    expect(screen.getByRole('status')).toHaveTextContent('');
  });

  test('restarts the time on a second copy', async () => {
    render(<CopyCommand command="make build" name="build command" />);

    await copy('Copy the build command');

    act(() => {
      jest.advanceTimersByTime(1500);
    });

    await copy('Copied the build command');

    act(() => {
      jest.advanceTimersByTime(1500);
    });

    expect(screen.getByRole('button', { name: 'Copied the build command' })).toBeInTheDocument();
  });

  test('claims nothing when the copy fails', async () => {
    navigator.clipboard = { writeText: () => Promise.reject(new Error('Denied')) };
    document.execCommand = jest.fn(() => false);

    render(<CopyCommand command="make build" name="build command" />);

    await copy('Copy the build command');

    expect(screen.getByRole('button', { name: 'Copy the build command' })).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('');
  });

  test('takes the class it is given', () => {
    const { container } = render(<CopyCommand command="make build" name="build command" className="extra" />);

    expect(container.firstChild).toHaveClass('command', 'extra');
  });
});
