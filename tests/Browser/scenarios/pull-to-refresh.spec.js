import { test, expect } from '@playwright/test';

/* Pull-to-refresh buatan sendiri (resources/js/pull-to-refresh.js).
   Layout mengunci html/body overflow, jadi pull-to-refresh bawaan browser &
   PWA mati — modul ini penggantinya. Gestur disimulasikan lewat TouchEvent
   sintetis; reload asli dicegat lewat event `pull-to-refresh` (cancelable). */

test.use({ hasTouch: true, isMobile: true, viewport: { width: 390, height: 844 } });

const SHELL = `
  <style>
    html, body { height: 100%; margin: 0; overflow: hidden; overscroll-behavior: none; }
    #main-content { height: 100%; overflow-y: auto; }
    .tall { height: 3000px; }
    #inner { height: 200px; overflow-y: auto; }
    #inner .tall { height: 1000px; }
    #modal { position: fixed; inset: 0; }
  </style>
  <main id="main-content">
    <div id="content" class="tall">
      <div id="inner"><div class="tall"></div></div>
      <form method="post" id="pay"><input id="amount" name="amount"></form>
      <form method="get" id="filter"><input id="search" name="q"></form>
      <div id="no-ptr" data-no-pull-refresh>x</div>
    </div>
  </main>
`;

async function setup(page, { mainAttrs = '', extra = '' } = {}) {
  await page.setContent(SHELL.replace('<main id="main-content"', `<main id="main-content" ${mainAttrs}`) + extra);
  await page.addScriptTag({ path: 'resources/js/pull-to-refresh.js' });
  await page.evaluate(() => {
    window.refreshCount = 0;
    document.addEventListener('pull-to-refresh', (e) => {
      e.preventDefault();
      window.refreshCount++;
    });
  });
}

/** Simulasikan swipe 1 jari dari (x, y) sejauh (dx, dy), 10 langkah. */
async function swipe(page, selector, dx, dy) {
  return page.evaluate(({ selector, dx, dy }) => {
    const target = document.querySelector(selector);
    const rect = target.getBoundingClientRect();
    const x = rect.left + 20;
    const y = rect.top + 10;
    const touchAt = (cx, cy) => new Touch({ identifier: 1, target, clientX: cx, clientY: cy });
    const fire = (type, touches, changed) => {
      const ev = new TouchEvent(type, { bubbles: true, cancelable: true, touches, targetTouches: touches, changedTouches: changed });
      target.dispatchEvent(ev);

      return ev.defaultPrevented;
    };
    fire('touchstart', [touchAt(x, y)], [touchAt(x, y)]);
    let prevented = false;
    for (let i = 1; i <= 10; i++) {
      const t = touchAt(x + (dx * i) / 10, y + (dy * i) / 10);
      prevented = fire('touchmove', [t], [t]) || prevented;
    }
    const end = touchAt(x + dx, y + dy);
    fire('touchend', [], [end]);

    return prevented;
  }, { selector, dx, dy });
}

const refreshCount = (page) => page.evaluate(() => window.refreshCount);

test('tarikan cukup jauh di posisi atas memicu refresh', async ({ page }) => {
  await setup(page);
  const prevented = await swipe(page, '#content', 0, 250);
  expect(prevented).toBe(true);
  expect(await refreshCount(page)).toBe(1);
});

test('indikator tampil saat ditarik dan berubah label saat lewat ambang', async ({ page }) => {
  await setup(page);
  await page.evaluate(() => {
    const target = document.querySelector('#content');
    const t = (y) => new Touch({ identifier: 1, target, clientX: 50, clientY: y });
    const fire = (type, y) => target.dispatchEvent(new TouchEvent(type, { bubbles: true, cancelable: true, touches: [t(y)], changedTouches: [t(y)] }));
    fire('touchstart', 10);
    fire('touchmove', 60);
  });
  await expect(page.locator('.ptr-indicator')).toHaveText('Tarik untuk memuat ulang');
  await page.evaluate(() => {
    const target = document.querySelector('#content');
    const t = new Touch({ identifier: 1, target, clientX: 50, clientY: 300 });
    target.dispatchEvent(new TouchEvent('touchmove', { bubbles: true, cancelable: true, touches: [t], changedTouches: [t] }));
  });
  await expect(page.locator('.ptr-indicator')).toHaveText('Lepas untuk memuat ulang');
});

test('tarikan pendek tidak memicu refresh', async ({ page }) => {
  await setup(page);
  await swipe(page, '#content', 0, 60);
  expect(await refreshCount(page)).toBe(0);
});

test('halaman yang sudah digulir ke bawah tidak memicu refresh dan scroll tidak diblok', async ({ page }) => {
  await setup(page);
  await page.evaluate(() => { document.getElementById('main-content').scrollTop = 500; });
  const prevented = await swipe(page, '#content', 0, 250);
  expect(prevented).toBe(false);
  expect(await refreshCount(page)).toBe(0);
});

test('gulir ke atas (swipe naik) tidak diblok', async ({ page }) => {
  await setup(page);
  const prevented = await swipe(page, '#content', 0, -250);
  expect(prevented).toBe(false);
  expect(await refreshCount(page)).toBe(0);
});

test('geser horizontal (tabel) tidak memicu refresh', async ({ page }) => {
  await setup(page);
  const prevented = await swipe(page, '#content', 250, 60);
  expect(prevented).toBe(false);
  expect(await refreshCount(page)).toBe(0);
});

test('area gulir dalam yang belum di atas tidak memicu refresh', async ({ page }) => {
  await setup(page);
  await page.evaluate(() => { document.getElementById('inner').scrollTop = 100; });
  await swipe(page, '#inner .tall', 0, 250);
  expect(await refreshCount(page)).toBe(0);
});

test('area gulir dalam yang sudah di atas tetap memicu refresh', async ({ page }) => {
  await setup(page);
  await swipe(page, '#inner .tall', 0, 250);
  expect(await refreshCount(page)).toBe(1);
});

test('modal fixed, data-no-pull-refresh, dan main off tidak memicu refresh', async ({ page }) => {
  await setup(page, { extra: '' });
  await page.evaluate(() => {
    const modal = document.createElement('div');
    modal.id = 'modal';
    modal.innerHTML = '<div id="modal-body">modal</div>';
    document.getElementById('content').appendChild(modal);
  });
  await swipe(page, '#modal-body', 0, 250);
  await swipe(page, '#no-ptr', 0, 250);
  expect(await refreshCount(page)).toBe(0);

  await setup(page, { mainAttrs: 'data-pull-to-refresh="off"' });
  await swipe(page, '#content', 0, 250);
  expect(await refreshCount(page)).toBe(0);
});

test('sentuhan di luar main-content (header) tidak memicu refresh', async ({ page }) => {
  await setup(page, { extra: '<header id="hdr" style="position:absolute;top:0;left:0;right:0;height:40px">h</header>' });
  await swipe(page, '#hdr', 0, 250);
  expect(await refreshCount(page)).toBe(0);
});

test('form POST yang sudah diisi minta konfirmasi dulu; batal = tidak refresh', async ({ page }) => {
  await setup(page);
  await page.fill('#amount', '50000');
  await page.evaluate(() => {
    window.confirmCalls = [];
    window.Confirm = (title, message, icon, onConfirm) => { window.confirmCalls.push({ title, onConfirm }); };
  });
  await swipe(page, '#content', 0, 250);
  expect(await refreshCount(page)).toBe(0);
  expect(await page.evaluate(() => window.confirmCalls.length)).toBe(1);

  // Pilih "Ya" di dialog → baru refresh.
  await page.evaluate(() => window.confirmCalls[0].onConfirm());
  expect(await refreshCount(page)).toBe(1);
});

test('form filter GET tidak dianggap isian belum disimpan', async ({ page }) => {
  await setup(page);
  await page.fill('#search', 'budi');
  await page.evaluate(() => { window.Confirm = () => { window.confirmed = true; }; });
  await swipe(page, '#content', 0, 250);
  expect(await page.evaluate(() => window.confirmed ?? false)).toBe(false);
  expect(await refreshCount(page)).toBe(1);
});

test('tanpa handler, refresh benar-benar me-reload halaman', async ({ page }) => {
  await page.goto('about:blank');
  await page.setContent(SHELL);
  await page.addScriptTag({ path: 'resources/js/pull-to-refresh.js' });
  await page.evaluate(() => { window.marker = 'sebelum-reload'; });
  const navigation = page.waitForEvent('framenavigated');
  await swipe(page, '#content', 0, 250);
  await navigation;
  expect(await page.evaluate(() => window.marker)).toBeUndefined();
});
