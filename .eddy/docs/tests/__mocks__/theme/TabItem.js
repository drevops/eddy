// Stands in for the Docusaurus theme component, which needs the site context
// that a unit test does not have.
const TabItem = ({ children, label, value }) => {
  return (
    <section data-testid="tab" data-value={value} aria-label={label}>
      {children}
    </section>
  );
};

export default TabItem;
