import re

with open('docs/plan/infrastruktur/Redesign UI_UX Jaringan - Google Gemini_files/shim(1).html', 'r', encoding='utf-8', errors='ignore') as f:
    html = f.read()

parts = html.split('</main>')
if len(parts) > 1:
    modals_html = parts[1]
    # find all divs with id ending in Modal
    modal_matches = re.findall(r'<div[^>]*id=[\"\']([^\"\']*[Mm]odal[^\"\']*)[\"\'][^>]*>', modals_html)
    print("Modal IDs found:", modal_matches)
    
    # print headers of modals
    for mid in modal_matches:
        idx = modals_html.find(f'id="{mid}"')
        if idx != -1:
            snippet = modals_html[idx:idx+800]
            # remove tags
            text = ' '.join(re.sub(r'<[^>]+>', ' ', snippet).split())
            print(f"\n--- {mid} ---")
            print(text[:250])
