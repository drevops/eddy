// Stands in for the Docusaurus theme component, which needs the site context
// that a unit test does not have.
const CodeBlock = ({ children, language }) => {
  return <pre data-language={language}>{children}</pre>;
};

export default CodeBlock;
