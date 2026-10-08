import argparse,json,pathlib,zipfile
p=argparse.ArgumentParser();p.add_argument('--platform',choices=['android','ios'],required=True);p.add_argument('--output',required=True);p.add_argument('--sha',required=True);a=p.parse_args();root=pathlib.Path('mobile');out=pathlib.Path(a.output);out.parent.mkdir(parents=True,exist_ok=True)
with zipfile.ZipFile(out,'w',zipfile.ZIP_DEFLATED,compresslevel=6) as archive:
 for path in sorted(root.rglob('*')):
  if not path.is_file():continue
  parts=path.relative_to(root).parts
  if any(x in parts for x in ['node_modules','Pods','.gradle','build','.expo','dist','ios-build','.git']):continue
  if parts[0] in ['android','ios'] and parts[0]!=a.platform:continue
  if path.suffix in ['.keystore','.jks','.p12','.mobileprovision','.log']:continue
  if path.name.startswith('.env') or path.name=='local.properties':continue
  archive.write(path,'CHOPPRO/'+path.relative_to(root).as_posix())
 archive.writestr('CHOPPRO/source-version.json',json.dumps({'commit':a.sha,'platform':a.platform,'version':'3.0.0','integrations':False},ensure_ascii=False,indent=2))
print(json.dumps({'platform':a.platform,'archive':str(out),'bytes':out.stat().st_size}))
