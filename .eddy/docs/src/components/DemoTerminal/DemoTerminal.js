import { useEffect, useId, useRef, useState } from 'react';
import { useBaseUrlUtils } from '@docusaurus/useBaseUrl';
import Icon from '@site/src/components/Icon';
import { prefersReducedMotion, useStillSvg } from '@site/src/animation';
import styles from './DemoTerminal.module.css';

// The size the demos are rendered at, which reserves their space before they
// load.
const WIDTH = 800;
const HEIGHT = 441;

// Shows 1 animated SVG demo at a time, chosen with tabs. Pausing applies to
// every demo, and the demos start paused for a reader who prefers reduced
// motion.
const DemoTerminal = ({ demos, label }) => {
  const id = useId();
  const tabs = useRef([]);
  const { withBaseUrl } = useBaseUrlUtils();
  const [active, setActive] = useState(0);
  const [paused, setPaused] = useState(false);
  const demo = demos[active];
  const src = withBaseUrl(demo.src);
  const stillSrc = useStillSvg(src);

  useEffect(() => {
    setPaused(prefersReducedMotion());
  }, []);

  const keyTargets = { ArrowLeft: active - 1, ArrowRight: active + 1, Home: 0, End: demos.length - 1 };

  const moveTo = (event) => {
    if (!Object.prototype.hasOwnProperty.call(keyTargets, event.key)) {
      return;
    }

    const next = (keyTargets[event.key] + demos.length) % demos.length;

    event.preventDefault();
    setActive(next);
    tabs.current[next].focus();
  };

  return (
    <figure className={styles.terminal}>
      <div className={styles.window}>
        <div className={styles.bar}>
          <span className={styles.dots} aria-hidden="true">
            <span />
            <span />
            <span />
          </span>
          <div className={styles.tabs} role="tablist" aria-label={label}>
            {demos.map((item, index) => (
              <button
                key={item.name}
                ref={(element) => {
                  tabs.current[index] = element;
                }}
                id={`${id}-tab-${index}`}
                type="button"
                role="tab"
                className={styles.tab}
                aria-selected={index === active}
                aria-controls={`${id}-panel`}
                tabIndex={index === active ? 0 : -1}
                onClick={() => setActive(index)}
                onKeyDown={moveTo}
              >
                {item.name}
              </button>
            ))}
          </div>
          {stillSrc && (
            <button type="button" className={styles.pause} onClick={() => setPaused(!paused)}>
              <Icon name={paused ? 'play' : 'pause'} className={styles.pauseIcon} />
              <span className={styles.pauseLabel}>{paused ? 'Play' : 'Pause'}</span>
              <span className="visually-hidden"> the demo</span>
            </button>
          )}
        </div>
        <div id={`${id}-panel`} className={styles.screen} role="tabpanel" aria-labelledby={`${id}-tab-${active}`} tabIndex={0}>
          <img src={paused && stillSrc ? stillSrc : src} alt={demo.alt} width={WIDTH} height={HEIGHT} />
        </div>
      </div>
      <figcaption className={styles.caption}>
        <Icon name="arrowRight" className={styles.captionIcon} />
        <span>{demo.caption}</span>
      </figcaption>
    </figure>
  );
};

export default DemoTerminal;
