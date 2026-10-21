#!/usr/bin/env python3
"""Reuse original assets across all export folders, then fetch missing files from Figma."""
import argparse
from collections import defaultdict
import json
from pathlib import Path
import shutil
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parent
REPOSITORY = ROOT.parent
ASSETS = ROOT / 'assets'


def image_type(path):
    """Inspect content, not the filename extension assigned by the Figma export."""
    try:
        with path.open('rb') as source:
            header = source.read(256)
        if header.startswith(b'\x89PNG\r\n\x1a\n'):
            return 'png'
        if header.startswith(b'\xff\xd8\xff'):
            return 'jpeg'
        if header[:6] in (b'GIF87a', b'GIF89a'):
            return 'gif'
        if header[:4] == b'RIFF' and header[8:12] == b'WEBP':
            return 'webp'
        if header[4:8] == b'ftyp' and any(brand in header[8:] for brand in (b'avif', b'avis')):
            return 'avif'
        if ET.parse(path).getroot().tag.split('}')[-1] == 'svg':
            return 'svg'
    except (OSError, ET.ParseError, ValueError):
        pass
    return None


def valid(path):
    kind = image_type(path)
    # Icons must remain vectors; raster exports may have a misleading .png suffix.
    return kind == 'svg' if path.suffix.lower() == '.svg' else kind in {'png', 'jpeg', 'webp', 'gif', 'avif'}


def asset_path(relative):
    path = ASSETS / relative
    if not path.resolve().is_relative_to(ASSETS.resolve()):
        raise ValueError(f'Asset path must stay inside assets/: {relative}')
    return path


def official_url(url):
    parsed = urllib.parse.urlsplit(url)
    return (parsed.scheme == 'https' and parsed.netloc == 'www.figma.com'
            and parsed.path.startswith('/api/mcp/asset/'))


def export_assets(manifest):
    """Read both original manifest formats without running repository scripts."""
    assets = manifest.get('assets', {})
    if isinstance(assets, dict):
        base = manifest.get('baseUrl', '').rstrip('/')
        for filename in dict.fromkeys(assets.values()):
            yield filename, f'{base}/{filename}' if base else None
    else:
        for entry in assets:
            if 'file' in entry:
                yield entry['file'], entry.get('url')
            elif 'path' in entry:
                for url in entry.get('sources', []):
                    yield Path(entry['path']).name, url


def enrich_manifest(manifest):
    """Use matching original filenames from every page as cache and URL fallbacks."""
    entries = {Path(entry['path']).name: entry for entry in manifest['assets']}
    for folder in sorted(REPOSITORY.glob('le-spot-halal-*')):
        source = folder / 'asset-manifest.json'
        if folder.resolve() == ROOT.resolve() or not source.is_file():
            continue
        try:
            exported = json.loads(source.read_text(encoding='utf-8'))
            for filename, url in export_assets(exported):
                entry = entries.get(Path(filename).name)
                if not entry:
                    continue
                folders = entry.setdefault('sourceFolders', [])
                if folder.name not in folders:
                    folders.append(folder.name)
                sources = entry.setdefault('sources', [])
                if url and official_url(url) and url not in sources:
                    sources.append(url)
        except (OSError, ValueError, TypeError, KeyError) as error:
            print(f'Warning: could not read {source.name} in {folder.name}: {error}', file=sys.stderr)
    return manifest


def local_index(extra_directories=()):
    """Index filenames once, including flat caches and nested images/icons folders."""
    index = defaultdict(list)
    directories = [ASSETS]
    directories.extend(folder / 'assets' for folder in sorted(REPOSITORY.glob('le-spot-halal-*'))
                       if folder.resolve() != ROOT.resolve())
    directories.extend(extra_directories)
    seen = set()
    for directory in directories:
        if not directory.is_dir():
            continue
        for path in sorted(directory.rglob('*')):
            if not path.is_file():
                continue
            resolved = path.resolve()
            if resolved in seen:
                continue
            seen.add(resolved)
            index[path.name].append(path)
    return index


def download(entry, target):
    # The original per-folder curl scripts worked on the user's machine.
    # Keep their transfer method and validate content only after transfer succeeds.
    partial = target.with_name(target.name + '.part')
    for url in entry['sources']:
        if not official_url(url):
            raise ValueError('Only official Figma asset URLs are supported.')
        try:
            if shutil.which('curl'):
                result = subprocess.run([
                    'curl', '--fail', '--location', '--silent', '--show-error',
                    '--retry', '1', '--connect-timeout', '15', '--max-time', '60',
                    url, '--output', str(partial),
                ], capture_output=True, text=True)
                if result.returncode:
                    print(f'  {target.name}: {result.stderr.strip()}', file=sys.stderr)
                    continue
            else:
                with urllib.request.urlopen(url, timeout=30) as response, partial.open('wb') as output:
                    shutil.copyfileobj(response, output)
            kind = image_type(partial)
            accepted = kind == 'svg' if target.suffix.lower() == '.svg' else kind in {'png', 'jpeg', 'webp', 'gif', 'avif'}
            if accepted:
                partial.replace(target)
                return True
            print(f'  {target.name}: response was not a supported image; trying the next original URL.', file=sys.stderr)
        except (OSError, urllib.error.URLError, ValueError) as error:
            print(f'  {target.name}: {error}', file=sys.stderr)
        finally:
            partial.unlink(missing_ok=True)
    return False


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--local-only', action='store_true', help='Import local files without network requests.')
    parser.add_argument('--check', action='store_true', help='Check the shared files without changing or downloading anything.')
    parser.add_argument('--assets-dir', type=Path, action='append', default=[], help='Also reuse originals from this directory (repeatable).')
    args = parser.parse_args()
    manifest = json.loads((ROOT / 'asset-manifest.json').read_text(encoding='utf-8'))
    if not args.check:
        enrich_manifest(manifest)
    cache = local_index(args.assets_dir) if not args.check else {}
    counts = {'existing': 0, 'imported': 0, 'downloaded': 0}
    missing = []
    for number, entry in enumerate(manifest['assets'], 1):
        target = asset_path(entry['path'])
        if valid(target):
            counts['existing'] += 1
            continue
        if args.check:
            missing.append(entry['path'])
            continue
        target.parent.mkdir(parents=True, exist_ok=True)
        preferred = [REPOSITORY / folder / 'assets' / target.name for folder in entry.get('sourceFolders', [])]
        candidates = preferred + cache.get(target.name, [])
        for candidate in candidates:
            if candidate.resolve() != target.resolve() and valid(candidate):
                shutil.copy2(candidate, target)
                counts['imported'] += 1
                print(f'[{number}/{len(manifest["assets"])}] Reused {entry["path"]} from {candidate.parent}')
                break
        else:
            if not args.local_only:
                print(f'[{number}/{len(manifest["assets"])}] Downloading {entry["path"]}', flush=True)
            if not args.local_only and download(entry, target):
                counts['downloaded'] += 1
            else:
                missing.append(entry['path'])
    print(', '.join(f'{key}: {value}' for key, value in counts.items()))
    if missing:
        print(f'Missing {len(missing)} of {len(manifest["assets"])} original assets:', file=sys.stderr)
        print('\n'.join(missing), file=sys.stderr)
        print('Existing valid originals were preserved. Retry the command, add --assets-dir /path/to/your/downloads, or refresh expired URLs in the original manifests.', file=sys.stderr)
        return 1
    print(f'All {len(manifest["assets"])} original assets are ready in the shared assets folder.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
