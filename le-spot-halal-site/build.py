#!/usr/bin/env python3
"""Build the static site pages from shared HTML partials using Python's standard library."""
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


PAGES = {
    'home': {
        'output': 'index.html',
        'page_title': 'Le spot halal — Le meilleur guide des spots halal',
        'page_description': 'Découvrez les meilleurs restaurants halal à Genève et en France voisine avec Le spot halal.',
        'page_stylesheet': 'assets/css/pages/home.css',
        'page_script': 'assets/js/home.js',
        'page_class': 'page-home',
        'about_url': '#about',
        'search_url': '#search',
        'nearby_current': '',
        'header_mobile_logo': 'a71cb.svg',
        'header_mobile_search': '8b691.svg',
        'footer_mobile_logo': '91834.svg',
        'footer_desktop_logo': '65cda.svg',
    },
    'restaurants': {
        'output': 'restaurants.html',
        'page_title': 'Les bons spots halal à Genève — Le spot halal',
        'page_description': 'Découvrez les restaurants halal à Genève et filtrez les spots par type de cuisine.',
        'page_stylesheet': 'assets/css/pages/restaurants.css',
        'page_script': 'assets/js/restaurants.js',
        'page_class': 'page-restaurants',
        'about_url': 'index.html#about',
        'search_url': 'index.html#search',
        'nearby_current': 'aria-current="page"',
        'header_mobile_logo': '6d906.svg',
        'header_mobile_search': '01fd5.svg',
        'footer_mobile_logo': 'f615a.svg',
        'footer_desktop_logo': '2233b.svg',
    },
}


def listing_cards():
    template = read('partials/listing-card.html')
    records = json.loads(read('data/listing-restaurants.json'))
    cards = []
    for index, record in enumerate(records):
        values = {key: escape(value, quote=True) for key, value in record.items() if isinstance(value, str)}
        name = record['name'].split(' ', 1)
        values['title'] = ' <br class="listing-card__break">'.join(escape(part) for part in name)
        values['order'] = str(index)
        values['crop_classes'] = ' '.join(
            css for key, css in [('desktopCrop', 'listing-card--desktop-crop'), ('mobileCrop', 'listing-card--mobile-crop')]
            if record.get(key)
        )
        cards.append(render(template, values))
    return '\n'.join(cards)


def build(page='home'):
    settings = dict(PAGES[page], nearby_url='restaurants.html')
    cards = {'restaurant_cards': restaurant_cards()} if page == 'home' else {'listing_cards': listing_cards()}
    content = render(read(f'pages/{page}.html'), cards)
    dialogs = read('partials/info-dialog.html')
    if page == 'restaurants':
        dialogs += '\n' + read('partials/listing-search.html')
    output = render(read('layout.html'), dict(settings,
        header=render(read('partials/header.html'), settings),
        content=content,
        footer=render(read('partials/footer.html'), settings),
        dialog=dialogs,
    ))
    return '\n'.join(line.rstrip() for line in output.splitlines()) + '\n'


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--check', action='store_true', help='Check that generated pages match their source templates.')
    parser.add_argument('--page', choices=PAGES, help='Build/check only this page; the default is all pages.')
    args = parser.parse_args()
    pages = [args.page] if args.page else PAGES
    outdated = []
    for page in pages:
        output = build(page)
        target = ROOT / PAGES[page]['output']
        if args.check:
            if not target.exists() or target.read_text(encoding='utf-8') != output:
                outdated.append(target.name)
        else:
            target.write_text(output, encoding='utf-8')
            print(f'Built {target.name} from the shared layout, header, footer, and page templates.')
    if outdated:
        parser.exit(1, f'Outdated pages: {", ".join(outdated)}. Run python3 build.py.\n')
    if args.check:
        print('All generated pages match their source templates.')


if __name__ == '__main__':
    main()
