import { useEffect, useState } from 'react';

const FREEZE_STYLE = '<style>*{animation-delay:-1000000s!important;animation-iteration-count:1!important;animation-fill-mode:forwards!important}</style>';

export const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// An <img> exposes no control over its SVG's animations, so the paused image
// is a copy with every animation finished on its last frame.
export const freezeSvg = (svg) => svg.replace(/<\/svg>\s*$/, `${FREEZE_STYLE}</svg>`);

export const isSvgUrl = (src) => /\.svg($|[?#])/i.test(String(src));

// Returns the URL of a still copy of an animated SVG, or null while the copy
// loads and for any other image. A copy is returned only for the 'src' it was
// made from, because the copy of the previous 'src' is revoked on a change.
export const useStillSvg = (src) => {
  const [still, setStill] = useState(null);

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
        setStill({ src, url });
      })
      .catch(() => {});

    return () => {
      cancelled = true;

      if (url) {
        URL.revokeObjectURL(url);
      }
    };
  }, [src]);

  return still?.src === src ? still.url : null;
};
