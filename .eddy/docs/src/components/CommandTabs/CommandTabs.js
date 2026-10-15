import Tabs from '@theme/Tabs';
import TabItem from '@theme/TabItem';
import CodeBlock from '@theme/CodeBlock';

// The order of the tabs on every page, whatever the order of the props.
const WRAPPERS = [
  { value: 'make', label: 'Make' },
  { value: 'ahoy', label: 'Ahoy' },
  { value: 'none', label: 'No wrapper' },
];

// The shared 'groupId' switches every tab set on the site at once, and the
// query string carries the choice in a shared link.
const CommandTabs = ({ language = 'bash', ...commands }) => {
  const items = WRAPPERS
    .map((wrapper) => ({ ...wrapper, command: (commands[wrapper.value] || '').trim() }))
    .filter((wrapper) => wrapper.command !== '');

  if (items.length === 0) {
    throw new Error('CommandTabs needs a command for at least 1 of make, ahoy or none.');
  }

  return (
    <Tabs groupId="command-wrapper" queryString>
      {items.map(({ value, label, command }) => (
        <TabItem key={value} value={value} label={label}>
          <CodeBlock language={language}>{command}</CodeBlock>
        </TabItem>
      ))}
    </Tabs>
  );
};

export default CommandTabs;
