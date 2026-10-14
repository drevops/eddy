// Stands in for the Docusaurus theme component, which needs the site context
// that a unit test does not have.
export default function Img(props) {
  return <img {...props} />;
}
