# Bug report for CHIM: Travel_To goes to a random location (all languages, worst outside English)

**CHIM:** server 3.4.2 (HerikaServer), DwemerAI4Skyrim3 WSL distro, PostgreSQL 15.12, pg_trgm 1.6.
**Game:** Skyrim SE 1.6.1170, Russian game language (the same applies to any language
written outside ASCII).

## What happens

An agent is asked to go somewhere: "go to my house in Whiterun and wait there". It answers
`Travel_To` with target `Вайтран` (Whiterun in the Russian game), and then walks off to
`Камнепадная пещера`, a cave in another hold. Telling it "Honeyside" or "Breezehome" sends it to
some other unrelated place. The destination looks random, and that is what it is.

## Why: two independent bugs in the TravelTo action post-filter

`lib/data_functions.php`, TravelTo branch of the ACTION POST-FILTER (lines 6405-6406 in 3.4.2):

```php
$dbDestination=$GLOBALS["db"]->fetchOne("SELECT name, similarity(name, '$destinationName') AS sim,formid FROM locations ORDER BY sim DESC,case when world='' then 0 else 1 end DESC,created_at DESC LIMIT 1");
$dbDestinationRegion=$GLOBALS["db"]->fetchOne("SELECT name, similarity(region, '$destinationName') AS sim,formid FROM locations ORDER BY sim DESC LIMIT 1");
```

### 1. The name query always fails: `locations` has no `created_at`

```
dwemer=# SELECT name FROM locations ORDER BY created_at DESC LIMIT 1;
ERROR:  column "created_at" does not exist
HINT:  Perhaps you meant to reference the column "locations.updated_at".
```

`$dbDestination` is therefore never an array, and the code always falls through to
`$dbDestinationRegion`. That query compares the asked name with the **region** column, not
with the location name. In an English game too, "Breezehome" is then matched against region
names such as "Whiterun" or "Riften", and the first row with the highest score wins.

### 2. pg_trgm finds no trigrams in non-ASCII text, because the database uses LC_CTYPE=C

```
dwemer=# SELECT datcollate, datctype FROM pg_database WHERE datname = 'dwemer';
 C | C
dwemer=# SELECT similarity('Whiterun','Whiterun'), similarity('Вайтран','Вайтран'), show_trgm('Вайтран');
 1 | 0 | {}
```

pg_trgm decides what a "word character" is from the database's LC_CTYPE. Under `C`, letters
outside ASCII are not word characters, so Cyrillic, Greek, CJK, and most accented letters
(Polish, Czech, German ß/umlauts) produce no trigrams at all. Every `similarity()` is 0, even
for identical strings. With all scores 0, `ORDER BY sim DESC LIMIT 1` simply returns the first
row in the table, and that row becomes the travel destination.

There is also no minimum score: even in an English game, a destination with no real match
still becomes `TravelToRaw@<some formid>` instead of being passed on by name.

### Same pattern elsewhere

The same `similarity()` lookups (and so the same LC_CTYPE problem) appear in:
- `functions/functions.php` ~3340: TeleportNPC destination (name and region queries, no threshold);
- `lib/data_functions.php` `resolveTravelLocation()` ~8721;
- `lib/rolemaster_helpers.php` ~756, ~878, ~1117;
- `functions/functions.php` ~339: item lookup in `combined_descriptions` (`similarity > 0.55`).
  That one never matches non-ASCII names, but at least it does not pick a wrong item;
- `ui/api/chim_npc_manager.php` ~589.

## Suggested fixes

1. Drop `created_at` from the ORDER BY, or use `updated_at`.
2. Make trigram matching work for every language. Either:
   - create or convert the database with a UTF-8 aware ctype (`LC_CTYPE = 'C.UTF-8'` or
     `en_US.UTF-8`; existing pg_trgm indexes then need a REINDEX), or
   - do the fuzzy match in PHP (`mb_strtolower` plus `similar_text`/`levenshtein` on the
     few hundred location rows), which is independent of the database locale.
3. Add a minimum score (for example `sim >= 0.3`). When nothing reaches it, keep
   `TravelTo@<name>` so the game plugin resolves the name itself, instead of using the top row.
4. Optional: when the asked text contains "home/house" and a city, prefer the location tagged
   `Player House` in that region. Agents are usually told "go to my house in Whiterun" without
   the house's name.

## Workaround in the meantime

**CHIM Travel Fix** (server plugin, SaurOff). It hooks `action_post_process_fnct_ex`, which runs
before the post-filter. The hook matches the name in PHP for any language, with a threshold,
and hands the post-filter an exact `TravelToRaw@<formid>`. When nothing matches, it passes
`TravelTo@<name>` through unchanged. It does not modify CHIM's files, and it can be removed
once CHIM fixes this.
