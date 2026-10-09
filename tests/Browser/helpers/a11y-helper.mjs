import AxeBuilder from '@axe-core/playwright';

/**
 * Scan a Playwright page for WCAG 2.1 AA accessibility violations.
 * @param {import('@playwright/test').Page} page
 * @param {Array<string>} tags - WCAG tags to evaluate (e.g. ['wcag2a', 'wcag2aa', 'wcag21aa'])
 */
export async function runA11yScan(page, tags = ['wcag2a', 'wcag2aa', 'wcag21aa']) {
  const accessibilityScanResults = await new AxeBuilder({ page })
    .withTags(tags)
    .analyze();

  return {
    violations: accessibilityScanResults.violations.map(v => ({
      id: v.id,
      impact: v.impact,
      description: v.description,
      help: v.help,
      helpUrl: v.helpUrl,
      nodesCount: v.nodes.length,
      targets: v.nodes.map(n => n.target.join(' ')),
    })),
    passesCount: accessibilityScanResults.passes.length,
    incompleteCount: accessibilityScanResults.incomplete.length,
  };
}
