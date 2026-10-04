import argparse, pathlib, json, hashlib, shutil, tempfile, zipfile, re, os
parser=argparse.ArgumentParser();parser.add_argument('--source',default='.');parser.add_argument('--output',required=True);parser.add_argument('--sha',required=True);parser.add_argument('--metadata');parser.add_argument('--private-config');parser.add_argument('--directory');args=parser.parse_args()
source=pathlib.Path(args.source).resolve();sha=args.sha
if not re.fullmatch('[a-f0-9]{40}',sha):raise SystemExit('Invalid SHA')
root=pathlib.Path(args.directory).resolve() if args.directory else pathlib.Path(tempfile.mkdtemp(prefix='choppro-',dir=os.environ.get('TMPDIR')))
root.mkdir(parents=True,exist_ok=True)
private=root/'.private';release=private/'releases'/sha;release.mkdir(parents=True,exist_ok=True);(private/'objects').mkdir(parents=True,exist_ok=True);(private/'runtime').mkdir(parents=True,exist_ok=True);loader=root/'.loader';loader.mkdir(exist_ok=True)
metadata=json.loads(pathlib.Path(args.metadata).read_text()) if args.metadata else None
if metadata and 'tree' in metadata:metadata={e['path']:e for e in metadata['tree'] if e['type']=='blob'}
entries={}
def allowed(path):
 if path in ['README.md','site-release.json']:return True
 return bool(re.match(r'^(backend/(src|config|database|public|scripts|assets)/|web/|docs/|prototypes/|acceptance-portal/(app/|assets/|prototypes/|(?:index|control|config|api|file|prototype)\.php$|portal\.json$|robots\.txt$))',path)) and not re.search(r'\.(pdf|part\d+)$',path,re.I)
for p in sorted(source.rglob('*')):
 if not p.is_file():continue
 path=p.relative_to(source).as_posix()
 if not allowed(path):continue
 content=p.read_bytes();blob=hashlib.sha1(b'blob '+str(len(content)).encode()+b'\0'+content).hexdigest()
 if metadata and (path not in metadata or metadata[path]['sha']!=blob):raise SystemExit('Source differs from Git: '+path)
 entries[path]={'path':path,'sha':blob,'size':len(content),'mode':'100644','type':'blob'}
 dest=release/path;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(content);(private/'objects'/blob).write_bytes(content)
for p in (source/'host-loader').glob('*.php'):shutil.copyfile(p,loader/p.name)
for route in ['index','control','api','file','prototype','demo','demo-api']:(root/(route+'.php')).write_text('<?php\ndeclare(strict_types=1);\n$hostRoute='+json.dumps(route)+';\nrequire __DIR__."/.loader/dispatch.php";\n')
for file,target in [('asset.php','asset.php'),('release-status.php','status.php'),('site-cron.php','cron.php')]:(root/file).write_text('<?php\ndeclare(strict_types=1);\nrequire __DIR__."/.loader/'+target+'";\n')
(root/'.htaccess').write_text('Options -Indexes\nDirectoryIndex index.php\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule ^(?:\\.private|\\.loader)(?:/|$) - [F,L]\nRewriteRule ^demo/(admin|client|platform|jobs)/?$ demo.php?app=$1 [QSA,L]\n</IfModule>\n<FilesMatch "^(INSTALL|installation|site-config|source-metadata).*">\nRequire all denied\n</FilesMatch>\n')
for d in [private,loader]:(d/'.htaccess').write_text('Require all denied\nDeny from all\n');(d/'index.html').write_text('')
(root/'robots.txt').write_text('User-agent: *\nDisallow: /\n')
cache=release/'.portal-cache';(cache/'blobs').mkdir(parents=True,exist_ok=True);files={p:e for p,e in entries.items() if p=='README.md' or p.startswith(('docs/','prototypes/')) or p=='acceptance-portal/portal.json'}
for path,e in files.items():shutil.copyfile(release/path,cache/'blobs'/e['sha'])
snapshot={'sha':sha,'branch':'main','tree':entries,'files':files,'manifest':json.loads((release/'acceptance-portal/portal.json').read_text()),'synced_at':0,'message':'Этап 2','commits':[]}
(cache/'current.json').write_text(json.dumps(snapshot,ensure_ascii=False));(cache/'state.json').write_text(json.dumps({'checked_at':0,'next_check_at':0,'error':None}));(release/'.release.json').write_text(json.dumps({'sha':sha,'files':entries}));(private/'current.json').write_text(json.dumps({'portal':sha,'demo':sha,'previous':None}));(private/'sync.json').write_text('{}')
if args.private_config:shutil.copyfile(args.private_config,private/'site.php')
else:(private/'site.php').write_text("<?php\nreturn ['demo'=>true];\n")
(private/'runtime'/'key').write_text(os.environ.get('CHOPPRO_BOOTSTRAP_KEY') or os.urandom(32).hex())
for p in private.rglob('*'):
 if p.is_file():p.chmod(0o600)
output=pathlib.Path(args.output).resolve();output.parent.mkdir(parents=True,exist_ok=True)
with zipfile.ZipFile(output,'w',zipfile.ZIP_DEFLATED,compresslevel=7)as z:
 for p in sorted(root.rglob('*')):
  if p.is_file():z.write(p,p.relative_to(root).as_posix())
print(json.dumps({'archive':str(output),'files':len(entries),'sha':sha,'bytes':output.stat().st_size,'directory':str(root)},ensure_ascii=False))
