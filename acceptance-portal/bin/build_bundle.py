#!/usr/bin/env python3
"""Build a self-contained PHP portal ZIP from a Git checkout or verified GitHub metadata."""
from __future__ import annotations
import argparse
import datetime
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
from zipfile import ZipFile, ZIP_DEFLATED

EXTENSIONS = {'md','markdown','txt','json','yaml','yml','csv','tsv','puml','svg','png','jpg',
              'jpeg','webp','gif','pdf','docx','xlsx','sql','html','css','js','mjs','woff','woff2','ttf','ico'}

def git(source: Path, *args: str) -> bytes:
    return subprocess.check_output(['git', '-C', str(source), *args])

def metadata(source: Path) -> dict:
    sha = git(source, 'rev-parse', 'HEAD').decode().strip()
    branch = subprocess.run(['git','-C',str(source),'symbolic-ref','--short','-q','HEAD'],capture_output=True).stdout.decode().strip()
    if not branch: branch = os.environ.get('GITHUB_REF_NAME','main')
    tree = {}
    for record in git(source, 'ls-tree', '-r', '-t', '-l', '-z', 'HEAD').split(b'\0'):
        if not record: continue
        meta, rawpath = record.split(b'\t', 1)
        mode, kind, object_sha, size = meta.decode().split()
        path = rawpath.decode()
        entry = {'path':path, 'mode':mode, 'type':kind, 'sha':object_sha}
        if size != '-': entry['size'] = int(size)
        tree[path] = entry
    commits = []
    for line in git(source, 'log', '-5', '--format=%H').decode().splitlines():
        message = git(source, 'show', '-s', '--format=%B', line).decode().strip()
        date = git(source, 'show', '-s', '--format=%cI', line).decode().strip()
        commits.append({'sha':line, 'message':message, 'date':date})
    return {'sha':sha,'branch':branch,'tree':tree,'commits':commits}

def selected(path: str, entry: dict, manifest: dict) -> bool:
    if entry['type'] != 'blob' or entry.get('mode') != '100644' or Path(path).suffix.lstrip('.').lower() not in EXTENSIONS:
        return False
    if path == 'README.md' or path == 'acceptance-portal/portal.json' or path.startswith(('docs/','prototypes/')):
        return True
    return any(path == s.get('path') or (s.get('directory') and path.startswith(s['directory'].rstrip('/')+'/'))
               for s in manifest['sections'])

def build(source: Path, output: Path, supplied_metadata: Path | None = None) -> dict:
    meta = json.loads(supplied_metadata.read_text()) if supplied_metadata else metadata(source)
    manifest = json.loads((source/'acceptance-portal/portal.json').read_text())
    files = {p:e for p,e in meta['tree'].items() if selected(p,e,manifest)}
    with tempfile.TemporaryDirectory(prefix='choppro-portal-') as temp:
        target = Path(temp)
        portal = source/'acceptance-portal'
        for item in portal.rglob('*'):
            if not item.is_file(): continue
            relative = item.relative_to(portal)
            if any(p in {'__pycache__','rendered','node_modules'} for p in relative.parts): continue
            if relative.parts[0] in {'bootstrap','storage'} and relative.name != '.htaccess': continue
            if relative.name.endswith('.pyc'): continue
            destination = target/relative
            destination.parent.mkdir(parents=True,exist_ok=True)
            shutil.copyfile(item,destination)
        blob_dir = target/'bootstrap/blobs'
        blob_dir.mkdir(parents=True,exist_ok=True)
        for path, entry in files.items():
            data = (source/path).read_bytes()
            digest = hashlib.sha1(b'blob '+str(len(data)).encode()+b'\0'+data).hexdigest()
            if digest != entry['sha']:
                raise ValueError('Content does not match GitHub blob: '+path)
            (blob_dir/digest).write_bytes(data)
        now = int(datetime.datetime.now(datetime.timezone.utc).timestamp())
        snapshot = {**meta,'files':files,'manifest':manifest,'synced_at':now,
                    'message':meta['commits'][0]['message'],'commit_date':meta['commits'][0]['date']}
        (target/'bootstrap/index.json').write_text(json.dumps(snapshot,ensure_ascii=False,indent=2)+'\n')
        output.parent.mkdir(parents=True,exist_ok=True)
        with ZipFile(output,'w',ZIP_DEFLATED,compresslevel=9) as archive:
            for item in sorted(target.rglob('*')):
                if item.is_file(): archive.write(item,item.relative_to(target).as_posix())
    return {'archive':str(output),'sha':meta['sha'],'files':len(files),'bytes':output.stat().st_size}

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--source',type=Path,default=Path(__file__).resolve().parents[2])
    parser.add_argument('--output',type=Path,default=Path('dist/CHOPPRO_portal_github_auto.zip'))
    parser.add_argument('--metadata',type=Path,help='Verified metadata exported from GitHub (for connector-based builds)')
    args = parser.parse_args()
    print(json.dumps(build(args.source.resolve(),args.output.resolve(),args.metadata),ensure_ascii=False))
