const { chromium } = require(process.env.PLAYWRIGHT_PATH || 'playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const base = process.env.UI_BASE_URL || 'http://localhost/BaiTapNhomPHP/public';
const output = path.resolve('storage/temp/ui-review');
fs.mkdirSync(output, { recursive: true });
(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    for (const width of [375, 768, 1440]) {
      await page.setViewportSize({ width, height: 960 });
      for (const route of ['/', '/login', '/register', '/missing']) {
        const response = await page.goto(`${base}/index.php?route=${encodeURIComponent(route)}`);
        assert.equal(response.status(), route === '/missing' ? 404 : 200);
        await page.waitForTimeout(450);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${width} ${route}: overflow`);
        assert.equal(await page.locator('h1').count(), 1);
        if (route === '/login' || route === '/register') {
          assert.equal(await page.locator('.navbar, .footer').count(), 0, 'auth must not use main layout');
          assert.equal(await page.locator('.auth-home-link').isVisible(), true);
        }
        await page.screenshot({ path: path.join(output, `${width}-${route.replaceAll('/', '') || 'home'}.png`), fullPage: true });
      }
    }
    await page.goto(`${base}/index.php?route=/login`);
    await page.locator('.pw-toggle').click();
    assert.equal(await page.locator('#password').getAttribute('type'), 'text');
    await page.locator('.pw-toggle').click();
    assert.equal(await page.locator('#password').getAttribute('type'), 'password');
    await page.locator('.auth-foot a').click();
    await page.waitForURL(/route=%2Fregister/);
    await page.locator('button[type=submit]').last().click();
    await page.waitForLoadState('load');
    assert.equal(await page.locator('#username-error').count(), 1);
    assert.equal(await page.locator('#password-error').count(), 1);
    assert.equal(await page.locator('#password').inputValue(), '');
    assert.equal(await page.locator('.auth-enter').count(), 0, 'validation response must not animate');
    await page.setViewportSize({ width: 375, height: 900 });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    await page.screenshot({ path: path.join(output, '375-register-errors.png'), fullPage: true });
    const csrf = await page.locator('[name=_token]').inputValue();
    assert.equal(csrf.length, 64);
    await page.goto(`${base}/index.php?route=/login`);
    await page.locator('button[type=submit]').last().click();
    await page.waitForLoadState('load');
    assert.equal(await page.locator('#username-error').count(), 1);
    assert.equal(await page.locator('#password-error').count(), 1);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto(`${base}/index.php?route=/login`);
    assert.equal(await page.locator('.auth-enter').count(), 0);
    await page.locator('.auth-foot a').click();
    await page.waitForURL(/route=%2Fregister/);
    const nojs = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 375, height: 900 } });
    const plain = await nojs.newPage();
    await plain.goto(`${base}/index.php?route=/login`);
    assert.equal(await plain.locator('.auth-home-link').isVisible(), true);
    await plain.locator('.auth-foot a').click();
    await plain.waitForURL(/route=%2Fregister/);
    assert.equal(await plain.locator('form [name=_token]').count(), 1);
    await plain.locator('button[type=submit]').last().click();
    await plain.waitForLoadState('load');
    assert.equal(await plain.locator('#username-error').count(), 1);
    assert.equal(await plain.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    const missingImage = await browser.newContext({ viewport: { width: 1440, height: 960 } });
    const fallback = await missingImage.newPage();
    await fallback.route('**/auth-background.jpg', route => route.abort());
    await fallback.goto(`${base}/index.php?route=/login`);
    await fallback.screenshot({ path: path.join(output, '1440-image-fallback.png'), fullPage: true });
    assert.equal(await fallback.locator('h1').isVisible(), true);
    // Render protected views with in-memory sample data, not database records
    // or a test endpoint in the application.
    const fixtures = [
      ['account', 'auth/account', "['title'=>'Tài khoản','user'=>['ten_vai_tro'=>'KHACH_HANG','ten_dang_nhap'=>'sample.user','ten_khach_hang'=>'Người dùng kiểm thử']]"],
      ['settings', 'auth/settings', "['title'=>'Cài đặt tài khoản','user'=>['ten_dang_nhap'=>'sample.user']]"],
      ['role', 'auth/role', "['title'=>'Khách hàng','user'=>['ten_dang_nhap'=>'sample.user']]"],
      ['403', 'errors/error', "['title'=>'403','message'=>'Bạn không có quyền truy cập trang này.']"],
      ['500', 'errors/error', "['title'=>'500','message'=>'Hệ thống tạm thời không khả dụng. Vui lòng thử lại sau.']"],
      ['login-failure', 'auth/login', "['title'=>'Đăng nhập','username'=>'sample.user','loginError'=>'Đăng nhập thất bại: Tài khoản hoặc mật khẩu bị sai']"],
    ];
    const fixturePage = await browser.newPage();
    for (const [name, view, data] of fixtures) {
      const code = `require 'vendor/autoload.php'; $_ENV['APP_BASE_PATH'] = '/BaiTapNhomPHP/public'; $_SESSION = []; Core\\View::render('${view}', ${data});`;
      const html = execFileSync('php', ['-r', code], { encoding: 'utf8' });
      await fixturePage.route('**/ui-fixture', route => route.fulfill({ contentType: 'text/html; charset=utf-8', body: html }));
      for (const width of [375, 768, 1440]) {
        await fixturePage.setViewportSize({ width, height: 960 });
        await fixturePage.goto(`${base}/ui-fixture`);
        await fixturePage.waitForTimeout(50);
        assert.equal(await fixturePage.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${name} overflow`);
        if (name === 'login-failure') {
          assert.equal(await fixturePage.locator('[aria-invalid=true]').count(), 0);
          assert.equal(await fixturePage.locator('.login-error').count(), 1);
        }
        await fixturePage.screenshot({ path: path.join(output, `${width}-${name}.png`), fullPage: true });
      }
      await fixturePage.unroute('**/ui-fixture');
    }
    const signedInCode = `require 'vendor/autoload.php'; $_ENV['APP_BASE_PATH'] = '/BaiTapNhomPHP/public'; $_SESSION = ['account_id'=>1,'authenticated_at'=>time(),'last_active_at'=>time(),'username'=>'sample.user']; Core\\View::render('auth/account', ['title'=>'Tài khoản','user'=>['ten_vai_tro'=>'KHACH_HANG','ten_dang_nhap'=>'sample.user','ten_khach_hang'=>'Người dùng kiểm thử','so_dien_thoai_khach'=>'0901234567','email_khach'=>'sample@example.test','ngay_sinh_khach'=>'2000-01-01','gioi_tinh_khach'=>'NU']]);`;
    const signedInHtml = execFileSync('php', ['-r', signedInCode], { encoding: 'utf8' });
    await fixturePage.route('**/ui-fixture', route => route.fulfill({ contentType: 'text/html; charset=utf-8', body: signedInHtml }));
    for (const width of [375, 768, 1440]) {
      await fixturePage.setViewportSize({ width, height: 960 });
      await fixturePage.goto(`${base}/ui-fixture`);
      if (width <= 800) await fixturePage.locator('#nav-toggle').click();
      const trigger = fixturePage.locator('.account-menu__trigger');
      assert.equal((await trigger.innerText()).includes('sample.user'), true, 'navbar username');
      const avatarBox = await fixturePage.locator('.account-identity .profile-avatar').boundingBox();
      const infoBox = await fixturePage.locator('.account-identity__info').boundingBox();
      assert.equal(infoBox.x > avatarBox.x + avatarBox.width, true, 'avatar and name are horizontal');
      assert.equal(Math.abs((avatarBox.y + avatarBox.height / 2) - (infoBox.y + infoBox.height / 2)) < 3,
        true, 'avatar and name are vertically centered');
      assert.equal(await fixturePage.locator('.account-identity').innerText().then(text => text.includes('@sample.user')), false,
        'identity header has no username');
      await trigger.click();
      assert.equal(await fixturePage.locator('#account-menu').getAttribute('open'), '', 'dropdown opens');
      assert.equal(await fixturePage.locator('.account-menu__chevron').evaluate(el => getComputedStyle(el).transform), 'none', 'chevron does not rotate');
      assert.equal(await fixturePage.locator('.account-menu__panel a').count(), 2, 'profile and settings links');
      assert.equal((await fixturePage.locator('.account-menu__panel a').nth(1).getAttribute('href')).includes('account%2Fsettings'), true,
        'settings menu opens separate route');
      await fixturePage.screenshot({ path: path.join(output, `${width}-account-dropdown.png`), fullPage: true });
      assert.equal(await fixturePage.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${width} dropdown overflow`);
      await fixturePage.keyboard.press('Escape');
      assert.equal(await fixturePage.locator('#account-menu').getAttribute('open'), null, 'Escape closes dropdown');
      await trigger.click();
      await fixturePage.locator('.account-page__intro h1').click();
      assert.equal(await fixturePage.locator('#account-menu').getAttribute('open'), null, 'outside click closes dropdown');
    }
    await fixturePage.unroute('**/ui-fixture');
    assert.deepEqual(errors, []);
    console.log('PASS: 375/768/1440 layouts, signed-in dropdown, account/profile, auth navigation, password toggle, validation, CSRF, no JS, reduced motion, missing-image fallback.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
