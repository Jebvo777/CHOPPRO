#!/usr/bin/env python3
"""Render every versioned PUML and record the exact source Git blob SHA."""
import hashlib
import json
from pathlib import Path
import subprocess

root = Path('docs/диаграммы')
sources = sorted(root.glob('*.puml'))
if not sources:
    raise SystemExit('No PUML sources')
renders = {}
for path in sources:
    for output_format in ('svg','png'):
        subprocess.run(['plantuml','-charset','UTF-8','-failfast2','-t'+output_format,str(path)],check=True)
        if not path.with_suffix('.'+output_format).is_file():
            raise SystemExit('Missing render for '+str(path))
    data = path.read_bytes()
    renders[path.as_posix()] = {'source_sha':hashlib.sha1(b'blob '+str(len(data)).encode()+b'\0'+data).hexdigest(),
                               'svg':path.with_suffix('.svg').as_posix(),'png':path.with_suffix('.png').as_posix()}
valid = {p.with_suffix('.svg') for p in sources} | {p.with_suffix('.png') for p in sources}
for path in list(root.glob('*.svg')) + list(root.glob('*.png')):
    if path not in valid: path.unlink()
(root/'render-index.json').write_text(json.dumps({'version':1,'renders':renders},ensure_ascii=False,indent=2)+'\n')
print('Rendered',len(sources),'PUML diagrams')
