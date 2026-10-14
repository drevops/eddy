import React, { useEffect, useRef, useState } from 'react';

// A table wider than the page scrolls sideways, and the keyboard can only
// scroll an element that takes focus.
const ScrollableTable = (props) => {
  const ref = useRef(null);
  const [scrollable, setScrollable] = useState(false);

  useEffect(() => {
    const table = ref.current;
    const update = () => setScrollable(table.scrollWidth > table.clientWidth);
    const observer = new ResizeObserver(update);

    update();
    observer.observe(table);

    return () => observer.disconnect();
  }, []);

  return <table {...props} ref={ref} tabIndex={scrollable ? 0 : undefined} />;
};

export default ScrollableTable;
