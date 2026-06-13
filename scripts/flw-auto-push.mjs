/**
 * Flutterwave MoMo Direct Push — Auto-solve math captcha, trigger USSD
 *
 * Usage:
 *   node scripts/flw-auto-push.mjs <phone> <amount> [network]
 *   node scripts/flw-auto-push.mjs 0761103575 1000 MTN
 *
 * What it does:
 *   1. Calls the backend charge API → gets redirect URL
 *   2. Opens the Flutterwave captcha page in a headless browser
 *   3. Reads the math question, calculates answer, auto-submits
 *   4. USSD is pushed to the phone (no user action needed on screen)
 *   5. Polls until payment confirmed, then prints result
 */

import puppeteer from 'puppeteer';
import https from 'https';
import fs from 'fs';

// ── Config ──────────────────────────────────────────────────────────────────
const FLW_SECRET_KEY = process.env.FLW_SECRET_KEY || readEnv('FLW_SECRET_KEY');
const FLW_API        = 'https://api.flutterwave.com/v3';

const phone   = process.argv[2] || '0761103575';
const amount  = parseInt(process.argv[3] || '1000');
const network = (process.argv[4] || detectNetwork(normalizePhone(phone))).toUpperCase();

// ── Main ─────────────────────────────────────────────────────────────────────
(async () => {
  if (!FLW_SECRET_KEY) {
    console.error('❌  FLW_SECRET_KEY not found. Set it in .env or as env var.');
    process.exit(1);
  }

  const normalPhone = normalizePhone(phone);
  const txRef = `KTG-PUSH-${randomHex(6).toUpperCase()}-${Date.now()}`;

  log('info', `Phone   : ${normalPhone}`);
  log('info', `Network : ${network}`);
  log('info', `Amount  : UGX ${amount.toLocaleString()}`);
  log('info', `tx_ref  : ${txRef}`);
  log('info', '');

  // ── Step 1: Initiate charge ──────────────────────────────────────────────
  log('step', '[1/4] Initiating charge with Flutterwave...');

  const chargePayload = {
    phone_number: normalPhone,
    network,
    amount,
    currency: 'UGX',
    email: 'test@mruodel.com',
    fullname: 'Test User',
    tx_ref: txRef,
    redirect_url: 'https://api.mruodel.com/flw-callback',
    meta: { test: true, source: 'auto-push-script' },
  };

  const chargeResp = await flwPost('/charges?type=mobile_money_uganda', chargePayload);

  if (chargeResp.status !== 'success') {
    log('error', `Charge failed: ${chargeResp.message}`);
    process.exit(1);
  }

  const redirectUrl = chargeResp.meta?.authorization?.redirect;
  const mode        = chargeResp.meta?.authorization?.mode;

  if (!redirectUrl) {
    log('error', 'No redirect URL in response.');
    console.log(JSON.stringify(chargeResp, null, 2));
    process.exit(1);
  }

  log('ok',   `Charge initiated — mode: ${mode}`);
  log('info', `Redirect URL: ${redirectUrl}`);
  log('info', '');

  // ── Step 2: Open headless browser, auto-solve math captcha ──────────────
  log('step', '[2/4] Opening captcha page in headless browser...');

  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  const page = await browser.newPage();

  // Mimic a real Android mobile browser
  await page.setUserAgent(
    'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Mobile Safari/537.36'
  );
  await page.setViewport({ width: 390, height: 844, isMobile: true });

  let ussdTriggered = false;
  let captchaSolved = false;

  // Monitor console logs from the page (for debugging)
  page.on('console', msg => {
    if (msg.type() === 'error') log('warn', `[page error] ${msg.text()}`);
  });

  // Monitor network requests to detect when captcha is submitted
  page.on('request', req => {
    const url = req.url();
    if (url.includes('/captcha/verify') && req.method() === 'POST') {
      log('ok', `Math captcha submitted → ${url}`);
      captchaSolved = true;
    }
  });

  // Monitor navigation to detect USSD trigger / redirect
  page.on('framenavigated', frame => {
    if (frame === page.mainFrame()) {
      const url = frame.url();
      if (!url.includes('captcha/verify') && url !== 'about:blank') {
        log('ok', `Page navigated → ${url}`);
        if (url.includes('mruodel.com') || url.includes('flutterwave')) {
          ussdTriggered = true;
        }
      }
    }
  });

  await page.goto(redirectUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });

  log('info', `Page title: "${await page.title()}"`);
  log('step', '[3/4] Auto-solving math captcha...');

  // Wait for page JS to populate the math question, then solve it
  const solved = await page.evaluate(async () => {
    function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

    // Poll until the math question element is populated by page JS
    let questionEl, questionText;
    for (let i = 0; i < 30; i++) {
      questionEl = document.getElementById('math-captcha-question');
      questionText = questionEl?.textContent?.trim() ?? '';
      // Page JS replaces {{ question }} with the actual question
      if (questionText && !questionText.includes('{{') && questionText.length > 2) break;
      await sleep(300);
    }

    if (!questionText || questionText.includes('{{')) {
      return { ok: false, error: 'Math question not found after 9 seconds', questionText };
    }

    // Parse math: supports +, -, ×, x, *, ÷, /
    const match = questionText.match(/(\d+)\s*([+\-×x*÷/])\s*(\d+)/);
    if (!match) {
      return { ok: false, error: `Cannot parse question: "${questionText}"` };
    }

    const a  = parseInt(match[1]);
    const op = match[2];
    const b  = parseInt(match[3]);
    let answer;

    if      (op === '+')                              answer = a + b;
    else if (op === '-')                              answer = a - b;
    else if (op === '×' || op === 'x' || op === '*') answer = a * b;
    else if (op === '÷' || op === '/')                answer = Math.floor(a / b);
    else return { ok: false, error: `Unknown operator: ${op}` };

    // Fill the input
    const input = document.getElementById('math-captcha-input');
    if (!input) return { ok: false, error: 'Input #math-captcha-input not found' };

    // Use native value setter to work with any framework
    const nativeSetter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set;
    if (nativeSetter) {
      nativeSetter.call(input, String(answer));
    } else {
      input.value = String(answer);
    }

    input.dispatchEvent(new Event('input',  { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
    input.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true }));

    await sleep(600);

    // Click submit
    const submitBtn = document.getElementById('math-captcha-submit');
    if (submitBtn) {
      submitBtn.removeAttribute('disabled');
      submitBtn.click();
    } else {
      const form = document.getElementById('math-captcha-form');
      if (form) form.submit();
    }

    return { ok: true, question: questionText, answer, a, op, b };
  });

  if (!solved.ok) {
    log('error', `Auto-solve failed: ${solved.error}`);
    await browser.close();
    process.exit(1);
  }

  log('ok',   `Math solved: "${solved.question}" → answer: ${solved.answer}`);

  // Wait a moment for the page to react
  await new Promise(r => setTimeout(r, 3000));

  // Check what happened after captcha submission
  const currentUrl  = page.url();
  const currentTitle = await page.title().catch(() => '');
  log('info', `Page after captcha: "${currentTitle}" — ${currentUrl}`);

  await browser.close();

  log('info', '');
  log('step', '[4/4] Math captcha solved — USSD push should have fired to your phone.');
  log('info', '');
  log('info', '  🔔  Check your phone now for an MTN MoMo / Airtel Money USSD prompt');
  log('info', '  📱  Enter your PIN when it appears');
  log('info', '');
  log('info', `  tx_ref: ${txRef}`);
  log('info', '');

  // ── Step 3: Poll for payment confirmation ───────────────────────────────
  await pollVerification(txRef, amount);
})();

// ── Helpers ──────────────────────────────────────────────────────────────────

async function pollVerification(txRef, expectedAmount, maxAttempts = 25) {
  log('info', '  Polling for payment confirmation (every 8 seconds, up to ~3 min)...');
  log('info', '  (Enter your PIN on the phone, then wait)');
  log('info', '');

  for (let i = 1; i <= maxAttempts; i++) {
    await new Promise(r => setTimeout(r, 8000));
    process.stdout.write(`  [${i}/${maxAttempts}] Checking... `);

    try {
      const result = await flwGet(`/transactions/verify_by_reference?tx_ref=${encodeURIComponent(txRef)}`);
      const data   = result.data ?? {};
      const status = (data.status ?? '').toLowerCase();

      if (['successful', 'success', 'completed'].includes(status)) {
        console.log('✅');
        log('info', '');
        log('info',  '  ┌────────────────────────────────────┐');
        log('info',  '  │   ✓  PAYMENT CONFIRMED!             │');
        log('info',  '  └────────────────────────────────────┘');
        printVerification(data);
        return;
      } else if (status === 'failed') {
        console.log('❌');
        log('error', 'Payment FAILED or was declined.');
        printVerification(data);
        return;
      } else if (status === 'pending') {
        console.log('⏳ pending');
      } else {
        console.log('⏳ not confirmed yet');
      }
    } catch (e) {
      console.log(`⚠ ${e.message}`);
    }
  }

  log('warn', '\n  Polling timed out. Verify manually:');
  log('info', `  node scripts/flw-auto-push.mjs --verify ${txRef}`);
}

function printVerification(data) {
  log('info', '');
  log('info', `  Amount    : ${data.currency} ${Number(data.amount).toLocaleString()}`);
  log('info', `  Settled   : ${data.currency} ${Number(data.amount_settled).toLocaleString()}`);
  log('info', `  FLW ref   : ${data.flw_ref}`);
  log('info', `  tx_ref    : ${data.tx_ref}`);
  log('info', `  Type      : ${data.payment_type}`);
  log('info', `  Customer  : ${data.customer?.name} / ${data.customer?.phone_number}`);
  log('info', '');
}

function flwPost(path, body) {
  return new Promise((resolve, reject) => {
    const payload = JSON.stringify(body);
    const req = https.request(
      { hostname: 'api.flutterwave.com', path: `/v3${path}`, method: 'POST',
        headers: { 'Authorization': `Bearer ${FLW_SECRET_KEY}`, 'Content-Type': 'application/json', 'Accept': 'application/json', 'Content-Length': Buffer.byteLength(payload) }
      },
      res => {
        let data = '';
        res.on('data', chunk => data += chunk);
        res.on('end', () => { try { resolve(JSON.parse(data)); } catch { resolve({ status: 'error', message: data }); } });
      }
    );
    req.on('error', reject);
    req.write(payload);
    req.end();
  });
}

function flwGet(path) {
  return new Promise((resolve, reject) => {
    const req = https.request(
      { hostname: 'api.flutterwave.com', path: `/v3${path}`, method: 'GET',
        headers: { 'Authorization': `Bearer ${FLW_SECRET_KEY}`, 'Accept': 'application/json' }
      },
      res => {
        let data = '';
        res.on('data', chunk => data += chunk);
        res.on('end', () => { try { resolve(JSON.parse(data)); } catch { resolve({ status: 'error', message: data }); } });
      }
    );
    req.on('error', reject);
    req.end();
  });
}

function normalizePhone(raw) {
  const digits = raw.replace(/\D/g, '');
  if (digits.startsWith('256') && digits.length >= 12) return digits.slice(0, 12);
  if (digits.startsWith('0') && digits.length === 10) return '256' + digits.slice(1);
  if (digits.length === 9) return '256' + digits;
  return digits;
}

function detectNetwork(phone) {
  const prefix = phone.slice(3, 5);
  const airtel = ['20','70','71','74','75'];
  return airtel.includes(prefix) ? 'AIRTEL' : 'MTN';
}

function randomHex(n) {
  return [...Array(n)].map(() => Math.floor(Math.random()*16).toString(16)).join('');
}

function readEnv(key) {
  try {
    const env = fs.readFileSync('/Applications/MAMP/htdocs/katogo/.env', 'utf8');
    const match = env.match(new RegExp(`^${key}=(.+)$`, 'm'));
    return match?.[1]?.trim() ?? null;
  } catch { return null; }
}

function log(type, msg) {
  const prefix = { info: '  ', step: '\n  🔹', ok: '  ✅', error: '  ❌', warn: '  ⚠️ ' }[type] ?? '  ';
  console.log(`${prefix} ${msg}`);
}
