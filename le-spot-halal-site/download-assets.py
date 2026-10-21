#!/usr/bin/env python3
"""Import existing original assets, then download missing files from official Figma URLs."""
import argparse
import json
from pathlib import Path
import shutil
import sys
import urllib.error
import urllib.request
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parent
REPOSITORY = ROOT.parent
ASSETS = ROOT / 'assets'


def valid(path):
    if not path.is_file():
        return False
    try:
        if path.suffix == '.png':
            return path.read_bytes().startswith(b'\x89PNG\r\n\x1a\n')
        if path.suffix == '.svg':
            return ET.parse(path).getroot().tag.split('}')[-1] == 'svg'
    except (OSError, ET.ParseError):
        return False
    return False


def asset_path(relative):
    path = ASSETS / relative
    if not path.resolve().is_relative_to(ASSETS.resolve()):
        raise ValueError(f'Asset path must stay inside assets/: {relative}')
    return path


def download(entry, target):
    partial = target.with_name(target.name + '.part')
    for url in entry['sources']:
        if not url.startswith('https://www.figma.com/api/mcp/asset/'):
            raise ValueError('Only official Figma asset URLs are supported.')
        try:
            request = urllib.request.Request(url, headers={'User-Agent': 'LeSpotHalalAssetDownloader/1.0'})
            with urllib.request.urlopen(request, timeout=30) as response, partial.open('wb') as output:
                shutil.copyfileobj(response, output)
            # Validate with the real extension before replacing the final file.
            if target.suffix == '.png':
                accepted = partial.read_bytes().startswith(b'\x89PNG\r\n\x1a\n')
            else:
                accepted = ET.parse(partial).getroot().tag.split('}')[-1] == 'svg'
            if accepted:
                partial.replace(target)
                return True
        except (OSError, urllib.error.URLError, ET.ParseError):
            pass
        finally:
            partial.unlink(missing_ok=True)
    return False


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--local-only', action='store_true', help='Import local files without making network requests.')
    args = parser.parse_args()
    manifest = json.loads((ROOT / 'asset-manifest.json').read_text(encoding='utf-8'))
    counts = {'existing': 0, 'imported': 0, 'downloaded': 0}
    missing = []
    for entry in manifest['assets']:
        target = asset_path(entry['path'])
        target.parent.mkdir(parents=True, exist_ok=True)
        if valid(target):
            counts['existing'] += 1
            continue
        for folder in entry['sourceFolders']:
            candidate = REPOSITORY / folder / 'assets' / target.name
            if valid(candidate):
                shutil.copy2(candidate, target)
                counts['imported'] += 1
                break
        else:
            if not args.local_only and download(entry, target):
                counts['downloaded'] += 1
            else:
                missing.append(entry['path'])
    print(', '.join(f'{key}: {value}' for key, value in counts.items()))
    if missing:
        print(f'Missing {len(missing)} original assets:', file=sys.stderr)
        print('\n'.join(missing), file=sys.stderr)
        print('Run this script on your local machine with the original assets or Figma access. Temporary Figma URLs may need refreshing.', file=sys.stderr)
        return 1
    print(f'All {len(manifest["assets"])} original assets are ready in the shared assets folder.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
