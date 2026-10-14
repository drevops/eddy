import MDXComponents from '@theme-original/MDXComponents';
import PausableImage from '@site/src/components/PausableImage';
import ScrollableTable from '@site/src/components/ScrollableTable';

export default {
  ...MDXComponents,
  img: PausableImage,
  table: ScrollableTable,
};
