#!/usr/bin/env python3
"""Parse Katogo CSV exports and analyze data for migration."""
import csv, re, html, json
from collections import Counter

def strip_html(s):
    s = re.sub(r'<br\s*/?>', '|', s)
    s = re.sub(r'<[^>]+>', '', s)
    s = html.unescape(s)
    return s.strip()

# ── Parse Users ──
users = []
with open('/Applications/MAMP/htdocs/katogo/transition-data/Users_2026-04-10_04-55.csv', 'r', encoding='utf-8-sig') as f:
    reader = csv.DictReader(f)
    for row in reader:
        uid = row.get('ID', '').strip()
        if not uid or not uid.isdigit():
            continue
        user_html = row.get('User', '')
        name_match = re.search(r'<strong>(.*?)</strong>', user_html)
        email_match = re.search(r"[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}", user_html)
        name = name_match.group(1).strip() if name_match else ''
        email = email_match.group(0).strip().lower() if email_match else ''
        phone = strip_html(row.get('Phone', '')).replace('\u2014', '').strip()
        sub_text = strip_html(row.get('Subscription', '')).strip()
        device_html = row.get('Device', '')
        device = 'android' if 'android' in device_html.lower() else ('ios' if 'ios' in device_html.lower() else 'unknown')
        guest_html = row.get('Guest', '')
        is_guest = 'Guest' in guest_html
        if email:
            users.append({
                'old_id': int(uid), 'name': name, 'email': email,
                'phone': phone, 'device': device,
                'subscription': sub_text, 'is_guest': is_guest
            })

print(f"USERS: {len(users)} with email")
for u in users[:5]:
    print(f"  id={u['old_id']} email={u['email']} name={u['name']} sub={u['subscription']}")
sub_counts = Counter(u['subscription'] for u in users)
print(f"Subscription breakdown: {dict(sub_counts)}")

# ── Parse Transactions ──
txns = []
with open('/Applications/MAMP/htdocs/katogo/transition-data/Transactions_2026-04-10_04-58.csv', 'r', encoding='utf-8-sig') as f:
    reader = csv.DictReader(f)
    for row in reader:
        tid = row.get('ID', '').strip()
        if not tid or not tid.isdigit():
            continue
        date_text = row.get('Date', '').strip()
        user_html = row.get('User', '')
        name_match = re.search(r'<strong>(.*?)</strong>', user_html)
        email_match = re.search(r"[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}", user_html)
        name = name_match.group(1).strip() if name_match else ''
        email = email_match.group(0).strip().lower() if email_match else ''
        sub_html = row.get('Subscription', '')
        sub_id_match = re.search(r'#(\d+)', sub_html)
        sub_id = int(sub_id_match.group(1)) if sub_id_match else None
        type_text = strip_html(row.get('Type', '')).strip()
        amount_html = row.get('Amount', '')
        amount_match = re.search(r'[\d,]+', strip_html(amount_html))
        amount = int(amount_match.group(0).replace(',', '')) if amount_match else 0
        status_text = strip_html(row.get('Status', '')).strip()
        method_text = strip_html(row.get('Method', '')).strip()
        tracking_html = row.get('Tracking ID', '') or ''
        tracking_match = re.search(r'[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}', tracking_html)
        tracking_id = tracking_match.group(0) if tracking_match else ''
        if email:
            txns.append({
                'old_id': int(tid), 'date': date_text, 'email': email, 'name': name,
                'sub_id': sub_id, 'type': type_text, 'amount': amount,
                'status': status_text, 'method': method_text, 'tracking_id': tracking_id
            })

print(f"\nTRANSACTIONS: {len(txns)} with email")
for t in txns[:5]:
    print(f"  id={t['old_id']} email={t['email']} amt={t['amount']} status={t['status']} type={t['type']}")
status_counts = Counter(t['status'] for t in txns)
print(f"Status breakdown: {dict(status_counts)}")
type_counts = Counter(t['type'] for t in txns)
print(f"Type breakdown: {dict(type_counts)}")

user_emails = set(u['email'] for u in users)
txn_emails = set(t['email'] for t in txns)
print(f"\nUnique user emails: {len(user_emails)}")
print(f"Unique txn emails: {len(txn_emails)}")
print(f"Txn emails NOT in users: {len(txn_emails - user_emails)}")

# Save clean JSON for later use
with open('/Applications/MAMP/htdocs/katogo/transition-data/parsed_users.json', 'w') as f:
    json.dump(users, f, indent=2)
with open('/Applications/MAMP/htdocs/katogo/transition-data/parsed_transactions.json', 'w') as f:
    json.dump(txns, f, indent=2)
print("\nSaved parsed_users.json and parsed_transactions.json")
