#!/usr/bin/env python3
"""
Récupère le roster de WWE 2K26 depuis thesmackdownhotel.com et l'écrit en JSON.

Le JSON produit est lu par la commande Craft `my-module/wwe-roster/import`.

Usage :
    python3 scripts/wwe2k26/scrape.py                  # tout le roster
    python3 scripts/wwe2k26/scrape.py --limit 10       # pour tester
    python3 scripts/wwe2k26/scrape.py --with-variants  # garde AJ Styles '16, (Retro)...
    python3 scripts/wwe2k26/scrape.py --with-extras    # garde Elite, MyRISE, célébrités

Les pages HTML sont mises en cache dans .cache/wwe2k26 (gitignoré) : relancer le
script ne retélécharge rien. Uniquement la lib standard Python 3.9+.
"""
import argparse
import hashlib
import html
import json
import re
import sys
import time
import urllib.request
from pathlib import Path

BASE = 'https://www.thesmackdownhotel.com'
ROSTER_URL = BASE + '/wwe2k26/roster'
UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130 Safari/537.36'

# Brand du site (champ Craft `roster`) selon le champ "Roster" de la fiche jeu
ROSTER_MAP = {
    'raw': 'raw',
    'smackdown': 'smackdown',
    'nxt': 'nxt',
    'legends': 'hallOfFamer',
    'legend': 'hallOfFamer',
    'hall of fame': 'hallOfFamer',
}


class Fetcher:
    def __init__(self, cache_dir: Path, delay: float):
        self.cache_dir = cache_dir
        self.delay = delay
        cache_dir.mkdir(parents=True, exist_ok=True)

    def get(self, url: str) -> str:
        path = self.cache_dir / (hashlib.md5(url.encode()).hexdigest() + '.html')
        if path.exists():
            return path.read_text(encoding='utf-8', errors='ignore')
        for attempt in range(3):
            try:
                req = urllib.request.Request(url, headers={'User-Agent': UA})
                with urllib.request.urlopen(req, timeout=30) as res:
                    body = res.read().decode('utf-8', errors='ignore')
                path.write_text(body, encoding='utf-8')
                time.sleep(self.delay)
                return body
            except Exception as e:  # noqa: BLE001
                if getattr(e, 'code', None) == 404:
                    return ''
                print(f'  ! {url} ({e}), nouvel essai...', file=sys.stderr)
                time.sleep(2 * (attempt + 1))
        return ''


def text_lines(fragment: str) -> list:
    fragment = re.sub(r'<script.*?</script>|<style.*?</style>', '', fragment, flags=re.S)
    fragment = re.sub(r'<[^>]+>', '\n', fragment)
    fragment = html.unescape(fragment)
    return [line.strip() for line in fragment.split('\n') if line.strip()]


def value_after(lines: list, label: str, start: int = 0):
    for i in range(start, len(lines) - 1):
        if lines[i] == label:
            return lines[i + 1]
    return None


def parse_roster_index(page: str) -> list:
    """Liste du panneau "ALL" : url, nom, note."""
    start = page.find('id="rlta-panel-all-')
    end = page.find('id="rlta-raw"', start)
    panel = page[start:end] if start != -1 else page
    pattern = re.compile(
        r'<a href="(/roster/wwe2k26/[^"]+)"[^>]*>\s*<div class="roster">\s*'
        r'<div class="roster-overall"><span[^>]*>\s*(\d+)\s*</span></div>.*?'
        r'<div class="roster_name">([^<]+)</div>',
        re.S,
    )
    seen, items = set(), []
    for url, overall, name in pattern.findall(panel):
        if url in seen:
            continue
        seen.add(url)
        items.append({'url': BASE + url, 'name': html.unescape(name).strip(), 'overall': int(overall)})
    return items


def parse_game_page(page: str) -> dict:
    lines = text_lines(page)
    try:
        start = lines.index('PROFILE INFO')
    except ValueError:
        start = 0

    alts = []
    if 'Alt. Character' in lines[start:]:
        i = lines.index('Alt. Character', start) + 1
        while i < len(lines) and lines[i] not in ('Main Series Debut', 'First Game', 'Real Life Profile', 'Signatures'):
            alts.append(lines[i])
            i += 1

    finishers = []
    block = re.search(r'game-finishers.*?</ul>', page, re.S)
    if block:
        for name in re.findall(r'<li><span class="field-entry"><span class="field-value ">([^<]+)</span>', block.group(0)):
            name = re.sub(r'\s+\d$', '', html.unescape(name).strip())
            if name not in finishers:
                finishers.append(name)

    profile = re.search(r'href="(/wrestlers/[^"]+)"', page[page.find('Real Life Profile'):]) if 'Real Life Profile' in page else None
    image = re.search(r'<meta property="og:image" content="([^"]+)"', page)

    return {
        'gender': (value_after(lines, 'Gender', start) or '').lower(),
        'rosterLabel': value_after(lines, 'Roster', start),
        'overall': value_after(lines, 'Overall Rating', start),
        'weightClass': value_after(lines, 'Weight Class', start),
        'alts': alts,
        'finishers': finishers,
        'profileUrl': BASE + profile.group(1) if profile else None,
        'image': image.group(1) if image else None,
    }


def parse_profile_page(page: str) -> dict:
    lines = text_lines(page)
    try:
        start = lines.index('Profile Info', lines.index('Profile Info') + 1)
    except ValueError:
        start = 0

    def metric(label: str):
        raw = value_after(lines, label, start)
        match = re.search(r'\((\d+)\s*(cm|kg)\)', raw or '')
        return int(match.group(1)) if match else None

    alignment = None
    turns = [m.start() for m in re.finditer('Face / Heel Turns', page)]
    if turns:
        section = text_lines(page[turns[-1]:turns[-1] + 3000])
        if 'to' in section:
            first = section[section.index('to') + 1].lower()
            if first in ('face', 'heel', 'tweener'):
                alignment = first

    title = re.search(r'<title>([^<:|]+)', page)

    return {
        'name': html.unescape(title.group(1)).strip() if title else None,
        'realName': value_after(lines, 'Real Name', start),
        'nationality': value_after(lines, 'Nationality', start),
        'billedFrom': value_after(lines, 'Billed From', start) or value_after(lines, 'Birth Place', start),
        'height': metric('Height'),
        'weight': metric('Weight'),
        'alignment': alignment,
    }


VARIANT_PATTERN = re.compile(r"'\d{2}|\(|\bWM\d+|\bRetro\b|\bMasked\b|^Elite |^Main Event |\"")


def pick_main(candidates: list, profile_name) -> dict:
    """Parmi les versions d'un même lutteur, garde la version "actuelle".

    La liste "Alt. Character" du site est alphabétique ("Elite Rhea Ripley" avant
    "Rhea Ripley"), elle ne dit donc pas quelle version est la principale.
    Ordre de préférence : même nom que le profil réel, brand active (pas Legend),
    nom sans marqueur de variante ('16, (Retro), Elite...), nom le plus court.
    """
    def score(c):
        label = (c['game']['rosterLabel'] or '').strip()
        return (
            c['item']['name'] != profile_name,
            label in ('Legend', 'Extra'),
            bool(VARIANT_PATTERN.search(c['item']['name'])),
            len(c['item']['name']),
        )

    return min(candidates, key=score)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('--out', default='modules/data/wwe2k26-roster.json')
    parser.add_argument('--cache', default='.cache/wwe2k26')
    parser.add_argument('--limit', type=int, default=0)
    parser.add_argument('--delay', type=float, default=0.4, help='pause entre deux requêtes (s)')
    parser.add_argument('--with-variants', action='store_true', help="garde toutes les versions (AJ Styles '16...)")
    parser.add_argument('--with-extras', action='store_true',
                        help='garde la catégorie "Extra" : persos MyRISE, célébrités')
    args = parser.parse_args()

    fetch = Fetcher(Path(args.cache), args.delay)
    index = parse_roster_index(fetch.get(ROSTER_URL))
    print(f'{len(index)} personnages dans le roster', file=sys.stderr)
    if args.limit:
        index = index[: args.limit]

    # 1. Fiches jeu, regroupées par lutteur réel (une fiche profil = une personne)
    groups = {}
    for n, item in enumerate(index, 1):
        print(f'[{n}/{len(index)}] {item["name"]}', file=sys.stderr)
        game = parse_game_page(fetch.get(item['url']))
        key = game['profileUrl'] or item['url']
        groups.setdefault(key, []).append({'item': item, 'game': game})

    # 2. Une version par lutteur (ou toutes avec --with-variants)
    result, skipped = [], 0
    for profile_url, candidates in groups.items():
        profile = parse_profile_page(fetch.get(profile_url)) if '/wrestlers/' in profile_url else {}
        chosen = candidates if args.with_variants else [pick_main(candidates, profile.get('name'))]
        skipped += len(candidates) - len(chosen)

        for c in chosen:
            item, game = c['item'], c['game']
            roster_label = (game['rosterLabel'] or '').strip()
            if roster_label == 'Extra' and not args.with_extras:
                skipped += 1
                continue
            overall = game['overall'] if (game['overall'] or '').isdigit() else item['overall']
            result.append({
                'name': item['name'],
                'slug': item['url'].rstrip('/').split('/')[-1],
                'gender': 'female' if game['gender'] == 'female' else 'male',
                'roster': ROSTER_MAP.get(roster_label.lower(), 'freeAgent'),
                'rosterLabel': roster_label or None,
                'overall': int(overall),
                'weightClass': game['weightClass'],
                'finisher': game['finishers'][0] if game['finishers'] else None,
                'finishers': game['finishers'],
                'alignment': profile.get('alignment'),
                'realName': profile.get('realName'),
                'hometown': profile.get('billedFrom'),
                'country': profile.get('nationality'),
                'height': profile.get('height'),
                'weight': profile.get('weight'),
                'image': game['image'],
                'source': item['url'],
            })

    result.sort(key=lambda r: r['name'].lower())
    out = Path(args.out)
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print(f'\n{len(result)} superstars écrites dans {out} ({skipped} variantes/extras ignorés)', file=sys.stderr)
    return 0


if __name__ == '__main__':
    sys.exit(main())
