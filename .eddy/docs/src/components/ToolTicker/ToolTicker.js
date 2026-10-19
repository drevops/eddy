import { useState } from 'react';
import clsx from 'clsx';
import Icon from '@site/src/components/Icon';
import styles from './ToolTicker.module.css';

const Chips = ({ tools, hidden = false }) => (
  <ul className={styles.list} aria-hidden={hidden || undefined}>
    {tools.map((tool) => (
      <li key={tool} className={styles.chip}>
        {tool}
      </li>
    ))}
  </ul>
);

// Scrolls rows of tool names sideways in a loop. Each row holds its list
// twice, so the loop has no seam, and assistive technology reads it once.
const ToolTicker = ({ rows, label }) => {
  const [paused, setPaused] = useState(false);

  return (
    <div className={clsx(styles.ticker, paused && styles.paused)} role="group" aria-label={label}>
      <div className={styles.rows}>
        {rows.map((tools) => (
          <div key={tools.join()} className={styles.row}>
            <Chips tools={tools} />
            <Chips tools={tools} hidden />
          </div>
        ))}
      </div>
      <button type="button" className={styles.toggle} onClick={() => setPaused(!paused)}>
        <Icon name={paused ? 'play' : 'pause'} className={styles.toggleIcon} />
        <span className="visually-hidden">{paused ? 'Play the tool list' : 'Pause the tool list'}</span>
      </button>
    </div>
  );
};

export default ToolTicker;
