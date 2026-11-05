// NODE_PATH must expose playwright and @axe-core/playwright (test tools only).
// Run: node tests/ux-journey.cjs [--probe]
const {chromium} = require('playwright');
const {AxeBuilder} = require('@axe-core/playwright');
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const base = process.env.SPORTSHUB_URL || 'http://localhost/MyProject/27TUSportsHub';
const out = path.join(__dirname,'../docs/ux-evidence');
const fixture = (...args) => execFileSync(process.env.PHP_BINARY || 'php',[path.join(__dirname,'ux-fixtures.php'),...args],{encoding:'utf8'}).trim();
const read = () => JSON.parse(fixture('read'));
let png;
(async()=>{
 fs.mkdirSync(out,{recursive:true});
 const f=JSON.parse(fixture('create'));
 const browser=await chromium.launch({channel:process.env.BROWSER_CHANNEL || 'chrome',headless:true});
 const specimenContext=await browser.newContext({viewport:{width:620,height:300}});
 const specimen=await specimenContext.newPage();
 await specimen.setContent('<div style="font:18px/1.6 system-ui;padding:24px;background:#f5f5f7"><h1 style="font-size:26px;color:#b20820">SYNTHETIC RECEIPT</h1><p>Test artwork · Not proof of settlement</p><p>Amount: RM 15.00<br>Reference: DEMO-UX-ONLY<br>No real transfer occurred</p></div>');
 png=await specimen.screenshot(); await specimenContext.close();
 const checks=[],errors=[],audits=[];
 const check=(label,value)=>{assert.ok(value,label);checks.push(label);};
 let student,manager,outsider;
 const login=async(role)=>{
  const context=await browser.newContext({viewport:{width:1440,height:1000}});
  const page=await context.newPage(); page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/login.php');
  await page.locator('[name=email]').fill(f.users[role].email);
  await page.locator('[name=password]').fill('UxFixture123!');
  await Promise.all([page.waitForURL(u=>!u.pathname.endsWith('login.php')),page.locator('button[type=submit]').click()]);
  return page;
 };
 const post=async(page,url,data={},file)=>{
  const token=await page.locator('[name=csrf_token]').first().inputValue();
  const response=await page.request.post(base+url,file ? {multipart:{csrf_token:token,...data,receipt:{name:'synthetic.png',mimeType:'image/png',buffer:png}}} : {form:{csrf_token:token,...data}});
  return response;
 };
 const go=async(page,url)=>{await page.goto(base+url);};
 const clickSubmit=async(page,selector)=>{await Promise.all([page.waitForNavigation(),page.locator(selector).click()]);};
 const capture=async(page,name)=>{
  for(const width of [1440,390]) {
   await page.setViewportSize({width,height:1000});
   await page.screenshot({path:path.join(out,`after-${name}-${width}.png`),fullPage:true});
   check(`${name} ${width}px no page overflow`,await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
   const axe=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
   audits.push({name,width,violations:axe.violations.map(v=>({id:v.id,impact:v.impact,nodes:v.nodes.map(n=>n.target)}))});
  }
 };
 try {
  student=await login('student'); manager=await login('manager'); outsider=await login('outsider');
  await go(student,`/session-detail.php?id=${f.sessions.paid}`);
  await clickSubmit(student,'form[action$="reserve.php"] button');
  let r=read(); check('Paid reserve creates one awaiting-payment hold',r.status==='awaiting_payment');
  const rid=r.id;
  fixture('bank-change');
  await go(student,`/payment.php?id=${rid}&msg=invalid_file_type`);
  if(process.argv.includes('--probe')) {
   const probes={
    'Keyboard-accessible native upload':await student.locator('input[type=file]').evaluate(el=>getComputedStyle(el).display!=='none'),
    'Inline upload recovery':await student.locator('[role=alert]').count()>0,
    'Snapshot instructions preserved':!(await student.locator('main').innerText()).includes('CHANGED-AFTER-RESERVATION'),
    'Primary button styled':await student.locator('button[type=submit]').last().evaluate(el=>getComputedStyle(el).backgroundColor!=='rgb(239, 239, 239)'),
   };
   console.log(JSON.stringify(probes,null,2));
   assert.ok(Object.values(probes).every(Boolean),'Baseline UX regressions reproduced');
   return;
  }
  check('Reservation deadline uses MYT',await student.locator('main').innerText().then(t=>t.includes('MYT')));
  check('Snapshot instructions survive club bank change',!(await student.locator('main').innerText()).includes('CHANGED-AFTER-RESERVATION'));
  check('Upload failure appears inline',await student.locator('[role=alert]').count()>0);
  await capture(student,'payment');
  await student.locator('#receipt').setInputFiles({name:'bad.txt',mimeType:'text/plain',buffer:Buffer.from('not an image')});
  check('Invalid file gives persistent field error',await student.locator('#receipt-error').innerText().then(t=>t.includes('JPG')));
  await go(student,`/payment.php?id=${rid}`);
  await student.locator('#receipt').setInputFiles({name:'synthetic.png',mimeType:'image/png',buffer:png});
  await clickSubmit(student,'button[type=submit][data-pending-label]');
  check('Actual upload moves hold to review',read().status==='awaiting_approval');
  check('Under-review UI does not demand another payment',!(await student.locator('main').innerText()).includes('Amount due'));
  check('Under-review UI has no upload',await student.locator('#receipt').count()===0);
  await capture(student,'under-review');
  await go(outsider,`/payment.php?id=${rid}`); check('Other student cannot view registration',await outsider.locator('h1').innerText().then(t=>t.includes('Access')||t.includes('403')));
  const evidence=read().receipt_path;
  check('Direct upload directory is private',(await outsider.request.get(base+'/'+evidence)).status()===403);
  check('Other student cannot read evidence',(await outsider.request.get(base+`/evidence.php?registration_id=${rid}`)).status()===403);
  check('Unappointed student cannot review receipts',(await outsider.request.get(base+`/manager/review-receipt.php?id=${rid}`)).status()===403);
  await go(manager,`/manager/review-receipt.php?id=${rid}`);
  await capture(manager,'review');
  await go(manager,`/manager/participants.php?session_id=${f.sessions.paid}`); await capture(manager,'participants');
  await go(manager,`/manager/review-receipt.php?id=${rid}`);
  await manager.locator('[name=rejection_reason]').fill('Synthetic reference is not legible');
  await clickSubmit(manager,'button[value=reject]');
  check('Real rejection preserves reason and new hold',read().status==='payment_rejected'&&read().rejection_reason.includes('not legible'));
  await go(student,`/payment.php?id=${rid}`);
  check('Rejection shows actual reason',await student.locator('main').innerText().then(t=>t.includes('not legible')));
  await capture(student,'rejected');
  await post(student,'/upload-receipt.php',{registration_id:rid},true);
  check('Resubmission returns to review',read().status==='awaiting_approval');
  await go(manager,`/manager/review-receipt.php?id=${rid}`);
  await clickSubmit(manager,'button[value=approve]');
  check('Real manager approval confirms registration',read().status==='confirmed');
  await go(student,`/payment.php?id=${rid}`); await capture(student,'confirmed');
  await go(student,`/cancel-registration.php?id=${rid}`);
  await student.locator('[name=recipient_bank_name]').fill('DEMO BANK');
  await student.locator('[name=recipient_account_name]').fill('SYNTHETIC RECIPIENT');
  await student.locator('[name=recipient_account_number]').fill('DEMO-NOT-AN-ACCOUNT');
  await clickSubmit(student,'main button[type=submit]');
  check('Paid cancellation stays pending',read().status==='cancellation_pending');
  await capture(student,'activities-refund');
  await go(student,`/payment.php?id=${rid}`); await capture(student,'refund-pending');
  await go(manager,`/manager/record-refund.php?id=${rid}`);
  await capture(manager,'record-refund');
  const refund=async(amount)=>{await post(manager,`/manager/record-refund.php?id=${rid}`,{amount,reason:'Synthetic external transfer recorded'});};
  await refund('5.00');
  check('Partial refund keeps hold',read().status==='cancellation_pending'&&Number(read().refunded)===5);
  await go(student,`/payment.php?id=${rid}`); check('Student sees remaining refund',await student.locator('main').innerText().then(t=>t.includes('RM 10.00')));
  await go(manager,`/manager/record-refund.php?id=${rid}`); await refund('10.00');
  check('Full refund completes cancellation',read().status==='cancelled'&&Number(read().refunded)===15);
  await go(student,`/payment.php?id=${rid}`); await capture(student,'refund-complete');
  await go(manager,`/manager/review-receipt.php?id=${rid}`); await post(manager,`/manager/review-receipt.php?id=${rid}`,{decision:'approve'});
  check('Stale approval cannot resurrect cancelled registration',read().status==='cancelled');
  await go(manager,`/manager/record-refund.php?id=${rid}`); await refund('1');
  check('Completed refund cannot be recorded twice',Number(read().refunded)===15);
  // A new synthetic unpaid hold for boundary checks; no seed data modified.
  fixture('set','awaiting_payment'); fixture('expire');
  await go(student,`/payment.php?id=${rid}`); check('Deadline expiry releases hold',read().status==='expired');
  check('Expired page has recovery link',await student.locator('main a[href*="session-detail"]').count()>0);
  await capture(student,'expired');
  fixture('set','awaiting_payment');
  await go(manager,`/manager/cancel-session.php?id=${f.sessions.paid}`);
  await post(manager,`/manager/cancel-session.php?id=${f.sessions.paid}`,{cancellation_reason:'Synthetic court closure'});
  await go(student,`/payment.php?id=${rid}`);
  check('Cancelled session blocks upload UI',await student.locator('#receipt').count()===0);
  await post(student,'/upload-receipt.php',{registration_id:rid},true);
  check('Cancelled session blocks direct upload',read().status!=='awaiting_approval');
  await go(manager,`/manager/close-signup.php?id=${f.sessions.paid}`);
  await post(manager,`/manager/close-signup.php?id=${f.sessions.paid}`);
  check('Stale close-signup cannot change cancelled session',fixture('session-state')==='cancelled');
  await go(student,`/session-detail.php?id=${f.sessions.free}`);
  await clickSubmit(student,'form[action$="reserve.php"] button');
  check('Free join confirms with no payment form',await student.locator('main').innerText().then(t=>t.includes('Confirmed')));
  await go(student,'/my-activities.php'); await capture(student,'activities');
  // Cancellation during settlement review must not be mistaken for an approved payment.
  await go(student,`/session-detail.php?id=${f.sessions.review}`);
  await clickSubmit(student,'form[action$="reserve.php"] button');
  const reviewRead=()=>JSON.parse(fixture('review-read'));
  const reviewId=reviewRead().id;
  await go(manager,`/manager/close-signup.php?id=${f.sessions.review}`);
  await post(manager,`/manager/close-signup.php?id=${f.sessions.review}`);
  await post(student,'/upload-receipt.php',{registration_id:reviewId},true);
  check('Existing receipt window survives manual closure',reviewRead().status==='awaiting_approval');
  await go(student,`/cancel-registration.php?id=${reviewId}`);
  await post(student,`/cancel-registration.php?id=${reviewId}`,{recipient_bank_name:'DEMO',recipient_account_name:'SYNTHETIC',recipient_account_number:'NOT-REAL'});
  check('Cancellation during review retains settlement review',reviewRead().status==='awaiting_approval'&&!!reviewRead().cancellation_requested_at);
  await go(manager,`/manager/review-receipt.php?id=${reviewId}`);
  check('Manager sees cancellation settlement choice',await manager.locator('main').innerText().then(t=>t.includes('Payment received — queue refund')));
  await clickSubmit(manager,'button[value=approve]');
  check('Verified payment on cancelled review queues refund',reviewRead().status==='cancellation_pending');
  // Timely evidence may still be reviewed after start; new uploads cannot.
  fixture('review-start');
  await go(student,`/payment.php?id=${reviewId}`);
  check('Post-start paid cancellation stays pending',reviewRead().status==='cancellation_pending');
  fixture('review-reset');
  await go(manager,`/manager/review-receipt.php?id=${reviewId}`);
  await clickSubmit(manager,'button[value=approve]');
  check('Timely receipt can be approved after start',reviewRead().status==='confirmed');
  fixture('review-reset');
  await go(manager,`/manager/review-receipt.php?id=${reviewId}`);
  await manager.locator('[name=rejection_reason]').fill('Synthetic post-start rejection');
  await clickSubmit(manager,'button[value=reject]');
  await go(student,`/payment.php?id=${reviewId}`);
  check('Post-start rejection expires without upload option',reviewRead().status==='expired'&&await student.locator('#receipt').count()===0);
  // Competing final-place HTTP requests use independent PHP sessions.
  fixture('race-capacity');
  await go(student,`/session-detail.php?id=${f.sessions.race}`);
  await go(outsider,`/session-detail.php?id=${f.sessions.race}`);
  await Promise.all([post(student,'/reserve.php',{session_id:f.sessions.race}),post(outsider,'/reserve.php',{session_id:f.sessions.race})]);
  check('Concurrent final-place requests yield one hold',Number(fixture('race-read'))===1);
  await Promise.all([post(student,'/reserve.php',{session_id:f.sessions.race}),post(outsider,'/reserve.php',{session_id:f.sessions.race})]);
  check('Reserve retries cannot create another place',Number(fixture('race-read'))===1);
  await go(student,'/my-activities.php');
  await student.setViewportSize({width:1440,height:1000});
  await student.locator('.user-account .user-pill').focus();await student.keyboard.press('Enter');
  check('Account button opens menu by keyboard',await student.locator('#account-menu').isVisible());
  await student.keyboard.press('Escape');
  check('Account menu Escape closes and restores focus',await student.locator('.user-account .user-pill').evaluate(el=>document.activeElement===el&&el.getAttribute('aria-expanded')==='false'));
  // Keyboard checks with an actual browser input sequence.
  await student.setViewportSize({width:390,height:844});
  await student.locator('.mobile-menu-btn').focus(); await student.keyboard.press('Enter');
  check('Drawer receives keyboard focus',await student.locator('.mobile-drawer-close').evaluate(el=>el===document.activeElement));
  await student.keyboard.press('Shift+Tab');
  check('Drawer traps focus',await student.locator('.mobile-drawer').evaluate(el=>el.contains(document.activeElement)));
  await student.keyboard.press('Escape');
  check('Escape restores focus and expanded state',await student.locator('.mobile-menu-btn').evaluate(el=>el===document.activeElement&&el.getAttribute('aria-expanded')==='false'));
  await student.emulateMedia({reducedMotion:'reduce'});
  check('Reduced motion suppresses drawer travel',await student.locator('.mobile-drawer').evaluate(el=>parseFloat(getComputedStyle(el).transitionDuration)<0.01));
  await student.setViewportSize({width:720,height:500});
  check('200% equivalent viewport reflows',await student.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  check('No browser JS errors',errors.length===0);
  fs.writeFileSync(path.join(out,'after-audit.json'),JSON.stringify({checks,audits,errors},null,2));
  check('Axe finds no violations across captured journey states',audits.every(a=>a.violations.length===0));
  console.log(JSON.stringify({passed:checks.length,axeViolations:audits.reduce((n,a)=>n+a.violations.length,0),checks,audits,errors},null,2));
 } finally { await browser.close(); console.log(fixture('cleanup')); }
})().catch(e=>{console.error(e);process.exitCode=1;});
