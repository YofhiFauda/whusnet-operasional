/**
 * Standard Viewport Presets for UI/UX Responsiveness Testing
 */
export const VIEWPORT_MATRIX = {
  desktop_ultrawide: { width: 1920, height: 1080, label: 'Desktop 1080p (1920x1080)' },
  desktop_hd: { width: 1440, height: 900, label: 'MacBook/Desktop (1440x900)' },
  laptop: { width: 1280, height: 800, label: 'Standard Laptop (1280x800)' },
  tablet_landscape: { width: 1024, height: 768, label: 'iPad Landscape (1024x768)' },
  tablet_portrait: { width: 768, height: 1024, label: 'iPad Portrait (768x1024)' },
  mobile_large: { width: 414, height: 896, label: 'iPhone Plus/Max (414x896)' },
  mobile_standard: { width: 375, height: 812, label: 'iPhone Standard (375x812)' },
  mobile_compact: { width: 360, height: 740, label: 'Android Compact (360x740)' },
};

/**
 * Capture full-page responsive screenshots across standard break-points.
 * @param {import('@playwright/test').Page} page
 * @param {string} routeName
 * @param {string} outputDir
 */
export async function captureResponsiveSnapshots(page, routeName, outputDir = 'docs/audits/reports/artifacts') {
  const screenshots = {};
  for (const [key, vp] of Object.entries(VIEWPORT_MATRIX)) {
    await page.setViewportSize({ width: vp.width, height: vp.height });
    await page.waitForTimeout(200); // Allow responsive CSS transitions
    const filePath = `${outputDir}/${routeName}_${key}.png`;
    await page.screenshot({ path: filePath, fullPage: true });
    screenshots[key] = filePath;
  }
  return screenshots;
}
