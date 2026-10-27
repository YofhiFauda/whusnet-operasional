import re

with open('docs/plan/infrastruktur/master-node-whusnet.html', 'r', encoding='utf-8', errors='ignore') as f:
    content = f.read()

right_match = re.search(r'<!-- PANEL KANAN:.*?</section>', content, re.DOTALL)
if right_match:
    lines = right_match.group(0).splitlines()
    for i, line in enumerate(lines[100:250], start=100):
        print(f"{i}: {line}")
