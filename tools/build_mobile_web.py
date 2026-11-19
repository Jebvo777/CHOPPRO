#!/usr/bin/env python3
"""Prepare Expo's web export for the PHP mobile asset endpoint."""
import argparse
import gzip
from pathlib import Path
import re
import shutil
from urllib.parse import urlencode

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('export', type=Path, help='Directory created by Expo export --platform web')
parser.add_argument('--output', type=Path, default=Path('web/mobile/build'))
args = parser.parse_args()
export, output = args.export, args.output
output.mkdir(parents=True, exist_ok=True)
shutil.copyfile(export / 'index.html', output / 'index.html')
shutil.copyfile(export / 'favicon.ico', output / 'favicon.ico')

bundles = sorted((export / '_expo/static/js/web').glob('index-*.js'))
assets = sorted(path for path in (export / 'assets').rglob('*') if path.is_file())
supported = re.compile(r'(?:index-[a-f0-9]{32}\.js|icon\.[a-f0-9]{32}\.png|Golos(?:Regular|Medium|Semibold|Bold)\.[a-f0-9]{32}\.ttf)')
paths = {}
for path in [*bundles, *assets]:
    if not supported.fullmatch(path.name):
        raise SystemExit(f'Unsupported asset name: {path.name}')
    paths['/' + path.relative_to(export).as_posix()] = './demo-api.php?' + urlencode({
        'path': '/v1/mobile-assets', 'name': path.name,
    })
for asset in assets:
    shutil.copyfile(asset, output / asset.name)
for bundle in bundles:
    content = bundle.read_text()
    for path, url in paths.items():
        content = content.replace('"' + path + '"', '"' + url + '"')
    (output / (bundle.name + '.gz')).write_bytes(gzip.compress(content.encode(), compresslevel=9, mtime=0))
print(f'Packaged {len(bundles)} JavaScript bundles and {len(assets)} assets.')
