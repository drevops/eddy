import React, { useEffect, useRef } from 'react';
import SearchBar from '@theme-original/SearchBar';

// The search plugin's autocomplete widget follows the ARIA 1.1 combobox
// pattern, puts its "See all results" link straight inside the listbox and
// names its clear button "✕". It redraws on every query, so the gaps are
// filled again after each change.
export const patchSearchMarkup = (root) => {
  root.querySelectorAll('[role="combobox"][aria-owns]:not([aria-controls])').forEach((input) => {
    input.setAttribute('aria-controls', input.getAttribute('aria-owns'));
  });

  root.querySelectorAll('[role="listbox"] a[href]:not([role])').forEach((link) => {
    link.setAttribute('role', 'option');
  });

  root.querySelectorAll('[class*="searchClearButton"]:not([aria-label])').forEach((button) => {
    button.setAttribute('aria-label', 'Clear search');
  });
};

const SearchBarWrapper = (props) => {
  const ref = useRef(null);

  useEffect(() => {
    const root = ref.current;
    const observer = new MutationObserver(() => patchSearchMarkup(root));

    patchSearchMarkup(root);
    observer.observe(root, { childList: true, subtree: true, attributes: true, attributeFilter: ['aria-owns'] });

    return () => observer.disconnect();
  }, []);

  return (
    <div ref={ref} style={{ display: 'contents' }}>
      <SearchBar {...props} />
    </div>
  );
};

export default SearchBarWrapper;
