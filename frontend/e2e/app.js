// End-to-end test of the whole app with Playwright (no extra package needed).
//   1. playwright-cli -s=e2e open --headed http://localhost:3000/admin/login   (--headed = visible window)
//   2. log in there as a super admin
//   3. playwright-cli -s=e2e run-code --filename=e2e/app.js
// Each run creates a new shop, owner and staff, and returns a PASS/FAIL line per step. No passwords live here.
async (page) => {
  const BASE = 'http://localhost:3000';
  const run = Date.now();
  const shop = `E2E Shop ${run}`;
  const owner = { name: 'E2E Owner', email: `e2e.owner.${run}@example.com`, password: `E2e-Owner-${run}` };
  const staff = { name: 'E2E Staff', email: `e2e.staff.${run}@example.com`, password: `E2e-Staff-${run}` };
  const template = `E2E Template ${run}`;

  const results = [];
  const consoleErrors = [];
  page.on('console', (message) => message.type() === 'error' && consoleErrors.push(message.text()));

  // Auto-confirm "Delete/Suspend ...?" boxes (the CLI would otherwise stop at them)
  await page.addInitScript(() => { window.confirm = () => true; });

  const step = async (name, fn) => {
    try {
      await fn();
      results.push(`PASS  ${name}`);
    } catch (error) {
      results.push(`FAIL  ${name}: ${error.message.split('\n')[0]}`);
    }
  };
  const expectText = (text) => page.getByText(text, { exact: false }).first().waitFor({ timeout: 30000 });
  const expectUrl = (path) => page.waitForURL((url) => url.pathname === path, { timeout: 30000 });
  const save = () => page.getByRole('button', { name: 'Save' }).click();
  const shopLogin = async (user) => {
    await page.goto(`${BASE}/login`);
    await page.getByLabel('Email').fill(user.email);
    await page.getByLabel('Password').fill(user.password);
    await page.getByRole('button', { name: 'Login' }).click();
  };
  const shopRow = () => page.locator('tbody tr').filter({ hasText: shop });
  // Reload until the locator shows (or is gone), so a slow dev refresh does not fail the step
  const reloadUntil = async (locator, { gone = false } = {}) => {
    for (let i = 0; i < 10; i++) {
      if ((await locator.count() > 0) !== gone) return;
      await page.waitForTimeout(1000);
      await page.reload();
    }
    throw new Error(`${locator} ${gone ? 'still shown' : 'not shown'}`);
  };

  // ---------- Super admin: create the shop ----------

  await step('Admin: panel opens', async () => {
    await page.goto(`${BASE}/admin`);
    await expectUrl('/admin/workspaces');
  });

  await step('Admin: create a shop with a new owner login', async () => {
    await page.goto(`${BASE}/admin/workspaces/new`);
    await page.getByLabel('Shop name').fill(shop);
    await page.getByLabel('Owner email').fill(owner.email);
    await page.getByLabel('Owner name (new user only)').fill(owner.name);
    await page.getByLabel('Owner password (new user only)').fill(owner.password);
    await page.getByRole('button', { name: 'Create shop' }).click();
    await expectUrl('/admin/workspaces');
    await shopRow().waitFor({ timeout: 30000 });
  });

  await step('Admin: role template create, edit, delete', async () => {
    await page.goto(`${BASE}/admin/role-templates/new`);
    await page.getByLabel('Role name').fill(template);
    await page.locator('input[value="customers.view"]').check();
    await save();
    await expectUrl('/admin/role-templates');
    await page.getByRole('link', { name: template }).click();
    await page.locator('input[value="members.view"]').check();
    await save();
    await expectUrl('/admin/role-templates');
    await page.getByRole('link', { name: template }).click();
    await page.getByRole('button', { name: 'Delete template' }).click();
    await expectUrl('/admin/role-templates');
    await reloadUntil(page.getByRole('link', { name: template }), { gone: true });
  });

  // ---------- Shop owner ----------

  await step('Shop: no shop session yet, so pages go to the shop login', async () => {
    await page.goto(`${BASE}/dashboard`);
    await expectUrl('/login');
  });

  await step('Shop: there is no public register page', async () => {
    const response = await page.goto(`${BASE}/register`);
    if (response.status() !== 404 && !page.url().endsWith('/login')) throw new Error(`status ${response.status()}`);
  });

  await step('Shop: wrong password is rejected', async () => {
    await shopLogin({ email: owner.email, password: 'wrong-password' });
    await expectText('Invalid email or password');
  });

  await step('Shop: owner logs in and sees the dashboard', async () => {
    await shopLogin(owner);
    await expectUrl('/dashboard');
    await expectText(`Welcome to ${shop}`);
  });

  await step('Shop: tokens are hidden from browser JavaScript', async () => {
    const visible = await page.evaluate(() => document.cookie);
    if (visible !== '') throw new Error(`document.cookie = "${visible}"`);
  });

  await step('Customer: wrong GSTIN state is rejected', async () => {
    await page.goto(`${BASE}/customers/new`);
    await page.getByLabel('Name').fill('Ramesh Kumar');
    await page.getByLabel('Phone').fill('9876543210');
    await page.getByLabel('GSTIN').fill('09AAACR5055K1Z5');
    await page.getByLabel('State code').fill('27');
    await save();
    await expectText('GSTIN does not match the state code');
  });

  await step('Customer: create fills state from GSTIN', async () => {
    await page.getByLabel('State code').fill('');
    await save();
    await expectUrl('/customers');
    await expectText('Ramesh Kumar');
  });

  await step('Customer: search, edit and delete', async () => {
    await page.goto(`${BASE}/customers?search=ramesh`);
    await page.getByRole('link', { name: 'Ramesh Kumar' }).click();
    await page.waitForURL(/\/customers\/\d+$/);
    if ((await page.getByLabel('State code').inputValue()) !== '09') throw new Error('state code not filled from GSTIN');
    await page.getByLabel('Address').fill('Main Bazaar');
    await save();
    await expectUrl('/customers');
    await page.getByRole('link', { name: 'Ramesh Kumar' }).click();
    await page.getByRole('button', { name: 'Delete customer' }).click();
    await expectText('No customers yet');
  });

  await step('Owner: create a "Staff" role (view customers + members)', async () => {
    await page.goto(`${BASE}/roles/new`);
    await page.getByLabel('Role name').fill('Staff');
    await page.locator('input[value="customers.view"]').check();
    await page.locator('input[value="members.view"]').check();
    await save();
    await expectUrl('/roles');
    await expectText('Staff');
  });

  await step('Owner: hire a staff member with the Staff role', async () => {
    await page.goto(`${BASE}/members`);
    await page.getByLabel('Email').fill(staff.email);
    await page.getByLabel('Role').selectOption({ label: 'Staff' });
    await page.getByLabel('Name (new user only)').fill(staff.name);
    await page.getByLabel('Password (new user only)').fill(staff.password);
    await page.getByRole('button', { name: 'Add member' }).click();
    await expectText('Member added successfully');
    await reloadUntil(page.locator('tbody').getByText(staff.email));
  });

  await step('Owner: shop profile needs a GSTIN when GST registered', async () => {
    await page.goto(`${BASE}/settings/profile`);
    await page.getByLabel('GST', { exact: true }).selectOption('regular');
    await page.getByLabel('UPI ID').fill('e2eshop@okaxis');
    await save();
    await expectText('GSTIN is required');
    await page.getByLabel('GSTIN').fill('09AAACR5055K1Z5');
    await save();
    await expectText('updated successfully');
  });

  await step('Knowledge: text entry is saved and learned by the AI', async () => {
    await page.goto(`${BASE}/knowledge/new`);
    await page.getByLabel('Title').fill(`Shop info ${run}`);
    await page.getByLabel('About your shop').fill('We sell solar lights and panels. Open 9am to 7pm. Free delivery within 5 km.');
    await save();
    await expectUrl('/knowledge');
    await reloadUntil(page.locator('tbody tr').filter({ hasText: `Shop info ${run}` }).getByText('Learned', { exact: true }));
  });

  await step('Knowledge: file upload is saved and learned by the AI', async () => {
    await page.goto(`${BASE}/knowledge/new`);
    await page.getByLabel('Title').fill(`Price list ${run}`);
    await page.getByRole('radio', { name: 'Upload a file' }).check();
    await page.setInputFiles('#file', { name: 'prices.txt', mimeType: 'text/plain', buffer: Buffer.from('Solar LED bulb 9W costs Rs 450.') });
    await save();
    await expectUrl('/knowledge');
    const row = page.locator('tbody tr').filter({ hasText: `Price list ${run}` });
    await reloadUntil(row.getByText('prices.txt'));
    await reloadUntil(row.getByText('Learned', { exact: true }));
  });

  await step('Knowledge: edit text and delete the file entry', async () => {
    await page.getByRole('link', { name: `Shop info ${run}` }).click();
    await page.waitForURL(/\/knowledge\/\d+$/);
    await page.getByLabel('About your shop').fill('We sell solar lights. Open 10am to 8pm.');
    await save();
    await expectUrl('/knowledge');
    await page.getByRole('link', { name: `Price list ${run}` }).click();
    await page.waitForURL(/\/knowledge\/\d+$/);
    await page.getByRole('button', { name: 'Delete knowledge' }).click();
    await expectUrl('/knowledge');
    await reloadUntil(page.getByRole('link', { name: `Price list ${run}` }), { gone: true });
  });

  await step('Owner: logout', async () => {
    await page.getByRole('link', { name: 'Logout' }).click();
    await expectUrl('/login');
  });

  // ---------- Staff ----------

  await step('Staff: sees only allowed menu and no edit buttons', async () => {
    await shopLogin(staff);
    await expectUrl('/dashboard');
    const menu = await page.locator('aside').innerText();
    if (!menu.includes('Customers') || menu.includes('Roles') || menu.includes('Shop profile') || menu.includes('Knowledge')) throw new Error(`menu: ${menu.replace(/\n/g, ', ')}`);
    await page.goto(`${BASE}/customers`);
    await page.getByRole('heading', { name: 'Customers' }).waitFor();
    if (await page.getByRole('link', { name: 'New customer' }).count()) throw new Error('"New customer" is visible');
    await page.goto(`${BASE}/members`);
    if (await page.getByRole('button', { name: 'Remove' }).count()) throw new Error('"Remove" is visible');
  });

  await step('Staff: blocked from roles by URL', async () => {
    await page.goto(`${BASE}/roles`);
    await expectText('do not have permission');
  });

  // ---------- Super admin: suspend blocks the shop ----------

  await step('Admin: suspend the shop', async () => {
    await page.goto(`${BASE}/admin/workspaces?search=${encodeURIComponent(shop)}`);
    await shopRow().getByRole('button', { name: 'Suspend' }).click();
    await reloadUntil(shopRow().getByText('suspended', { exact: true }));
  });

  await step('Staff: suspended shop is blocked', async () => {
    await page.goto(`${BASE}/dashboard`);
    await expectText('This workspace is suspended');
  });

  await step('Admin: activate the shop again', async () => {
    await page.goto(`${BASE}/admin/workspaces?search=${encodeURIComponent(shop)}`);
    await shopRow().getByRole('button', { name: 'Activate' }).click();
    await reloadUntil(shopRow().getByText('active', { exact: true }));
  });

  // ---------- Separate logins ----------

  await step('Admin logout does not log the staff out of the shop', async () => {
    await page.goto(`${BASE}/admin/logout`);
    await expectUrl('/admin/login');
    await page.goto(`${BASE}/dashboard`);
    await expectUrl('/dashboard');
  });

  await step('Shop account cannot use the admin login', async () => {
    await page.goto(`${BASE}/admin/login`);
    await page.getByLabel('Email').fill(owner.email);
    await page.getByLabel('Password').fill(owner.password);
    await page.getByRole('button', { name: 'Login' }).click();
    await expectText('Invalid email or password');
  });

  await page.goto(`${BASE}/logout`);

  results.push(consoleErrors.length ? `WARN  ${consoleErrors.length} console error(s): ${consoleErrors[0]}` : 'PASS  No console errors');

  return results.join('\n');
}
