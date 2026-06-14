/**
 * Flutterwave Captcha Auto-Solver
 * Accepts an already-generated redirect URL and solves the math captcha headlessly.
 * Called by SolveFLWCaptchaJob.php as a subprocess.
 *
 * Usage:
 *   node scripts/flw-solve-captcha.mjs <redirect_url> [tx_ref]
 *
 * Exit codes:
 *   0 = captcha solved, USSD push triggered
 *   1 = failed (captcha not found / wrong answer / timeout)
 */

import puppeteer from 'puppeteer';

const redirectUrl = process.argv[2];
const txRef       = process.argv[3] ?? 'unknown';

if (!redirectUrl || !redirectUrl.startsWith('http')) {
  console.error(JSON.stringify({ ok: false, error: 'No redirect URL provided' }));
  process.exit(1);
}

(async () => {
  let browser;
  try {
    browser = await puppeteer.launch({
      headless: true,
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-seccomp-filter-sandbox',  // needed on AlmaLinux shared hosting
        '--disable-dev-shm-usage',
        '--disable-gpu',
        '--single-process',                  // avoids renderer subprocess crash on restricted hosts
        '--no-zygote',
        '--disable-crash-reporter',
        '--disable-extensions',
        '--disable-background-networking',
        '--disable-default-apps',
        '--no-first-run',
        '--disable-features=site-per-process',
        '--memory-pressure-off',
      ],
    });

    // Use browser.pages()[0] (the already-open blank tab) rather than newPage().
    // On single-process/no-zygote setups, newPage() can race with renderer init.
    const existingPages = await browser.pages();
    const page = existingPages.length > 0 ? existingPages[0] : await browser.newPage();

    await page.setUserAgent(
      'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
    );
    await page.setViewport({ width: 1280, height: 800 });

    // Abort image/font/media requests to load faster
    await page.setRequestInterception(true);
    page.on('request', req => {
      const type = req.resourceType();
      if (['image', 'font', 'media', 'stylesheet'].includes(type)) {
        req.abort();
      } else {
        req.continue();
      }
    });

    await page.goto(redirectUrl, { waitUntil: 'domcontentloaded', timeout: 20000 });

    // Inject auto-solver and wait for it to complete
    const result = await page.evaluate(async () => {
      function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

      // Wait for page JS to populate the math question (replaces {{ question }})
      let questionEl, questionText;
      for (let i = 0; i < 40; i++) {
        questionEl  = document.getElementById('math-captcha-question');
        questionText = questionEl?.textContent?.trim() ?? '';
        if (questionText && !questionText.includes('{{') && questionText.length > 2) break;
        await sleep(250);
      }

      if (!questionText || questionText.includes('{{')) {
        return { ok: false, error: 'Math question did not appear', questionText };
      }

      // Parse: "12 + 7 = ?", "8 - 3", "4 × 6", "4 x 6", "15 ÷ 3"
      const match = questionText.match(/(\d+)\s*([+\-×x*÷/])\s*(\d+)/);
      if (!match) {
        return { ok: false, error: `Cannot parse: "${questionText}"` };
      }

      const a  = parseInt(match[1]);
      const op = match[2];
      const b  = parseInt(match[3]);
      let answer;

      if      (op === '+')                              answer = a + b;
      else if (op === '-')                              answer = a - b;
      else if (op === '×' || op === 'x' || op === '*') answer = a * b;
      else if (op === '÷' || op === '/')                answer = Math.floor(a / b);
      else return { ok: false, error: `Unknown operator: "${op}"` };

      // Fill input using native setter (avoids React/Vue state issues)
      const input = document.getElementById('math-captcha-input');
      if (!input) return { ok: false, error: 'Input not found' };

      const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set;
      setter ? setter.call(input, String(answer)) : (input.value = String(answer));

      input.dispatchEvent(new Event('input',  { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
      input.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true }));

      await sleep(700);

      // Submit
      const btn = document.getElementById('math-captcha-submit');
      if (btn) {
        btn.removeAttribute('disabled');
        btn.click();
      } else {
        document.getElementById('math-captcha-form')?.submit();
      }

      return { ok: true, question: questionText, answer, a, op, b };
    });

    // Allow 2s for the submit to register before closing browser
    await new Promise(r => setTimeout(r, 2000));
    await browser.close();

    if (result.ok) {
      console.log(JSON.stringify({
        ok: true,
        tx_ref: txRef,
        question: result.question,
        answer: result.answer,
      }));
      process.exit(0);
    } else {
      console.error(JSON.stringify({ ok: false, tx_ref: txRef, error: result.error }));
      process.exit(1);
    }

  } catch (err) {
    try { await browser?.close(); } catch {}
    console.error(JSON.stringify({ ok: false, tx_ref: txRef, error: err.message }));
    process.exit(1);
  }
})();
