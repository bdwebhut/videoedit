"""Standard-library checks for the source build and safe local asset migration."""
import base64
from contextlib import redirect_stdout, redirect_stderr
from html.parser import HTMLParser
import importlib.util
import io
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]


def module_from(name, path):
    spec = importlib.util.spec_from_file_location(name, path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


build = module_from('site_build', ROOT / 'build.py')
downloader = module_from('site_assets', ROOT / 'download-assets.py')


class Markup(HTMLParser):
    def __init__(self):
        super().__init__()
        self.ids = []
        self.assets = []
        self.headings = 0

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if 'id' in attrs:
            self.ids.append(attrs['id'])
        self.headings += tag == 'h1'
        if tag in {'img', 'script'}:
            self.assets.append(attrs['src'])
        if tag == 'source':
            self.assets.append(attrs['srcset'])
        if tag == 'link' and attrs.get('rel') == 'stylesheet':
            self.assets.append(attrs['href'])


class BuildTests(unittest.TestCase):
    def test_committed_page_matches_shared_templates(self):
        self.assertEqual((ROOT / 'index.html').read_text(), build.build())

    def test_page_has_one_heading_unique_ids_and_declared_asset_paths(self):
        markup = Markup()
        markup.feed(build.build())
        self.assertEqual(markup.headings, 1)
        self.assertEqual(len(markup.ids), len(set(markup.ids)))
        manifest = json.loads((ROOT / 'asset-manifest.json').read_text())
        declared = {'assets/' + asset['path'] for asset in manifest['assets']}
        for asset in markup.assets:
            self.assertTrue(asset.startswith('assets/'), asset)
            self.assertTrue((ROOT / asset).exists() or asset in declared, asset)

    def test_missing_template_values_fail_clearly(self):
        with self.assertRaisesRegex(ValueError, 'Missing template value'):
            build.render('{{missing}}', {})

    def test_shared_card_data_is_escaped(self):
        records = [{'name': '<script>alert(1)</script>', 'slug': 'test', 'variant': 'pizza', 'photo': 'test.png', 'badge': '<b>New</b>', 'quote': '"Nice"', 'tags': []}]
        real_read = build.read
        with patch.object(build, 'read', side_effect=lambda path: json.dumps(records) if path == 'data/home-restaurants.json' else real_read(path)):
            result = build.restaurant_cards()
        self.assertNotIn('<script>', result)
        self.assertNotIn('<b>New</b>', result)
        self.assertIn('&lt;script&gt;', result)


class AssetTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.repo = Path(self.temp.name)
        self.site = self.repo / 'site'
        self.assets = self.site / 'assets'
        self.assets.mkdir(parents=True)
        self.source = self.repo / 'desktop' / 'assets'
        self.source.mkdir(parents=True)
        self.png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jT1sAAAAASUVORK5CYII=')
        self.svg = b'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><path d="M0 0h1v1H0z"/></svg>'
        self.entries = [{'path': 'images/photo.png', 'sources': ['https://www.figma.com/api/mcp/asset/example/photo.png'], 'sourceFolders': ['desktop']}]
        (self.site / 'asset-manifest.json').write_text(json.dumps({'assets': self.entries}))
        self.globals = patch.multiple(downloader, ROOT=self.site, REPOSITORY=self.repo, ASSETS=self.assets)
        self.globals.start()
        self.addCleanup(self.globals.stop)

    def run_local(self):
        with patch('sys.argv', ['download-assets.py', '--local-only']), redirect_stdout(io.StringIO()), redirect_stderr(io.StringIO()), patch.object(downloader, 'download', side_effect=AssertionError('Network requested in local-only mode')):
            return downloader.main()

    def test_imports_existing_original_without_network(self):
        (self.source / 'photo.png').write_bytes(self.png)
        self.assertEqual(self.run_local(), 0)
        self.assertEqual((self.assets / 'images/photo.png').read_bytes(), self.png)

    def test_existing_valid_asset_is_not_overwritten(self):
        target = self.assets / 'images/photo.png'
        target.parent.mkdir()
        target.write_bytes(self.png)
        (self.source / 'photo.png').write_text('not an image')
        self.assertEqual(self.run_local(), 0)
        self.assertEqual(target.read_bytes(), self.png)

    def test_html_error_response_is_not_imported_as_an_image(self):
        (self.source / 'photo.png').write_text('<html>403 Forbidden</html>')
        self.assertEqual(self.run_local(), 1)
        self.assertFalse((self.assets / 'images/photo.png').exists())

    def test_svg_root_is_required(self):
        path = self.assets / 'icon.svg'
        path.write_bytes(self.svg)
        self.assertTrue(downloader.valid(path))
        path.write_text('<html>Forbidden</html>')
        self.assertFalse(downloader.valid(path))

    def test_manifest_cannot_write_outside_assets(self):
        with self.assertRaises(ValueError):
            downloader.asset_path('../../outside.png')

    def test_non_official_download_url_is_rejected(self):
        target = self.assets / 'icon.svg'
        with self.assertRaises(ValueError):
            downloader.download({'sources': ['https://example.com/icon.svg']}, target)


if __name__ == '__main__':
    unittest.main()
