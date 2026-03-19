#!/usr/bin/env python3
"""Generate LugaFlix A4 Poster — v3 Big, Bold, Red Accent"""

import os, base64, io, qrcode
from playwright.sync_api import sync_playwright

OUTPUT_PDF = os.path.expanduser("~/Desktop/LugaFlix_Poster.pdf")
QR_URL = "https://katogo.ugnews24.info/app"


def qr_to_base64(url):
    qr = qrcode.QRCode(version=1, error_correction=qrcode.constants.ERROR_CORRECT_H, box_size=14, border=2)
    qr.add_data(url)
    qr.make(fit=True)
    img = qr.make_image(fill_color="#111", back_color="white").convert("RGB")
    buf = io.BytesIO()
    img.save(buf, format="PNG")
    return base64.b64encode(buf.getvalue()).decode()


def build_html(qr_b64):
    return f'''<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&family=Playfair+Display:wght@700;800;900&display=swap');

  * {{ margin: 0; padding: 0; box-sizing: border-box; }}
  @page {{ size: A4; margin: 0; }}

  body {{
    width: 210mm;
    height: 297mm;
    font-family: 'Inter', sans-serif;
    background: #fff;
    color: #111;
    overflow: hidden;
  }}

  .page {{
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    position: relative;
    border: 4px solid #111;
  }}

  /* ===== HERO ===== */
  .hero {{
    background: #111;
    color: #fff;
    padding: 18mm 16mm 16mm;
    text-align: center;
    position: relative;
  }}

  .hero::after {{
    content: '';
    position: absolute;
    bottom: 0; left: 50%;
    transform: translateX(-50%);
    width: 0; height: 0;
    border-left: 22px solid transparent;
    border-right: 22px solid transparent;
    border-bottom: 16px solid #fff;
  }}

  .hero .top-label {{
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 4px;
    text-transform: uppercase;
    color: #D42B2B;
    margin-bottom: 12px;
  }}

  .hero h1 {{
    font-family: 'Playfair Display', serif;
    font-weight: 900;
    font-size: 52px;
    line-height: 1.05;
    letter-spacing: 2px;
    margin-bottom: 4px;
  }}

  .hero h1 .red {{ color: #D42B2B; }}

  .red-bar {{
    width: 70px;
    height: 5px;
    background: #D42B2B;
    margin: 14px auto;
    border-radius: 3px;
  }}

  /* ===== MAIN MESSAGE ===== */
  .main-msg {{
    background: #fff;
    text-align: center;
    padding: 16mm 14mm 8mm;
  }}

  .main-msg h2 {{
    font-family: 'Playfair Display', serif;
    font-weight: 800;
    font-size: 32px;
    line-height: 1.25;
    color: #111;
    margin-bottom: 6px;
  }}

  .main-msg h2 .red {{ color: #D42B2B; }}

  .main-msg .sub {{
    font-size: 17px;
    font-weight: 600;
    color: #555;
    margin-top: 4px;
  }}

  /* ===== QR SECTION ===== */
  .qr-section {{
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4mm 14mm 10mm;
    text-align: center;
  }}

  .qr-box {{
    border: 3px solid #111;
    border-radius: 14px;
    padding: 14px;
    display: inline-block;
    margin-bottom: 12px;
    position: relative;
  }}

  .qr-box img {{
    display: block;
    width: 50mm;
    height: 50mm;
  }}

  .qr-box .badge {{
    position: absolute;
    top: -13px;
    left: 50%;
    transform: translateX(-50%);
    background: #D42B2B;
    color: #fff;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 2px;
    text-transform: uppercase;
    padding: 4px 16px;
    border-radius: 20px;
    white-space: nowrap;
  }}

  .scan-text {{
    font-size: 16px;
    font-weight: 700;
    color: #111;
    margin-bottom: 3px;
  }}

  .url {{
    font-size: 13px;
    color: #999;
    margin-bottom: 16px;
  }}

  .divider {{
    display: flex;
    align-items: center;
    gap: 16px;
    width: 65%;
    margin-bottom: 16px;
  }}

  .divider .line {{
    flex: 1;
    height: 1.5px;
    background: #ddd;
  }}

  .divider span {{
    font-size: 13px;
    font-weight: 800;
    color: #ccc;
    letter-spacing: 2px;
  }}

  .playstore {{
    font-size: 24px;
    font-weight: 800;
    color: #111;
    line-height: 1.3;
  }}

  .playstore .red {{
    color: #D42B2B;
    font-style: italic;
  }}

  /* ===== FOOTER ===== */
  .footer {{
    background: #D42B2B;
    padding: 8mm 16mm;
    text-align: center;
  }}

  .footer p {{
    font-size: 15px;
    font-weight: 800;
    color: #fff;
    letter-spacing: 2px;
    text-transform: uppercase;
  }}

  /* ===== CORNER MARKS ===== */
  .corner {{
    position: absolute;
    width: 30px;
    height: 30px;
    z-index: 10;
  }}
  .c-tl {{ top: 6px; left: 6px; border-top: 4px solid #D42B2B; border-left: 4px solid #D42B2B; }}
  .c-tr {{ top: 6px; right: 6px; border-top: 4px solid #D42B2B; border-right: 4px solid #D42B2B; }}
  .c-bl {{ bottom: 6px; left: 6px; border-bottom: 4px solid #D42B2B; border-left: 4px solid #D42B2B; }}
  .c-br {{ bottom: 6px; right: 6px; border-bottom: 4px solid #D42B2B; border-right: 4px solid #D42B2B; }}
</style>
</head>
<body>
  <div class="page">
    <div class="corner c-tl"></div>
    <div class="corner c-tr"></div>
    <div class="corner c-bl"></div>
    <div class="corner c-br"></div>

    <!-- HERO -->
    <div class="hero">
      <p class="top-label">&#127916; Streaming App</p>
      <h1>LUGA<span class="red">FLIX</span></h1>
      <div class="red-bar"></div>
    </div>

    <!-- MAIN MESSAGE -->
    <div class="main-msg">
      <h2>Watch &amp; Download<br><span class="red">Luganda Translated</span><br>Movies &amp; Series</h2>
      <p class="sub">All Your Favourite VJs &bull; All in One App</p>
    </div>

    <!-- QR -->
    <div class="qr-section">
      <div class="qr-box">
        <div class="badge">Free Download</div>
        <img src="data:image/png;base64,{qr_b64}" alt="QR">
      </div>
      <p class="scan-text">Point your phone camera here</p>
      <p class="url">{QR_URL}</p>

      <div class="divider">
        <div class="line"></div>
        <span>OR</span>
        <div class="line"></div>
      </div>

      <p class="playstore">Search <span class="red">&ldquo;LugaFlix&rdquo;</span><br>on Google Play Store</p>
    </div>

    <!-- FOOTER -->
    <div class="footer">
      <p>Uganda&rsquo;s #1 Luganda Movie App &mdash; Download Now!</p>
    </div>
  </div>
</body>
</html>'''


def main():
    print("Generating QR code...")
    qr_b64 = qr_to_base64(QR_URL)
    print("Rendering PDF...")
    html = build_html(qr_b64)
    tmp = os.path.expanduser("~/Desktop/_poster_tmp.html")
    with open(tmp, 'w') as f:
        f.write(html)
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()
        page.set_content(html, wait_until='networkidle')
        page.pdf(path=OUTPUT_PDF, format='A4', print_background=True,
                 margin={'top':'0','bottom':'0','left':'0','right':'0'})
        browser.close()
    os.remove(tmp)
    print(f"Done! → {OUTPUT_PDF}")


if __name__ == "__main__":
    main()
