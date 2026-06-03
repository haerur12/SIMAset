import re
p='c:/xampp/htdocs/SIMAset/tracking_aset.php'
s=open(p,'r',encoding='utf-8').read()
php_segments=re.findall(r'<\?php(.*?)\?>',s,flags=re.S)
stack=[]
for seg_idx,seg in enumerate(php_segments, start=1):
    lines=seg.splitlines()
    for i,l in enumerate(lines, start=1):
        # find if(...):
        if re.search(r"\bif\s*\([^)]*\)\s*:\s*$", l.strip()):
            stack.append((seg_idx,i,l.strip()))
        if re.search(r"\bendif\s*;", l):
            if stack:
                stack.pop()
            else:
                print('Found endif; without matching if at segment',seg_idx,'line',i)

if stack:
    print('Unclosed if blocks:')
    for seg_idx,i,l in stack:
        print(f'  In PHP segment {seg_idx}, line {i}: {l}')
else:
    print('All if(: blocks closed')
