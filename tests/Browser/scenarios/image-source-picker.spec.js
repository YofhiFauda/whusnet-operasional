import { test, expect } from '@playwright/test';

test.use({ hasTouch: true, isMobile: true, viewport: { width: 390, height: 844 } });

test('existing photo upload opens a source picker and camera uses the same form field', async ({ page }) => {
  await page.setContent(`
    <form>
      <input id="house_photo" name="house_photo" type="file" accept="image/*" hidden onchange="window.photoChanges = (window.photoChanges || 0) + 1">
      <label for="house_photo">Upload Foto</label>
    </form>
  `);
  await page.addScriptTag({ path: 'resources/js/image-source-picker.js' });

  await expect(page.getByRole('dialog', { name: 'Pilih sumber foto' })).toHaveCount(0);
  await page.getByText('Upload Foto').click();
  await expect(page.getByRole('button', { name: 'Ambil Foto' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Pilih dari Galeri' })).toBeVisible();

  await page.evaluate(() => {
    window.photoClickState = [];
    document.querySelector('#house_photo').addEventListener('click', (event) => {
      window.photoClickState.push({
        capture: event.currentTarget.getAttribute('capture'),
        accept: event.currentTarget.accept,
      });
      event.preventDefault();
    });
  });

  await page.getByRole('button', { name: 'Ambil Foto' }).click();
  expect(await page.evaluate(() => window.photoClickState)).toEqual([
    { capture: 'environment', accept: 'image/*' },
  ]);
  await expect(page.locator('#house_photo')).toHaveAttribute('name', 'house_photo');

  await page.locator('#house_photo').setInputFiles({ name: 'house.jpg', mimeType: 'image/jpeg', buffer: Buffer.from('photo') });
  expect(await page.evaluate(() => window.photoChanges)).toBe(1);
  expect(await page.evaluate(() => new FormData(document.querySelector('form')).get('house_photo').name)).toBe('house.jpg');

  await page.getByText('Upload Foto').click();
  await page.evaluate(() => { window.photoClickState = []; });
  await page.getByRole('button', { name: 'Pilih dari Galeri' }).click();
  expect(await page.evaluate(() => window.photoClickState)).toEqual([
    { capture: null, accept: 'image/*' },
  ]);
});

test('camera limits mixed image and PDF inputs to images, then restores PDF selection', async ({ page }) => {
  await page.setContent('<input id="proof" name="proof" type="file" accept="image/*,application/pdf" required>');
  await page.addScriptTag({ path: 'resources/js/image-source-picker.js' });

  await page.evaluate(() => {
    window.proofClicks = [];
    document.querySelector('#proof').addEventListener('click', (event) => {
      window.proofClicks.push({
        capture: event.currentTarget.getAttribute('capture'),
        accept: event.currentTarget.accept,
      });
      event.preventDefault();
    });
  });

  await page.locator('#proof').click();
  await page.evaluate(() => { window.proofClicks = []; });
  await page.getByRole('button', { name: 'Ambil Foto' }).click();
  expect(await page.evaluate(() => window.proofClicks)).toEqual([
    { capture: 'environment', accept: 'image/*' },
  ]);
  await page.evaluate(() => document.querySelector('#proof').dispatchEvent(new Event('change', { bubbles: true })));
  await expect(page.locator('#proof')).toHaveAttribute('accept', 'image/*,application/pdf');

  await page.locator('#proof').click();
  await page.evaluate(() => { window.proofClicks = []; });
  await page.getByRole('button', { name: 'Pilih dari Galeri' }).click();
  expect(await page.evaluate(() => window.proofClicks[0])).toEqual({
    capture: null,
    accept: 'image/*,application/pdf',
  });
});

test('dynamic photo inputs get the picker while non-image uploads stay unchanged', async ({ page }) => {
  await page.setContent('<form id="form"><input id="spreadsheet" type="file" accept=".xlsx"></form>');
  await page.addScriptTag({ path: 'resources/js/image-source-picker.js' });

  await page.evaluate(() => {
    document.querySelector('#spreadsheet').addEventListener('click', (event) => event.preventDefault());
  });
  await page.locator('#spreadsheet').click();
  await expect(page.getByRole('dialog', { name: 'Pilih sumber foto' })).toHaveCount(0);

  await page.evaluate(() => {
    document.querySelector('#form').insertAdjacentHTML('beforeend', '<input id="new_photo" name="new_photo" type="file" accept="image/*">');
  });
  await page.locator('#new_photo').click();
  await expect(page.getByRole('button', { name: 'Ambil Foto' })).toBeVisible();
});

test('mobile picker fits the screen and cancelling camera keeps multi-file upload available', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.setContent('<meta name="viewport" content="width=device-width, initial-scale=1"><input id="receipts" name="files[]" type="file" accept="image/*,application/pdf" multiple required>');
  await page.addScriptTag({ path: 'resources/js/image-source-picker.js' });

  await page.evaluate(() => {
    document.querySelector('#receipts').addEventListener('click', (event) => event.preventDefault());
  });
  await page.locator('#receipts').click();

  const dialog = page.getByRole('dialog', { name: 'Pilih sumber foto' });
  await expect(dialog).toBeVisible();
  const bounds = await dialog.boundingBox();
  expect(bounds.x).toBeGreaterThanOrEqual(0);
  expect(bounds.x + bounds.width).toBeLessThanOrEqual(390);

  await page.getByRole('button', { name: 'Ambil Foto' }).click();
  await expect(page.locator('#receipts')).not.toHaveAttribute('multiple');
  await page.evaluate(() => document.querySelector('#receipts').dispatchEvent(new Event('cancel')));
  await expect(page.locator('#receipts')).toHaveAttribute('multiple');
  await expect(page.locator('#receipts')).toHaveAttribute('accept', 'image/*,application/pdf');
});


test('source picker keeps keyboard focus inside the dialog and Escape closes it', async ({ page }) => {
  await page.setContent('<input id="photo" type="file" accept="image/*">');
  await page.addScriptTag({ path: 'resources/js/image-source-picker.js' });

  await page.locator('#photo').click();
  const galleryButton = page.getByRole('button', { name: 'Pilih dari Galeri' });
  await galleryButton.focus();
  await page.keyboard.press('Tab');
  await expect(page.getByRole('button', { name: 'Tutup pilihan foto' })).toBeFocused();

  await page.keyboard.press('Escape');
  await expect(page.getByRole('dialog', { name: 'Pilih sumber foto' })).toHaveCount(0);
});


test('desktop photo upload opens the native file picker directly', async ({ browser }) => {
  const context = await browser.newContext({ hasTouch: false, isMobile: false, viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  try {
    await page.setContent('<input id="desktop_photo" name="desktop_photo" type="file" accept="image/*">');
    await page.addScriptTag({ path: 'resources/js/image-source-picker.js' });

    const fileChooser = page.waitForEvent('filechooser', { timeout: 2000 });
    await page.locator('#desktop_photo').click();
    await fileChooser;

    await expect(page.getByRole('dialog', { name: 'Pilih sumber foto' })).toHaveCount(0);
  } finally {
    await context.close();
  }
});


test('touchscreen laptop still opens storage directly', async ({ browser }) => {
  const context = await browser.newContext({ hasTouch: true, isMobile: false, viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  try {
    await page.setContent('<input id="laptop_photo" type="file" accept="image/*">');
    await page.addScriptTag({ path: 'resources/js/image-source-picker.js' });

    const fileChooser = page.waitForEvent('filechooser', { timeout: 2000 });
    await page.locator('#laptop_photo').click();
    await fileChooser;

    await expect(page.getByRole('dialog', { name: 'Pilih sumber foto' })).toHaveCount(0);
  } finally {
    await context.close();
  }
});

test('tablet photo upload shows camera and gallery choices', async ({ browser }) => {
  const context = await browser.newContext({ hasTouch: true, isMobile: true, viewport: { width: 820, height: 1180 } });
  const page = await context.newPage();
  try {
    await page.setContent('<meta name="viewport" content="width=device-width, initial-scale=1"><input id="tablet_photo" type="file" accept="image/*">');
    await page.addScriptTag({ path: 'resources/js/image-source-picker.js' });

    await page.locator('#tablet_photo').click();
    await expect(page.getByRole('button', { name: 'Ambil Foto' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Pilih dari Galeri' })).toBeVisible();
  } finally {
    await context.close();
  }
});
