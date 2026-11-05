// NODE_PATH: existing Playwright test tools. Uses disposable UX fixtures only.
const {chromium} = require('playwright');
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const base = process.env.SPORTSHUB_URL || 'http://localhost/MyProject/27TUSportsHub';
const php = process.env.PHP_BINARY || 'php';
const fixture = (...args) => execFileSync(php, [path.join(__dirname, 'ux-fixtures.php'), ...args], {encoding:'utf8'}).trim();
const sql = (query, params=[]) => JSON.parse(execFileSync(php, ['-r',
    'require "config/db.php"; $q=get_db_connection()->prepare($argv[1]); $q->execute(json_decode($argv[2],true)); echo json_encode($q->columnCount() ? $q->fetchAll() : []);',
    query, JSON.stringify(params)], {cwd:root, encoding:'utf8'}));
(async () => {
    const f = JSON.parse(fixture('create'));
    let browser;
    let failures = 0;
    try {
        browser = await chromium.launch({channel:'chrome', headless:true});
        const login = async role => {
            const page = await browser.newPage();
            await page.goto(base+'/login.php');
            await page.locator('[name=email]').fill(f.users[role].email);
            await page.locator('[name=password]').fill('UxFixture123!');
            await Promise.all([page.waitForNavigation(), page.locator('button[type=submit]').click()]);
            return page;
        };
        const student = await login('student'), manager = await login('manager'), outsider = await login('outsider');
        const post = async (page, url, data, receipt) => {
            const csrf_token = await page.locator('[name=csrf_token]').first().inputValue();
            if (url.startsWith('/manager/review-receipt.php') && await page.locator('[name=receipt_id]').count()) {
                data = {receipt_id:await page.locator('[name=receipt_id]').inputValue(),...data};
            }
            return page.request.post(base+url, receipt ? {multipart:{csrf_token,...data,receipt:{name:'synthetic.png',mimeType:'image/png',buffer:receipt}}} : {form:{csrf_token,...data}});
        };
        const check = async (name, run) => {
            try { await run(); console.log('PASS '+name); }
            catch (e) { failures++; console.error('FAIL '+name+': '+e.message); }
        };
        await check('staff email rejected by actual signup handler', async () => {
            const page = await browser.newPage();
            await page.goto(base+'/register.php');
            const response = await post(page, '/register.php', {name:'Staff test',email:'admin@taylors.edu.my',password:'Password123!',confirm_password:'Password123!',terms:'1'});
            assert.ok((await response.text()).includes('Use your @sd.taylors.edu.my student email'), 'staff signup must show student-domain error');
            await page.close();
        });
        await check('published/closed/cancelled terms stay fixed; draft editable', async () => {
            const sid = f.sessions.free;
            const original = sql('SELECT * FROM sessions WHERE id=?',[sid])[0];
            for (const status of ['published','closed','cancelled']) {
                sql('UPDATE sessions SET status=? WHERE id=?',[status,sid]);
                await manager.goto(base+`/manager/session-edit.php?id=${sid}`);
                await post(manager, `/manager/session-edit.php?id=${sid}`, {
                    club_id:f.club,title:'FORGED TITLE',sport_type:'Basketball',venue:'FORGED VENUE',
                    session_date:'2099-01-01',start_time:'09:00',end_time:'10:00',price:'999',capacity:'999',
                    description:'Updated description',image_path:'assets/images/sports/badminton-open-play.webp',action:'publish'
                });
                const current = sql('SELECT * FROM sessions WHERE id=?',[sid])[0];
                for (const key of ['club_id','title','sport_type','venue','session_date','start_time','end_time','price','capacity']) assert.equal(current[key],original[key],`${status}: ${key}`);
                assert.equal(current.status,status);
                assert.equal(current.description,'Updated description');
            }
            sql("UPDATE sessions SET status='draft' WHERE id=?",[sid]);
            await manager.goto(base+`/manager/session-edit.php?id=${sid}`);
            await post(manager, `/manager/session-edit.php?id=${sid}`, {club_id:f.club,title:'Draft edited',sport_type:'Badminton',venue:'Draft court',session_date:'2099-01-01',start_time:'09:00',end_time:'10:00',price:'0',capacity:'3',description:'Draft description',image_path:'',action:'draft'});
            assert.equal(sql('SELECT title FROM sessions WHERE id=?',[sid])[0].title,'Draft edited');
        });
        await check('receipt replacements retain review history and scoped evidence', async () => {
            await student.goto(base+`/session-detail.php?id=${f.sessions.paid}`);
            await post(student,'/reserve.php',{session_id:f.sessions.paid});
            const reg = JSON.parse(fixture('read'));
            const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aK9sAAAAASUVORK5CYII=','base64');
            await post(student,'/upload-receipt.php',{registration_id:reg.id},png);
            await manager.goto(base+`/manager/review-receipt.php?id=${reg.id}`);
            await post(manager,`/manager/review-receipt.php?id=${reg.id}`,{decision:'reject',rejection_reason:'First reference unreadable'});
            await post(student,'/upload-receipt.php',{registration_id:reg.id},png);
            const staleReceipt = await manager.locator('[name=receipt_id]').inputValue();
            const stale = await post(manager,`/manager/review-receipt.php?id=${reg.id}`,{decision:'approve',receipt_id:staleReceipt});
            assert.ok((await stale.text()).includes('The receipt changed'));
            assert.equal(JSON.parse(fixture('read')).status,'awaiting_approval');
            await manager.goto(base+`/manager/review-receipt.php?id=${reg.id}`);
            await post(manager,`/manager/review-receipt.php?id=${reg.id}`,{decision:'approve'});
            const receipts = sql('SELECT * FROM payment_receipts WHERE registration_id=? ORDER BY id',[reg.id]);
            assert.equal(receipts.length,2);
            assert.equal(receipts[0].decision,'rejected');
            assert.equal(receipts[0].rejection_reason,'First reference unreadable');
            assert.equal(receipts[1].decision,'approved');
            for (const r of receipts) {
                assert.equal(r.reviewed_by,f.users.manager.id);
                assert.ok(r.reviewed_at);
                const url=base+`/evidence.php?registration_id=${reg.id}&receipt_id=${r.id}`;
                assert.equal((await student.request.get(url)).status(),200);
                assert.equal((await manager.request.get(url)).status(),200);
                assert.equal((await outsider.request.get(url)).status(),403);
            }
            assert.notEqual(receipts[0].receipt_path,receipts[1].receipt_path);
            const mismatch=base+`/evidence.php?registration_id=${reg.id}&receipt_id=2147483647`;
            assert.equal((await student.request.get(mismatch)).status(),404);
            for (const page of [student,manager]) {
                await page.goto(base+(page===student?`/payment.php?id=${reg.id}`:`/manager/review-receipt.php?id=${reg.id}`));
                assert.match(await page.locator('main').innerText(),/First reference unreadable/);
                assert.equal(await page.locator('a[href*="receipt_id="]').count(),2);
            }
            // Retried decision cannot overwrite the earlier reviewer decision.
            await post(manager,`/manager/review-receipt.php?id=${reg.id}`,{decision:'reject',rejection_reason:'Stale retry'});
            assert.equal(sql('SELECT decision FROM payment_receipts WHERE id=?',[receipts[1].id])[0].decision,'approved');
        });
        await check('active receipt lightbox retains zoom, Escape and focus return', async () => {
            await manager.goto(base+`/manager/participants.php?session_id=${f.sessions.paid}`);
            const trigger = manager.locator('[data-receipt-zoom]').first();
            await trigger.click();
            assert.equal(await manager.locator('#receipt-lightbox').isVisible(),true);
            await manager.locator('#receipt-zoom-in').click();
            assert.equal(await manager.locator('#receipt-zoom-level').innerText(),'125%');
            await manager.keyboard.press('Escape');
            assert.equal(await manager.locator('#receipt-lightbox').isVisible(),false);
            assert.equal(await trigger.evaluate(el=>el===document.activeElement),true);
        });
        await check('cover selection follows native radio state without JavaScript', async () => {
            const context = await browser.newContext({javaScriptEnabled:false});
            try {
                await context.addCookies(await manager.context().cookies());
                const page = await context.newPage();
                await page.goto(base+`/manager/session-edit.php?id=${f.sessions.free}`);
                const cards = page.locator('.image-picker-option');
                await cards.nth(1).locator('input').check();
                await page.mouse.move(0,0);
                await page.screenshot({animations:'disabled'});
                assert.equal(await cards.nth(1).evaluate(el=>getComputedStyle(el).borderColor),'rgb(200, 16, 46)');
                assert.notEqual(await cards.first().evaluate(el=>getComputedStyle(el).borderColor),'rgb(200, 16, 46)');
            } finally { await context.close(); }
        });
        await check('account menu supports native Enter/Space, Escape, Tab and link navigation', async () => {
            await student.goto(base+'/my-activities.php');
            const toggle=student.locator('.user-menu-pill');
            await toggle.focus(); await student.keyboard.press('Enter');
            assert.equal(await toggle.getAttribute('aria-expanded'),'true');
            await student.keyboard.press('Tab');
            assert.equal(await student.locator('.user-dropdown a').first().evaluate(el=>el===document.activeElement),true);
            await student.keyboard.press('Escape');
            assert.equal(await toggle.getAttribute('aria-expanded'),'false');
            assert.equal(await toggle.evaluate(el=>el===document.activeElement),true);
            await student.keyboard.press('Space');
            assert.equal(await toggle.getAttribute('aria-expanded'),'true');
            await student.locator('.user-dropdown a').first().click();
            await student.waitForURL('**/profile.php');
        });
        await check('CSS uses explicit transition properties', async () => {
            for (const file of fs.readdirSync(path.join(root,'assets/css')).filter(n=>n.endsWith('.css'))) {
                assert.doesNotMatch(fs.readFileSync(path.join(root,'assets/css',file),'utf8'),/transition\s*:\s*all\b/,file);
            }
        });
        await check('navigation has one account separator, no mobile identity line and a touch-sized reset', async () => {
            await student.setViewportSize({width:1440,height:1000});
            await student.goto(base+'/sessions.php');
            const header = student.locator('.user-dropdown-header');
            assert.equal(await header.evaluate(el=>el.nextElementSibling.classList.contains('dropdown-divider')),false);
            assert.equal(await header.evaluate(el=>getComputedStyle(el).borderBottomWidth),'1px');
            await student.setViewportSize({width:390,height:844});
            assert.ok((await student.locator('.filter-reset-link').boundingBox()).height>=44);
            await student.locator('.mobile-menu-btn').click();
            assert.doesNotMatch(await student.locator('.mobile-drawer').innerText(),/Signed in as/);
            assert.equal(await student.locator('.mobile-drawer button[type=submit]').isVisible(),true);
            await student.keyboard.press('Escape');
        });
        await check('reset/popular actions and editable-field feedback are consistent across pages', async () => {
            const publicPage = await browser.newPage();
            const feedback = async (page,selector) => {
                const field = page.locator(selector).first();
                await field.blur(); await field.hover(); await page.waitForTimeout(220);
                const read = () => field.evaluate(el=>{const s=getComputedStyle(el);return {background:s.backgroundColor,border:s.borderColor,shadow:s.boxShadow,outline:s.outlineStyle}});
                const hover = await read();
                await field.focus(); await page.waitForTimeout(220);
                return {hover,focus:await read()};
            };
            try {
                await publicPage.goto(base+'/sessions.php');
                const reset = publicPage.locator('.filter-reset-link');
                const popular = publicPage.locator('.sessions-filter-link').last();
                const readLink = el=>{const s=getComputedStyle(el);return Object.fromEntries(['padding','borderRadius','fontSize','fontWeight','color','minHeight','backgroundColor'].map(key=>[key,s[key]]))};
                await publicPage.mouse.move(0,0);
                assert.deepEqual(await reset.evaluate(readLink),await popular.evaluate(readLink));
                await reset.hover(); await publicPage.waitForTimeout(220); const hoverLink = await reset.evaluate(readLink);
                await popular.hover(); await publicPage.waitForTimeout(220); assert.deepEqual(await popular.evaluate(readLink),hoverLink);
                const reference = await feedback(publicPage,'.input-text-standard');
                assert.deepEqual(await feedback(publicPage,'.form-select'),reference);
                for (const [route,selector] of [['/index.php','.hero-search-input'],['/login.php','[name=email]'],['/register.php','[name=name]']]) {
                    await publicPage.goto(base+route);
                    assert.deepEqual(await feedback(publicPage,selector),reference,route);
                    if (route==='/index.php') {
                        assert.equal(await publicPage.locator('.hero-search-bar').evaluate(el=>getComputedStyle(el).borderColor),'rgba(0, 0, 0, 0.1)');
                    }
                }
                await student.goto(base+'/profile.php');
                const disabled = student.locator('#user_email');
                const before = await disabled.evaluate(el=>getComputedStyle(el).backgroundColor);
                await disabled.hover(); await student.waitForTimeout(220);
                assert.equal(await disabled.evaluate(el=>getComputedStyle(el).backgroundColor),before);
                for (const selector of ['#full_name','#faculty_select']) assert.deepEqual(await feedback(student,selector),reference,selector);
                const reg = JSON.parse(fixture('read'));
                await student.goto(base+`/cancel-registration.php?id=${reg.id}`);
                assert.deepEqual(await feedback(student,'#recipient_bank_name'),reference);
                await manager.goto(base+`/manager/session-edit.php?id=${f.sessions.free}`);
                for (const selector of ['#club_id','#title','#session_date','textarea']) assert.deepEqual(await feedback(manager,selector),reference,selector);
            } finally { await publicPage.close(); }
        });
        await check('shared registration feedback retains hover, focus and reduced-motion behavior', async () => {
            await student.goto(base+`/session-detail.php?id=${f.sessions.paid}`);
            const reserve = student.locator('.btn-reserve').first();
            assert.equal(await reserve.evaluate(el=>getComputedStyle(el).borderRadius),'9999px');
            const reg = JSON.parse(fixture('read'));
            await student.goto(base+`/cancel-registration.php?id=${reg.id}`);
            const cancel = student.locator('.registration-cancel-button');
            assert.equal(await cancel.count(),1);
            const style = () => cancel.evaluate(el=>({background:getComputedStyle(el).backgroundColor,shadow:getComputedStyle(el).boxShadow}));
            await student.mouse.move(0,0); await student.waitForTimeout(200);
            const normal = await style();
            await cancel.hover(); await student.waitForTimeout(200);
            assert.notDeepEqual(await style(),normal);
            await cancel.focus();
            assert.equal(await cancel.evaluate(el=>getComputedStyle(el).outlineStyle),'solid');
            await student.emulateMedia({reducedMotion:'reduce'});
            try {
                await cancel.hover(); await student.mouse.down(); await student.waitForTimeout(50);
                assert.equal(await cancel.evaluate(el=>getComputedStyle(el).transform),'none');
            } finally {
                await student.mouse.move(0,0); await student.mouse.up();
                await student.emulateMedia({reducedMotion:'no-preference'});
            }
        });
        await check('standalone error pages resolve their styles under the installation path', async () => {
            const page = await browser.newPage();
            try {
                for (const code of [403,404,500]) {
                    const response = await page.goto(base+`/${code}.php`);
                    assert.equal(response.status(),code);
                    const logo = page.locator('.error-header .brand-logo-img');
                    assert.equal(await logo.count(),1);
                    assert.equal(await logo.evaluate(el=>el.complete&&el.naturalWidth>0),true);
                    assert.equal(new URL(await logo.getAttribute('src'),base).pathname,new URL(base).pathname+'/assets/images/logo/logo.webp');
                    assert.equal(await page.locator('.error-header .brand-subtitle').innerText(),'SPORTS HUB');
                    const styles = await page.locator('link[rel=stylesheet]').evaluateAll(links=>links.map(link=>link.href));
                    for (const url of styles) assert.equal((await page.request.get(url)).status(),200,`missing stylesheet for ${code}`);
                }
            } finally { await page.close(); }
        });
    } finally {
        if (browser) await browser.close();
        console.log(fixture('cleanup'));
    }
    if (failures) process.exitCode=1;
})().catch(e=>{console.error(e);process.exitCode=1;});
