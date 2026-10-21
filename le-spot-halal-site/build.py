#!/usr/bin/env python3
"""Build the static homepage from shared HTML partials using Python's standard library."""
import argparse
from html import escape
import json
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parent
SOURCE = ROOT / 'src'
TOKEN = re.compile(r'\{\{([a-z_]+)\}\}')


def render(template, values):
    """Fail on missing fields instead of quietly emitting unfinished HTML."""
    def replace(match):
        key = match.group(1)
        if key not in values:
            raise ValueError(f'Missing template value: {key}')
        value = values[key]
        line_start = template.rfind('\n', 0, match.start()) + 1
        indent = template[line_start:match.start()]
        if not indent.strip():
            value = value.rstrip().replace('\n', '\n' + indent)
        return value
    return TOKEN.sub(replace, template)


def read(relative):
    return (SOURCE / relative).read_text(encoding='utf-8')


def restaurant_cards():
    template = read('partials/restaurant-card.html')
    restaurants = json.loads(read('data/home-restaurants.json'))
    indicators = '''<span class="restaurant-card__indicators">
      <span class="service-icon service-icon--instagram" role="img" aria-label="Instagram"><img src="assets/icons/5d75e.svg" alt="" class="icon" aria-hidden="true"></span>
      <span class="service-icon service-icon--delivery" role="img" aria-label="Livraison"><img src="assets/icons/85ca2.svg" alt="" class="icon" aria-hidden="true"></span>
    </span>'''
    result = []
    for restaurant in restaurants:
        values = {key: escape(value, quote=True) for key, value in restaurant.items() if isinstance(value, str)}
        name = restaurant['name']
        if restaurant['variant'] in {'maison', 'smash', 'nomad'}:
            first, last = name.split(' ', 1)
            values['title'] = f'{escape(first)} <br class="desktop-break">{escape(last)}'
        else:
            values['title'] = escape(name)
        values['tags'] = '<span class="restaurant-card__separator" aria-hidden="true">•</span>'.join(
            f'<span class="restaurant-tag restaurant-tag--{escape(tag["color"], quote=True)}">{escape(tag["label"])}</span>'
            for tag in restaurant['tags']
        )
        values['halal'] = '''<span class="restaurant-card__halal"><img src="assets/icons/b8020.svg" alt="" class="icon" aria-hidden="true"><img src="assets/icons/64176.svg" alt="Options halal"></span>''' if restaurant['variant'] == 'smash' else ''
        values['body_indicators'] = '' if restaurant['variant'] == 'maison' else indicators
        values['media_indicators'] = indicators if restaurant['variant'] == 'maison' else ''
        result.append(render(template, values))
    return '\n'.join(result)


def build():
    content = render(read('pages/home.html'), {'restaurant_cards': restaurant_cards()})
    output = render(read('layout.html'), {
        'page_title': 'Le spot halal — Le meilleur guide des spots halal',
        'page_description': 'Découvrez les meilleurs restaurants halal à Genève et en France voisine avec Le spot halal.',
        'page_stylesheet': 'assets/css/pages/home.css',
        'page_script': 'assets/js/home.js',
        'page_class': 'page-home',
        'header': read('partials/header.html'),
        'content': content,
        'footer': read('partials/footer.html'),
        'dialog': read('partials/info-dialog.html'),
    })
    return '\n'.join(line.rstrip() for line in output.splitlines()) + '\n'


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--check', action='store_true', help='Check that index.html matches its source templates.')
    args = parser.parse_args()
    output = build()
    target = ROOT / 'index.html'
    if args.check:
        if not target.exists() or target.read_text(encoding='utf-8') != output:
            parser.exit(1, 'index.html is out of date. Run python3 build.py.\n')
        print('Generated homepage matches its source templates.')
    else:
        target.write_text(output, encoding='utf-8')
        print('Built index.html from shared header, footer, card, and page templates.')


if __name__ == '__main__':
    main()
