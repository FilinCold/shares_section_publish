#!/usr/bin/env bash
# Проверка раздела /actions/ (список, карточки, деталь, 404).
# Запуск: bash scripts/check_site.sh <домен>
# Вывод: "OK <id>" / "FAIL <id> <причина>", код выхода != 0 при любой ошибке.

DOMAIN="${1:-}"
if [ -z "$DOMAIN" ]; then
  echo "Использование: bash scripts/check_site.sh <домен>" >&2
  exit 2
fi

BASE="https://$DOMAIN"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SEED="$ROOT/src/local/setup/seed-data.json"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

FAILS=0
ok()   { echo "OK $1"; }
fail() { echo "FAIL $1 ${2:-}"; FAILS=$((FAILS + 1)); }

# --- список: /actions/ доступна, без ошибок PHP/Bitrix ---
CODE=$(curl -sS -o "$TMP/list.html" -w '%{http_code}' "$BASE/actions/")
if [ "$CODE" != "200" ]; then
  fail list "HTTP $CODE (ожидалось 200)"
elif grep -qE 'Fatal error|Warning:|Bitrix\\Main' "$TMP/list.html"; then
  fail list "в теле найдено Fatal error|Warning:|Bitrix\\Main"
else
  ok list
fi

# --- карточки и деталь: разбор списка через python3 ---
python3 - "$SEED" "$TMP/list.html" "$TMP/detail_url.txt" "$TMP/detail_phrase.txt" <<'PY' > "$TMP/t2.out"
import json, re, sys, html

seed_path, list_path, url_out, phrase_out = sys.argv[1:5]
seed = json.load(open(seed_path, encoding='utf-8'))
body = open(list_path, encoding='utf-8', errors='replace').read()

# Карточки: от class="action-card" до следующей карточки / конца.
starts = [m.start() for m in re.finditer(r'class="[^"]*\baction-card\b[^"]*"', body)]
cards = []
for i, s in enumerate(starts):
    e = starts[i + 1] if i + 1 < len(starts) else len(body)
    cards.append(body[s:e])

errs = []
if len(cards) != len(seed):
    errs.append(f"карточек {len(cards)}, ожидалось {len(seed)}")

def plain(t):
    return re.sub(r'\s+', ' ', html.unescape(re.sub(r'<[^>]+>', ' ', t))).strip()

for card in cards:
    text = plain(card)
    item = next((a for a in seed if a['name'] in text), None)
    if item is None:
        errs.append("карточка не соответствует ни одной акции из seed")
        continue
    n = item['name']
    if '<img' not in card:
        errs.append(f"{n}: нет <img")
    if plain(item['preview_text']) not in text:
        errs.append(f"{n}: нет краткого описания")
    if item['discount'] not in text:
        errs.append(f"{n}: нет скидки «{item['discount']}»")
    if 'Подробнее' not in card:
        errs.append(f"{n}: нет «Подробнее»")
    if not re.search(r'\d{2}\.\d{2}\.\d{4}', text):
        errs.append(f"{n}: нет периода (даты)")

# Первая карточка: ссылка «Подробнее» и фраза detail_text
if cards:
    first = cards[0]
    m = re.search(r'<a\b[^>]*href="([^"]+)"[^>]*>\s*Подробнее', first)
    if not m:
        errs.append("первая карточка: ссылка «Подробнее» не найдена")
    else:
        open(url_out, 'w').write(m.group(1))
        text = plain(first)
        item = next((a for a in seed if a['name'] in text), None)
        if item:
            mm = re.search(r'SEED-[A-Z0-9-]+', item['detail_text'])
            open(phrase_out, 'w').write(mm.group(0) if mm else item['detail_text'][:40])

print("ERRS:" + " | ".join(errs) if errs else "ERRS:")
PY

T2ERR=$(sed -n 's/^ERRS://p' "$TMP/t2.out")
if [ -z "$(cat "$TMP/t2.out")" ]; then
  fail cards "ошибка разбора списка"
elif [ -n "$T2ERR" ]; then
  fail cards "$T2ERR"
else
  ok cards
fi

if [ -s "$TMP/detail_url.txt" ]; then
  URL=$(cat "$TMP/detail_url.txt")
  case "$URL" in
    http*) FULL="$URL" ;;
    *)     FULL="$BASE$URL" ;;
  esac
  PHRASE=$(cat "$TMP/detail_phrase.txt" 2>/dev/null)
  DCODE=$(curl -sS -o "$TMP/detail.html" -w '%{http_code}' "$FULL")
  if [ "$DCODE" != "200" ]; then
    fail detail "HTTP $DCODE для $URL"
  elif [ -z "$PHRASE" ] || ! grep -qF -- "$PHRASE" "$TMP/detail.html"; then
    fail detail "в детали нет фразы «$PHRASE»"
  else
    ok detail
  fi
else
  fail detail "ссылка «Подробнее» не получена"
fi

# --- 404: несуществующий код → 404 ---
NCODE=$(curl -sS -o /dev/null -w '%{http_code}' "$BASE/actions/no-such-code/")
if [ "$NCODE" = "404" ]; then ok notfound; else fail notfound "HTTP $NCODE (ожидалось 404)"; fi

[ "$FAILS" -eq 0 ] && exit 0 || exit 1
