import { useEffect, useState } from 'react';
import Img from '@theme/MDXComponents/Img';
import { isSvgUrl, prefersReducedMotion, useStillSvg } from '@site/src/animation';
import styles from './PausableImage.module.css';

const PausableImage = (props) => {
  const { src } = props;
  const stillSrc = useStillSvg(src);
  const [paused, setPaused] = useState(false);

  // An image can only start paused once its still copy exists.
  useEffect(() => {
    if (stillSrc) {
      setPaused(prefersReducedMotion());
    }
  }, [stillSrc]);

  if (!isSvgUrl(src)) {
    return <Img {...props} />;
  }

  // The wrapper renders before the SVG loads, so the image isn't remounted
  // and restarted when the button appears.
  return (
    <span className={styles.pausableImage}>
      <Img {...props} src={paused && stillSrc ? stillSrc : src} />
      {stillSrc && (
        <button type="button" className="button button--secondary button--sm" onClick={() => setPaused(!paused)}>
          {paused ? 'Play animation' : 'Pause animation'}
        </button>
      )}
    </span>
  );
};

export default PausableImage;
