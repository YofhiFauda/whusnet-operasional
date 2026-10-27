import re

with open('docs/plan/infrastruktur/Redesign UI_UX Jaringan - Google Gemini_files/shim(1).html', 'r', encoding='utf-8', errors='ignore') as f:
    html = f.read()

sections = re.findall(r'<section[^>]*>(.*?)</section>', html, re.DOTALL)

with open('.gemini/antigravity-ide/brain/10991be7-b958-484f-8712-06deb8e24e7c/scratch/section0_tree.html', 'w', encoding='utf-8') as f:
    f.write(sections[0])

with open('.gemini/antigravity-ide/brain/10991be7-b958-484f-8712-06deb8e24e7c/scratch/section1_detail.html', 'w', encoding='utf-8') as f:
    f.write(sections[1])

print("Written section0 and section1 successfully!")
