/* Run only against the isolated Docker test site. See docs/TESTING.md. */
const { chromium } = require('playwright-core');
const { AxeBuilder } = require('@axe-core/playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const container = process.env.GHR_TEST_CONTAINER || 'ghr-wp';
const base = process.env.GHR_TEST_URL || 'http://localhost:8086';
const out = process.env.GHR_TEST_OUTPUT || '/workspace/ghr-dev/browser';
const wp = (...args) => execFileSync('docker',['exec',container,'php','/usr/local/bin/wp','--allow-root',...args],{encoding:'utf8'}).trim();
(async()=>{
 const fixture=JSON.parse(wp('eval-file','/var/www/html/wp-content/plugins/ghoroa-hat-rewards/tests/browser-fixtures.php'));
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',headless:true,args:['--no-sandbox']});
 const context=await browser.newContext({viewport:{width:1440,height:1000}});const page=await context.newPage();const errors=[];const results=[];page.on('pageerror',e=>errors.push(e.message));
 try {
  await page.goto(base+'/wp-login.php');await page.fill('#user_login',fixture.username);await page.fill('#user_pass','ghr-e2e-local-only');await page.click('#wp-submit');await page.waitForLoadState('networkidle');
  for(const slug of ['gh-membership','gh-trust-wallet','gh-referrals','gh-trust-seeds','gh-rewards','gh-notifications']){
   const response=await page.goto(base+'/my-account/'+slug+'/');await page.waitForLoadState('networkidle');if(response.status()!==200||!await page.locator('.ghr').count())throw Error('Endpoint failed '+slug);results.push({endpoint:slug,status:response.status()});
  }
  await page.goto(base+'/my-account/gh-membership/');const axe=await new AxeBuilder({page}).include('.ghr').analyze();if(axe.violations.length)throw Error('Accessibility: '+axe.violations.map(v=>v.id));results.push({accessibility:'No automated axe violations in membership portal'});await page.screenshot({path:out+'/membership.png',fullPage:true});await page.setViewportSize({width:390,height:844});if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth))throw Error('Mobile horizontal overflow');await page.screenshot({path:out+'/mobile.png',fullPage:true});await page.setViewportSize({width:1440,height:1000});
  for(const mode of ['blocks','classic']){
   wp('option','update','woocommerce_checkout_page_id',String(fixture[mode]));
   // Clear the previous cart through the WooCommerce cart session before adding a fresh item.
   await page.goto(base+'/?add-to-cart='+fixture.product);await page.waitForLoadState('networkidle');
   await page.goto(base+'/?page_id='+fixture[mode]);await page.waitForLoadState('networkidle');await page.locator('.ghr .ghr-checkout-row').first().waitFor();
   const amount=page.locator('.ghr .ghr-checkout-row input').first();await amount.fill('100');await page.locator('.ghr .ghr-checkout-row button').first().click();
   await page.waitForTimeout(1200);await page.waitForLoadState('networkidle');
   const state=await page.evaluate(()=>window.ghrRequest('checkout',undefined,'GET'));if(state.reservation?.state!=='reserved'||Number(state.reservation.units)!==10000)throw Error(mode+' did not reserve exactly 100 BDT');results.push({checkout:mode,reservation:'100 BDT reserved through server'});
   if(mode==='blocks'){
    await page.getByRole('button',{name:'Place Order',exact:true}).click();
   }else{
    await page.locator('#place_order').click();
   }
   await page.waitForURL(/order-received/, {timeout:30000});results.push({checkout:mode,order:'COD order accepted and thank-you page loaded'});await page.screenshot({path:out+'/'+mode+'-order.png',fullPage:true});
  }
  // Admin request handling, settings persistence and report permissions use real wp-admin forms.
  await context.clearCookies();await page.goto(base+'/wp-login.php');await page.fill('#user_login','ghradmin');await page.fill('#user_pass','ghr-local-admin-test');await page.click('#wp-submit');await page.waitForLoadState('networkidle');
  await page.goto(base+'/wp-admin/admin.php?page=ghr-settings');await page.locator('input[name="settings[label_trust]"]').fill('ট্রাস্ট ওয়ালেট');await page.getByRole('button',{name:'Save validated settings'}).click();await page.waitForURL(/saved=1/);results.push({admin:'Validated settings saved through native WordPress nonce-protected form'});
  for(const screen of ['members','trust','referrals','seeds','leaderboard','rewards','notifications','reports','audit','health']){const response=await page.goto(base+'/wp-admin/admin.php?page=ghr-'+screen);if(response.status()!==200)throw Error('Admin screen failed '+screen);results.push({admin:screen,status:200});}
  if(errors.length)throw Error('JavaScript errors: '+errors.join('; '));
  console.log(JSON.stringify({passed:true,results,errors},null,2));fs.writeFileSync(out+'/e2e-results.json',JSON.stringify({passed:true,results,errors},null,2));
 } finally {wp('option','update','woocommerce_checkout_page_id',String(fixture.original_checkout));await browser.close();}
})().catch(error=>{console.error(error);process.exit(1)});
