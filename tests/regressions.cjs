// NODE_PATH: existing Playwright test tools. Uses disposable UX fixtures only.
const {chromium} = require('playwright');
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const base = process.env.SPORTSHUB_URL?.replace(/\/+$/, '');
if (!base) throw new Error('Set SPORTSHUB_URL to your running application URL.');
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
        browser = await chromium.launch({channel:process.env.BROWSER_CHANNEL || 'chrome', headless:true});
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
            await post(manager, `/manager/session-edit.php?id=${sid}`, {club_id:f.club,title:'Draft edited',sport_type:'Badminton',venue:'Draft court',session_date:original.session_date,start_time:'09:00',end_time:'10:00',price:'0',capacity:'3',description:'Draft description',image_path:'',action:'draft'});
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
                assert.equal(await cards.nth(1).locator('input').isChecked(),true);
                assert.equal(await cards.first().locator('input').isChecked(),false);
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
        await check('mobile navigation exposes logout and a touch-sized filter reset', async () => {
            await student.setViewportSize({width:1440,height:1000});
            await student.goto(base+'/sessions.php');
            await student.setViewportSize({width:390,height:844});
            assert.ok((await student.locator('.filter-reset-link').boundingBox()).height>=44);
            await student.locator('.mobile-menu-btn').click();
            assert.doesNotMatch(await student.locator('.mobile-drawer').innerText(),/Signed in as/);
            assert.equal(await student.locator('.mobile-drawer button[type=submit]').isVisible(),true);
            await student.keyboard.press('Escape');
        });
        await check('public and manager pages reflow across viewport sizes', async () => {
            const page = await browser.newPage();
            const noOverflow = async (p,label) => assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),label);
            try {
                for (const width of [320,390,768,1024,1440]) {
                    await page.setViewportSize({width,height:1000});
                    await page.goto(base+'/index.php');
                    await noOverflow(page,`home at ${width}`);
                    await page.goto(base+'/sessions.php');
                    await noOverflow(page,`sessions at ${width}`);
                    await manager.setViewportSize({width,height:1000});
                    await manager.goto(base+`/manager/session-edit.php?id=${f.sessions.free}`);
                    await noOverflow(manager,`editor at ${width}`);
                    for (const selector of ['#session_date','#start_time','#end_time']) {
                        const bounds = await manager.locator(selector).boundingBox();
                        assert.ok(bounds.x>=0 && bounds.x+bounds.width<=width,selector+' fits');
                    }
                    await manager.goto(base+'/manager/sessions.php');
                    await noOverflow(manager,`manager at ${width}`);
                }
                await manager.setViewportSize({width:900,height:1000});
                await manager.goto(base+'/manager/sessions.php');
                await manager.locator('.mobile-menu-btn').click();
                await manager.setViewportSize({width:1000,height:1000});
                assert.equal(await manager.locator('.mobile-drawer').evaluate(el=>el.classList.contains('open')),true);
                await manager.setViewportSize({width:1200,height:1000});
                await manager.waitForFunction(()=>document.querySelector('.mobile-menu-btn').getAttribute('aria-expanded')==='false');
                const reg = JSON.parse(fixture('read'));
                for (const route of ['review-receipt','record-refund']) {
                    await manager.goto(base+`/manager/${route}.php?id=${reg.id}`);
                    await noOverflow(manager,route);
                }
            } finally { await page.close(); }
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
                const missing=await page.goto(base+'/missing-route-for-portability-check');
                assert.equal(missing.status(),404);
                assert.equal(await page.locator('h1').innerText(),'Page not found');
                const logo=page.locator('.error-header .brand-logo-img');
                assert.equal(await logo.evaluate(el=>el.complete&&el.naturalWidth>0),true);
            } finally { await page.close(); }
        });
        await check('invalid CSRF blocks reservation and session changes', async () => {
            await student.goto(base+`/session-detail.php?id=${f.sessions.review}`);
            for (const csrf_token of ['', 'invalid']) {
                await post(student,'/reserve.php',{session_id:f.sessions.review,csrf_token});
                assert.equal(sql('SELECT id FROM registrations WHERE session_id=?',[f.sessions.review]).length,0);
                const original=sql('SELECT * FROM sessions WHERE id=?',[f.sessions.review])[0];
                for (const route of ['close-signup','cancel-session','session-edit']) {
                    await manager.goto(base+`/manager/${route}.php?id=${f.sessions.review}`);
                    await post(manager,`/manager/${route}.php?id=${f.sessions.review}`,{csrf_token,cancellation_reason:'Forbidden change',title:'Forbidden edit',description:'Forbidden description change',action:'publish'});
                    assert.deepEqual(sql('SELECT * FROM sessions WHERE id=?',[f.sessions.review])[0],original,route);
                }
            }
        });
        await check('server rejects invalid uploads and CSRF without creating evidence', async () => {
            await student.goto(base+`/session-detail.php?id=${f.sessions.review}`);
            await post(student,'/reserve.php',{session_id:f.sessions.review});
            const reg=sql('SELECT * FROM registrations WHERE session_id=? AND user_id=?',[f.sessions.review,f.users.student.id])[0];
            assert.equal(reg.status,'awaiting_payment');
            assert.equal(reg.price_snapshot,'15.00');
            const deadline=new Date(reg.payment_deadline.replace(' ','T')+'+08:00').getTime();
            assert.ok(deadline>Date.now()+23*3600000 && deadline<=Date.now()+24*3600000);
            await student.goto(base+`/payment.php?id=${reg.id}`);
            const csrf_token=await student.locator('[name=csrf_token]').first().inputValue();
            const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aK9sAAAAASUVORK5CYII=','base64');
            const before=sql('SELECT * FROM registrations WHERE id=?',[reg.id])[0];
            for (const token of ['', 'invalid']) {
                await post(student,'/upload-receipt.php',{registration_id:reg.id,csrf_token:token},png);
                assert.deepEqual(sql('SELECT * FROM registrations WHERE id=?',[reg.id])[0],before);
            }
            for (const receipt of [
                {name:'forged.png',mimeType:'image/png',buffer:Buffer.from('plain text pretending to be PNG')},
                {name:'oversize.png',mimeType:'image/png',buffer:Buffer.concat([png,Buffer.alloc(5*1024*1024)])},
            ]) {
                const response=await student.request.post(base+'/upload-receipt.php',{multipart:{csrf_token,registration_id:String(reg.id),receipt}});
                assert.ok(response.status()<500);
                assert.deepEqual(sql('SELECT * FROM registrations WHERE id=?',[reg.id])[0],before);
                assert.equal(sql('SELECT id FROM payment_receipts WHERE registration_id=?',[reg.id]).length,0);
            }
            await post(student,'/upload-receipt.php',{registration_id:reg.id},png);
            assert.equal(sql('SELECT status FROM registrations WHERE id=?',[reg.id])[0].status,'awaiting_approval');
        });
        await check('review CSRF and wrong-club manager cannot mutate registrations or sessions', async () => {
            const reg=sql('SELECT * FROM registrations WHERE session_id=? AND user_id=?',[f.sessions.review,f.users.student.id])[0];
            await manager.goto(base+`/manager/review-receipt.php?id=${reg.id}`);
            const receipts=sql('SELECT * FROM payment_receipts WHERE registration_id=?',[reg.id]);
            for (const csrf_token of ['', 'invalid']) {
                await post(manager,`/manager/review-receipt.php?id=${reg.id}`,{decision:'approve',csrf_token});
                assert.deepEqual(sql('SELECT * FROM registrations WHERE id=?',[reg.id])[0],reg);
                assert.deepEqual(sql('SELECT * FROM payment_receipts WHERE registration_id=?',[reg.id]),receipts);
            }
            const missingReceipt=await post(manager,`/manager/review-receipt.php?id=${reg.id}`,{decision:'approve',receipt_id:''});
            assert.match(await missingReceipt.text(),/The receipt changed/);
            assert.deepEqual(sql('SELECT * FROM registrations WHERE id=?',[reg.id])[0],reg);
            assert.deepEqual(sql('SELECT * FROM payment_receipts WHERE registration_id=?',[reg.id]),receipts);
            await outsider.goto(base+'/my-activities.php');
            for (const route of ['review-receipt','record-refund']) {
                assert.equal((await post(outsider,`/manager/${route}.php?id=${reg.id}`,{decision:'approve',amount:'1',reason:'Forbidden'})).status(),403);
            }
            const session=sql('SELECT * FROM sessions WHERE id=?',[f.sessions.review])[0];
            for (const route of ['cancel-session','close-signup','session-edit']) {
                assert.equal((await post(outsider,`/manager/${route}.php?id=${f.sessions.review}`,{cancellation_reason:'Forbidden',action:'publish'})).status(),403);
            }
            assert.deepEqual(sql('SELECT * FROM sessions WHERE id=?',[f.sessions.review])[0],session);
            assert.deepEqual(sql('SELECT * FROM registrations WHERE id=?',[reg.id])[0],reg);
            await post(manager,`/manager/review-receipt.php?id=${reg.id}`,{decision:'approve'});
            assert.equal(sql('SELECT status FROM registrations WHERE id=?',[reg.id])[0].status,'confirmed');
        });
        await check('cancellation and refund validation preserve state until valid transfer', async () => {
            const reg=sql('SELECT * FROM registrations WHERE session_id=? AND user_id=?',[f.sessions.review,f.users.student.id])[0];
            const bank={recipient_bank_name:'DEMO',recipient_account_name:'SYNTHETIC',recipient_account_number:'NOT-REAL'};
            await student.goto(base+`/cancel-registration.php?id=${reg.id}`);
            for (const csrf_token of ['', 'invalid']) {
                await post(student,`/cancel-registration.php?id=${reg.id}`,{...bank,csrf_token});
                assert.deepEqual(sql('SELECT * FROM registrations WHERE id=?',[reg.id])[0],reg);
            }
            await post(student,`/cancel-registration.php?id=${reg.id}`,bank);
            const pending=sql('SELECT * FROM registrations WHERE id=?',[reg.id])[0];
            assert.equal(pending.status,'cancellation_pending');
            await manager.goto(base+`/manager/record-refund.php?id=${reg.id}`);
            const unchanged=()=>{
                assert.deepEqual(sql('SELECT * FROM registrations WHERE id=?',[reg.id])[0],pending);
                assert.equal(sql('SELECT id FROM refund_records WHERE registration_id=?',[reg.id]).length,0);
            };
            for (const csrf_token of ['', 'invalid']) {
                await post(manager,`/manager/record-refund.php?id=${reg.id}`,{amount:'15',reason:'Synthetic',csrf_token});
                unchanged();
            }
            for (const amount of ['0','-1','15.001','15.01','1e1','nonsense']) {
                await post(manager,`/manager/record-refund.php?id=${reg.id}`,{amount,reason:'Synthetic'});
                unchanged();
            }
            const csrf_token=await manager.locator('[name=csrf_token]').first().inputValue();
            await manager.request.post(base+`/manager/record-refund.php?id=${reg.id}`,{multipart:{csrf_token,amount:'15',reason:'Synthetic',proof:{name:'forged.png',mimeType:'image/png',buffer:Buffer.from('not a PNG')}}});
            unchanged();
            assert.equal((await post(outsider,`/manager/record-refund.php?id=${reg.id}`,{amount:'15',reason:'Forbidden'})).status(),403);
            unchanged();
            await post(manager,`/manager/record-refund.php?id=${reg.id}`,{amount:'15',reason:'Synthetic external transfer'});
            assert.equal(sql('SELECT status FROM registrations WHERE id=?',[reg.id])[0].status,'cancelled');
            assert.equal(sql('SELECT amount FROM refund_records WHERE registration_id=?',[reg.id])[0].amount,'15.00');
            await student.goto(base+`/session-detail.php?id=${f.sessions.review}`);
            await post(student,'/reserve.php',{session_id:f.sessions.review});
            assert.equal(sql('SELECT status FROM registrations WHERE id=?',[reg.id])[0].status,'cancelled','Financial rebooking must retain prior evidence');
            assert.equal(sql('SELECT id FROM payment_receipts WHERE registration_id=?',[reg.id]).length,1);
        });
        await check('manager cannot approve own receipt or record own refund', async () => {
            await manager.goto(base+`/session-detail.php?id=${f.sessions.race}`);
            await post(manager,'/reserve.php',{session_id:f.sessions.race});
            const reg=sql('SELECT * FROM registrations WHERE session_id=? AND user_id=?',[f.sessions.race,f.users.manager.id])[0];
            const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aK9sAAAAASUVORK5CYII=','base64');
            await post(manager,'/upload-receipt.php',{registration_id:reg.id},png);
            await manager.goto(base+`/manager/review-receipt.php?id=${reg.id}`);
            const receipt=sql('SELECT * FROM payment_receipts WHERE registration_id=?',[reg.id])[0];
            assert.equal(receipt.decision,'awaiting_review');
            const response=await post(manager,`/manager/review-receipt.php?id=${reg.id}`,{decision:'approve',receipt_id:receipt.id});
            assert.match(await response.text(),/Another authorised manager/);
            assert.equal(sql('SELECT status FROM registrations WHERE id=?',[reg.id])[0].status,'awaiting_approval');
            assert.deepEqual(sql('SELECT * FROM payment_receipts WHERE id=?',[receipt.id])[0],receipt);
            // Temporary peer appointment exercises valid approval/refund through HTTP too.
            sql('INSERT INTO club_managers (club_id,user_id,appointed_by) VALUES (?,?,?)',[f.club,f.users.student.id,f.users.manager.id]);
            try {
                await student.goto(base+`/manager/review-receipt.php?id=${reg.id}`);
                await post(student,`/manager/review-receipt.php?id=${reg.id}`,{decision:'approve'});
                assert.equal(sql('SELECT status FROM registrations WHERE id=?',[reg.id])[0].status,'confirmed');
                await manager.goto(base+`/cancel-registration.php?id=${reg.id}`);
                await post(manager,`/cancel-registration.php?id=${reg.id}`,{recipient_bank_name:'DEMO',recipient_account_name:'SYNTHETIC',recipient_account_number:'NOT-REAL'});
                assert.equal(sql('SELECT status FROM registrations WHERE id=?',[reg.id])[0].status,'cancellation_pending');
                await manager.goto(base+`/manager/record-refund.php?id=${reg.id}`);
                const result=await post(manager,`/manager/record-refund.php?id=${reg.id}`,{amount:'15',reason:'Synthetic'});
                assert.match(await result.text(),/Another authorised manager/);
                assert.equal(sql('SELECT id FROM refund_records WHERE registration_id=?',[reg.id]).length,0);
                assert.equal(sql('SELECT status FROM registrations WHERE id=?',[reg.id])[0].status,'cancellation_pending');
                await student.goto(base+`/manager/record-refund.php?id=${reg.id}`);
                await post(student,`/manager/record-refund.php?id=${reg.id}`,{amount:'15',reason:'Peer recorded synthetic transfer'});
                assert.equal(sql('SELECT status FROM registrations WHERE id=?',[reg.id])[0].status,'cancelled');
            } finally {
                sql('DELETE FROM club_managers WHERE club_id=? AND user_id=?',[f.club,f.users.student.id]);
            }
        });
        await check('organiser cancellation queues paid refunds and releases unpaid/free places', async () => {
            await outsider.goto(base+`/session-detail.php?id=${f.sessions.paid}`);
            await post(outsider,'/reserve.php',{session_id:f.sessions.paid});
            const paid=JSON.parse(fixture('read'));
            const unpaid=sql('SELECT * FROM registrations WHERE session_id=? AND user_id=?',[f.sessions.paid,f.users.outsider.id])[0];
            assert.equal(paid.status,'confirmed');
            assert.equal(unpaid.status,'awaiting_payment');
            await manager.goto(base+`/manager/cancel-session.php?id=${f.sessions.paid}`);
            await post(manager,`/manager/cancel-session.php?id=${f.sessions.paid}`,{cancellation_reason:'Synthetic court closure'});
            assert.equal(JSON.parse(fixture('read')).status,'cancellation_pending');
            assert.equal(sql('SELECT status FROM registrations WHERE id=?',[unpaid.id])[0].status,'cancelled');
            assert.equal(sql('SELECT decision FROM payment_receipts WHERE registration_id=? ORDER BY id DESC',[paid.id])[0].decision,'approved');
            const free=sql('SELECT * FROM sessions WHERE id=?',[f.sessions.free])[0];
            await manager.goto(base+`/manager/session-edit.php?id=${free.id}`);
            await post(manager,`/manager/session-edit.php?id=${free.id}`,{club_id:free.club_id,title:free.title,sport_type:free.sport_type,venue:free.venue,session_date:free.session_date,start_time:free.start_time,end_time:free.end_time,price:'0',capacity:free.capacity,description:free.description,image_path:'',action:'publish'});
            await student.goto(base+`/session-detail.php?id=${free.id}`);
            await post(student,'/reserve.php',{session_id:free.id});
            const joined=sql('SELECT * FROM registrations WHERE session_id=? AND user_id=?',[free.id,f.users.student.id])[0];
            assert.equal(joined.status,'confirmed');
            await manager.goto(base+`/manager/cancel-session.php?id=${free.id}`);
            await post(manager,`/manager/cancel-session.php?id=${free.id}`,{cancellation_reason:'Synthetic free session closure'});
            assert.equal(sql('SELECT status FROM registrations WHERE id=?',[joined.id])[0].status,'cancelled');
        });
    } finally {
        try { if (browser) await browser.close(); }
        finally { console.log(fixture('cleanup')); }
    }
    if (failures) process.exitCode=1;
})().catch(e=>{console.error(e);process.exitCode=1;});
