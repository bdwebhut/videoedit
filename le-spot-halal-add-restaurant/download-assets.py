#!/usr/bin/env python3
"""Download the original per-section Figma exports into their exact local slots."""
import json
import pathlib
import subprocess
import sys

root = pathlib.Path(__file__).resolve().parent
manifest = json.loads((root / 'asset-manifest.json').read_text())
target = root / 'assets'
target.mkdir(exist_ok=True)
failed = []
for asset in manifest['assets']:
    filename = asset['file']
    output = target / filename
    if output.exists() and output.stat().st_size:
        continue
    partial = target / (filename + '.part')
    result = subprocess.run(['curl', '-fL', '--retry', '2', '--connect-timeout', '15',
                             '--max-time', '60', asset['url'], '-o', str(partial)])
    if result.returncode == 0 and partial.exists() and partial.stat().st_size:
        partial.replace(output)
    else:
        partial.unlink(missing_ok=True)
        failed.append(filename)
if failed:
    print('Could not download: ' + ', '.join(failed), file=sys.stderr)
    print('Refresh expired URLs using Figma MCP for the sourceNodes in the manifest.', file=sys.stderr)
    sys.exit(1)
print(f"Downloaded all {len(manifest['assets'])} original Figma assets.")
