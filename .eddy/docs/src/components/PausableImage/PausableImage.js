import { useEffect, useState } from 'react';
import Img from '@theme/MDXComponents/Img';
import styles from './PausableImage.module.css';

const FREEZE_STYLE = '<style>*{animation-delay:-1000000s!important;animation-iteration-count:1!important;animation-fill-mode:forwards!important}</style>';

// An <img> exposes no control over the animations of its SVG, so the paused
// image is a copy with every animation finished on its last frame.
export const freezeSvg = (svg) => svg.replace(/<\/svg>\s*$/, `${FREEZE_STYLE}</svg>`);

const isSvgUrl = (src) => /\.svg($|[?#])/i.test(String(src));

const PausableImage = (props) => {
  const { src } = props;
  const [stillSrc, setStillSrc] = useState(null);
  const [paused, setPaused] = useState(false);

  useEffect(() => {
    if (!isSvgUrl(src)) {
      return undefined;
    }

    let cancelled = false;
    let url = null;

    fetch(src)
      .then((response) => (response.ok ? response.text() : ''))
      .then((svg) => {
        if (cancelled || !svg.includes('@keyframes')) {
          return;
        }

        url = URL.createObjectURL(new Blob([freezeSvg(svg)], { type: 'image/svg+xml' }));
        setStillSrc(url);
        setPaused(window.matchMedia('(prefers-reduced-motion: reduce)').matches);
      })
      .catch(() => {});

    return () => {
      cancelled = true;

      if (url) {
        URL.revokeObjectURL(url);
      }
    };
  }, [src]);

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
