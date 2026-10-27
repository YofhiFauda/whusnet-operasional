import re

with open('docs/plan/infrastruktur/Redesign UI_UX Jaringan - Google Gemini_files/shim(1).html', 'r', encoding='utf-8', errors='ignore') as f:
    html = f.read()

# Let's find all section tags
sections = re.findall(r'<section[^>]*>(.*?)</section>', html, re.DOTALL)
print(f"Total sections: {len(sections)}")

for i, s in enumerate(sections):
    clean = re.sub(r'<[^>]+>', ' ', s)
    clean = ' '.join(clean.split())
    print(f"\n--- Section {i} ({len(s)} chars) ---")
    print(clean[:300])

# Also check for modals
modals = re.findall(r'<div[^>]*id=[\"\'][^\"\']*modal[^\"\']*[\"\'][^>]*>(.*?)</div>', html, re.I | re.DOTALL)
print(f"\nTotal modals with id containing 'modal': {len(modals)}")

# Also look for javascript state & functions
scripts = re.findall(r'<script>(.*?)</script>', html, re.DOTALL)
for i, sc in enumerate(scripts):
    print(f"\n--- Inline script {i} ({len(sc)} chars) ---")
    for line in sc.splitlines()[:40]:
        print(line)
