import { useEffect, useRef, useState } from 'react';
import clsx from 'clsx';
import Icon from '@site/src/components/Icon';
import styles from './CopyCommand.module.css';

const COPIED_MS = 2000;

// Each of these operators ends a command, so the word after it is a program.
const SEPARATORS = ['&&', '||', '|', ';'];

// The fallback for a missing or refused Clipboard API, such as on an HTTP
// origin.
const copyWithSelection = (text) => {
  const field = document.createElement('textarea');

  field.value = text;
  field.setAttribute('readonly', '');
  field.style.position = 'fixed';
  field.style.opacity = '0';
  document.body.append(field);
  field.select();

  try {
    return document.execCommand('copy');
  }
  catch {
    return false;
  }
  finally {
    field.remove();
  }
};

export const copyText = async (text) => {
  try {
    await navigator.clipboard.writeText(text);

    return true;
  }
  catch {
    return copyWithSelection(text);
  }
};

// Splits a shell command into words and the spaces between them, each word
// tagged as a program, a flag, an operator or an argument.
export const tokenize = (command) => {
  let atProgram = true;

  return command.split(/(\s+)/).filter((text) => text !== '').map((text) => {
    if (/^\s+$/.test(text)) {
      return { text, kind: 'space' };
    }

    if (SEPARATORS.includes(text) || /^[<>]+$/.test(text)) {
      atProgram = SEPARATORS.includes(text);

      return { text, kind: 'operator' };
    }

    if (atProgram) {
      atProgram = false;

      return { text, kind: 'program' };
    }

    return { text, kind: text.startsWith('-') ? 'flag' : 'argument' };
  });
};

// Offers a line break after each run of slashes in a path or a URL, so
// 'https://' never splits between its 2 slashes.
const breakAfterSlashes = (text) => text
  .split(/(\/+)/)
  .filter((part) => part !== '')
  .flatMap((part, index, parts) => (part.startsWith('/') && index < parts.length - 1 ? [part, <wbr key={index} />] : [part]));

const CopyCommand = ({ command, name, compact = false, className }) => {
  const [copied, setCopied] = useState(false);
  const timer = useRef(null);

  useEffect(() => () => clearTimeout(timer.current), []);

  const copy = async () => {
    if (!(await copyText(command))) {
      return;
    }

    setCopied(true);
    clearTimeout(timer.current);
    timer.current = setTimeout(() => setCopied(false), COPIED_MS);
  };

  return (
    <div className={clsx(styles.command, compact && styles.compact, className)}>
      <code className={styles.code}>
        <span className={styles.prompt} aria-hidden="true">$ </span>
        {tokenize(command).map((token, index) => (
          // A word can repeat, such as 'install.php', so the key is its position.
          <span key={index} className={styles[token.kind]}>
            {breakAfterSlashes(token.text)}
          </span>
        ))}
      </code>
      <button type="button" className={clsx(styles.button, copied && styles.copied)} onClick={copy}>
        <Icon name={copied ? 'check' : 'copy'} className={styles.icon} />
        <span className={compact ? 'visually-hidden' : undefined}>{copied ? 'Copied' : 'Copy'}</span>
        <span className="visually-hidden">{` the ${name}`}</span>
      </button>
      <span className="visually-hidden" role="status">{copied ? `Copied the ${name} to the clipboard.` : ''}</span>
    </div>
  );
};

export default CopyCommand;
