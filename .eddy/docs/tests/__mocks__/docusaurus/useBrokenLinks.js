// Stands in for the Docusaurus hook, which needs the build context that a unit
// test does not have. Every call returns the same collector, so a test can read
// what a component collected.
const collector = { collectAnchor: jest.fn(), collectLink: jest.fn() };

const useBrokenLinks = () => collector;

export default useBrokenLinks;
