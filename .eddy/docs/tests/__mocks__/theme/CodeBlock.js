// Stands in for the Docusaurus theme component, which needs the site context
// that a unit test does not have.
export default function CodeBlock({ children, language }) {
  return <pre data-language={language}>{children}</pre>;
}
