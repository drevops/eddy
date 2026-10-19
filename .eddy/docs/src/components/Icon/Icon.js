// Line icons on a 24px grid, stroked in the color of the surrounding text.
const ICONS = {
  arrowRight: <path d="M5 12h14M13 6l6 6-6 6" />,
  arrowUpRight: <path d="M7 17 17 7M9 7h8v8" />,
  check: <path d="M20 6 9 17l-5-5" />,
  ci: (
    <>
      <path d="M21 12a9 9 0 1 1-2.64-6.36" />
      <path d="M21 4v5h-5" />
      <path d="m9 12 2 2 4-4" />
    </>
  ),
  code: <path d="m8 8-4 4 4 4M16 8l4 4-4 4M13.5 6l-3 12" />,
  copy: (
    <>
      <rect x="9" y="9" width="11" height="11" rx="2" />
      <path d="M5 15V5a2 2 0 0 1 2-2h10" />
    </>
  ),
  download: (
    <>
      <path d="M12 3v11m0 0-4-4m4 4 4-4" />
      <path d="M4 16v2.5A2.5 2.5 0 0 0 6.5 21h11a2.5 2.5 0 0 0 2.5-2.5V16" />
    </>
  ),
  file: (
    <>
      <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" />
      <path d="M14 3v5h5M9 13h6M9 17h6" />
    </>
  ),
  flask: (
    <>
      <path d="M9.5 3h5M10.5 3v6.2L5 18.6A1.6 1.6 0 0 0 6.4 21h11.2a1.6 1.6 0 0 0 1.4-2.4L13.5 9.2V3" />
      <path d="M7.4 15h9.2" />
    </>
  ),
  github: <path fill="currentColor" stroke="none" d="M12 2C6.48 2 2 6.58 2 12.25c0 4.53 2.87 8.37 6.84 9.73.5.1.68-.22.68-.49l-.01-1.9c-2.78.62-3.37-1.2-3.37-1.2-.46-1.18-1.11-1.49-1.11-1.49-.91-.64.07-.62.07-.62 1 .07 1.53 1.06 1.53 1.06.89 1.56 2.34 1.11 2.91.85.09-.66.35-1.11.63-1.37-2.22-.26-4.55-1.14-4.55-5.07 0-1.12.39-2.03 1.03-2.75-.1-.26-.45-1.3.1-2.71 0 0 .84-.28 2.75 1.05a9.4 9.4 0 0 1 5 0c1.91-1.33 2.75-1.05 2.75-1.05.55 1.41.2 2.45.1 2.71.64.72 1.03 1.63 1.03 2.75 0 3.94-2.34 4.81-4.57 5.06.36.32.68.94.68 1.9l-.01 2.82c0 .27.18.59.69.49A10.26 10.26 0 0 0 22 12.25C22 6.58 17.52 2 12 2z" />,
  info: (
    <>
      <circle cx="12" cy="12" r="9" />
      <path d="M12 11v5M12 8h.01" />
    </>
  ),
  laptop: (
    <>
      <rect x="4" y="4.5" width="16" height="11" rx="1.5" />
      <path d="M2.5 19.5h19" />
    </>
  ),
  layers: (
    <>
      <path d="M12 3 21 8l-9 5-9-5 9-5z" />
      <path d="m3 13 9 5 9-5" />
      <path d="m3 17.5 9 5 9-5" />
    </>
  ),
  merge: (
    <>
      <circle cx="18" cy="18" r="3" />
      <circle cx="6" cy="6" r="3" />
      <path d="M6 21V9a9 9 0 0 0 9 9" />
    </>
  ),
  package: (
    <>
      <path d="M12 2.5 20.5 7v10L12 21.5 3.5 17V7L12 2.5z" />
      <path d="M3.7 7.2 12 12l8.3-4.8M12 12v9.3" />
    </>
  ),
  pause: <path d="M9 5v14M15 5v14" />,
  play: <path fill="currentColor" d="M7.5 5.2v13.6a.6.6 0 0 0 .9.5l10.8-6.8a.6.6 0 0 0 0-1L8.4 4.7a.6.6 0 0 0-.9.5z" />,
  refresh: (
    <>
      <path d="M3.5 12a8.5 8.5 0 0 1 14.6-5.9l2.4 2.4" />
      <path d="M20.5 3.5v5h-5" />
      <path d="M20.5 12a8.5 8.5 0 0 1-14.6 5.9l-2.4-2.4" />
      <path d="M3.5 20.5v-5h5" />
    </>
  ),
  repeat: (
    <>
      <path d="m17 2.5 3.5 3.5-3.5 3.5" />
      <path d="M3.5 11.5v-1A4.5 4.5 0 0 1 8 6h12.5" />
      <path d="m7 21.5-3.5-3.5 3.5-3.5" />
      <path d="M20.5 12.5v1A4.5 4.5 0 0 1 16 18H3.5" />
    </>
  ),
  send: (
    <>
      <path d="M21.5 2.5 10.5 13.5" />
      <path d="m21.5 2.5-7 19-4-8-8-4z" />
    </>
  ),
  shield: (
    <>
      <path d="M12 3 5 6v5.5c0 4.2 3 7 7 9 4-2 7-4.8 7-9V6l-7-3z" />
      <path d="m9 12 2 2 4-4.2" />
    </>
  ),
  sparkles: (
    <>
      <path d="m12 3 1.9 4.7L18.5 9l-4.6 1.3L12 15l-1.9-4.7L5.5 9l4.6-1.3L12 3z" />
      <path d="M19 14.5l.7 1.8 1.8.7-1.8.7-.7 1.8-.7-1.8-1.8-.7 1.8-.7.7-1.8z" />
    </>
  ),
  terminal: (
    <>
      <rect x="3" y="4" width="18" height="16" rx="2" />
      <path d="m7.5 9.5 3 2.5-3 2.5M13 15h3.5" />
    </>
  ),
  users: (
    <>
      <circle cx="9" cy="8" r="3.2" />
      <path d="M3.5 20a5.5 5.5 0 0 1 11 0" />
      <path d="M16 5.2a3.2 3.2 0 0 1 0 5.6M20.5 20a5.5 5.5 0 0 0-3.8-5.2" />
    </>
  ),
  wand: (
    <>
      <path d="M4.5 19.5 13.5 10.5" />
      <path d="M17 3.4l1 2.4 2.4 1-2.4 1-1 2.4-1-2.4-2.4-1 2.4-1 1-2.4z" />
      <path d="M7 6l.5 1.2 1.2.5-1.2.5L7 9.4l-.5-1.2L5.3 7.7l1.2-.5L7 6z" />
    </>
  ),
  zap: <path d="M13 2 4.5 13.2H11l-1 8.8 9.5-11.2H13V2z" />,
};

const Icon = ({ name, className }) => {
  if (!Object.hasOwn(ICONS, name)) {
    throw new Error(`Icon "${name}" doesn't exist.`);
  }

  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.85" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false">
      {ICONS[name]}
    </svg>
  );
};

export const ICON_NAMES = Object.keys(ICONS);

export default Icon;
