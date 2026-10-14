// Stands in for the Docusaurus theme component, which needs the site context
// that a unit test does not have.
export default function Tabs({ children, groupId, queryString }) {
  return (
    <div data-testid="tabs" data-group-id={groupId} data-query-string={String(queryString)}>
      {children}
    </div>
  );
}
