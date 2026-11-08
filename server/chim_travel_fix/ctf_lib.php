<?php

/**
 * CHIM Travel Fix by SaurOff - shared code.
 *
 * CHIM turns an agent's Travel_To ("TravelTo@<place>") into "TravelToRaw@<location formid>"
 * in its action post-filter (lib/data_functions.php), looking the name up in the table
 * `locations` with pg_trgm similarity(). Two things break that lookup:
 *  - the query that matches the name sorts by a column the table does not have
 *    (created_at), so it fails, and only the second query - by REGION - is used;
 *  - CHIM's database runs with LC_CTYPE=C, where pg_trgm finds no trigrams in letters
 *    outside ASCII: for a Russian, Ukrainian, Polish, Czech, German, Chinese... game every
 *    similarity is 0.
 * Either way the "best" row is an arbitrary one, and the agent walks off to a random cave.
 *
 * This plugin hooks CHIM's action_post_process_fnct_ex, which runs before that post-filter,
 * matches the name in PHP (any language), and hands CHIM an exact TravelToRaw@<formid>.
 * CHIM leaves a TravelToRaw alone. When no place is close enough, the name goes on as
 * plain TravelTo@<name> - the game plugin then looks it up by itself - instead of a
 * random place.
 */

if (!defined("CTF_VERSION")) {
    define("CTF_VERSION", "1.0.0");
}

if (!function_exists("ctfLog")) {

function ctfLog(string $msg): void
{
    try {
        $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . "log";
        if (!is_dir($dir)) {
            return;
        }
        $file = $dir . DIRECTORY_SEPARATOR . "chim_travel_fix.log";
        if (is_file($file) && filesize($file) > 2 * 1024 * 1024) {
            @rename($file, $file . ".1");
        }
        @file_put_contents($file, date("Y-m-d H:i:s") . " " . $msg . "\n", FILE_APPEND);
    } catch (Throwable $e) {
        // logging never breaks a reply
    }
}

/** lower case, one kind of quotes and spaces, ё = е */
function ctfNorm($text): string
{
    $t = mb_strtolower(trim(strval($text)), "UTF-8");
    $t = str_replace(["ё", "\"", "'", "«", "»", "„", "“", "”", "’"], ["е", "", "", "", "", "", "", "", ""], $t);
    return trim(preg_replace('/\s+/u', " ", $t));
}

/**
 * "home", "my house" in the game languages Skyrim ships in (and Ukrainian): when asked for
 * together with a city, the player house there is meant.
 */
function ctfIsHome(string $want): bool
{
    $words = [
        "home", "house",                          // English
        "дом", "домой", "домик", "особняк",       // Russian
        "дім", "додому", "будинок", "хата",        // Ukrainian
        "haus", "zuhause", "heim",                // German
        "maison", "chez moi",                      // French
        "casa", "hogar",                           // Spanish, Italian, Portuguese
        "lar",                                     // Portuguese
        "dom", "domu",                             // Polish
        "dům", "domů",                             // Czech
    ];
    foreach ($words as $w) {
        if (preg_match('/(?<!\p{L})' . preg_quote($w, "/") . '(?!\p{L})/u', $want)) {
            return true;
        }
    }
    // Japanese, Chinese: no spaces between words
    return mb_strpos($want, "家") !== false || mb_strpos($want, "房子") !== false || mb_strpos($want, "屋敷") !== false;
}

/**
 * The location row that best fits $want, or null. $rows: name, region, formid, world, tags.
 * Score: exact name 1.0; "home" + a city -> that city's player house 0.95; a name that
 * contains the words asked for or is contained in them 0.6-0.8 (a part of a city,
 * "Riften - Ratway" for "Riften", 0.65, its square or market 0.75); otherwise how close
 * the spelling is. Below 0.6 nothing is close enough.
 */
function ctfMatch(string $want, array $rows): ?array
{
    $w = ctfNorm($want);
    if ($w === "") {
        return null;
    }
    $home = ctfIsHome($w);
    $houses = [];
    foreach ($rows as $r) {
        if (stripos(strval($r["tags"] ?? ""), "Player House") !== false) {
            $houses[] = $r;
        }
    }
    $cityNames = $home ? ctfCityNames($rows) : [];
    $best = null;
    $bestScore = 0.0;
    foreach ($rows as $r) {
        $n = ctfNorm($r["name"] ?? "");
        if ($n === "" || empty($r["formid"])) {
            continue;
        }
        $score = 0.0;
        if ($n === $w) {
            $score = 1.0;
        } else {
            if ($home && stripos(strval($r["tags"] ?? ""), "Player House") !== false) {
                // the city's name in what was asked: "мой дом в Вайтране", "my house in Whiterun".
                // The region may be stored in another language than the game's ("Riften" in a
                // Russian game): the city's own places ("Рифтен - Склад") give its name too.
                // the hold is wider than the city: a house in the city itself comes first
                $reg = ctfNorm($r["region"] ?? "");
                $names = [[$reg, 0.95], [ctfNorm($r["hold"] ?? ""), 0.92]];
                foreach ($cityNames[$reg] ?? [] as $c) {
                    $names[] = [$c, 0.95];
                }
                foreach ($names as [$city, $s]) {
                    if (mb_strlen($city) >= 4 && mb_strpos($w, mb_substr($city, 0, max(4, mb_strlen($city) - 2))) !== false) {
                        $score = max($score, $s);
                    }
                }
                if ($score === 0.0 && count($houses) === 1 && mb_strlen($w) <= 12) {
                    $score = 0.9; // just "home", and there is one house
                }
            }
            $lw = mb_strlen($w);
            $ln = mb_strlen($n);
            if ($score < 0.8 && $lw >= 4 && $ln >= 4 && (mb_strpos($n, $w) !== false || mb_strpos($w, $n) !== false)) {
                $score = max($score, 0.8 - 0.2 * abs($ln - $lw) / max($ln, $lw));
                if (mb_strpos($n, $w . " - ") === 0) {
                    // a part of the city asked for: its square or market first
                    $score = 0.65 + (preg_match('/(площад|рын|square|market|platz|markt|place|plaza|piazza|rynek|náměstí|广场|広場)/u', $n) ? 0.1 : 0.0);
                }
            }
            if ($score < 0.6) {
                similar_text($n, $w, $pct);
                $score = max($score, $pct / 100.0 * 0.9);
            }
        }
        if (!empty($r["world"])) {
            $score += 0.001; // as CHIM does: places with a world space first
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $r;
        }
    }
    if (!$best || $bestScore < 0.6) {
        return null;
    }
    return ["name" => strval($best["name"]), "formid" => strval($best["formid"]), "score" => round($bestScore, 3)];
}

/**
 * region -> the city names its places use: "Рифтен - Склад" in region "Riften" gives
 * "riften" => ["рифтен"]. Normalized.
 */
function ctfCityNames(array $rows): array
{
    $out = [];
    foreach ($rows as $r) {
        $name = strval($r["name"] ?? "");
        $reg = ctfNorm($r["region"] ?? "");
        $i = mb_strpos($name, " - ");
        if ($reg !== "" && $i !== false && $i >= 4) {
            $city = ctfNorm(mb_substr($name, 0, $i));
            if ($city !== $reg && !in_array($city, $out[$reg] ?? [], true)) {
                $out[$reg][] = $city;
            }
        }
    }
    return $out;
}

/**
 * The player houses worth naming to the AI: tagged "Player House" and "House", without
 * the extra rooms and storage cells some house mods tag too. At most $max.
 */
function ctfHouses(array $rows, int $max = 15): array
{
    $out = [];
    foreach ($rows as $r) {
        $tags = strval($r["tags"] ?? "");
        $name = trim(strval($r["name"] ?? ""));
        if ($name === "" || stripos($tags, "Player House") === false || !preg_match('/(^|,)House(,|$)/', $tags)
            || preg_match('/\b(storage|location|room|npcs)\b/i', $name)) {
            continue;
        }
        $reg = trim(strval($r["region"] ?? ""));
        $label = $name . ($reg !== "" && ctfNorm($reg) !== ctfNorm($name) ? " ({$reg})" : "");
        if (!in_array($label, $out, true)) {
            $out[] = $label;
        }
        if (count($out) >= $max) {
            break;
        }
    }
    return $out;
}

/** every known location, once per request */
function ctfLocations(): array
{
    if (isset($GLOBALS["CTF_LOCATIONS"])) {
        return $GLOBALS["CTF_LOCATIONS"];
    }
    $rows = [];
    try {
        $db = $GLOBALS["db"] ?? null;
        if ($db) {
            $got = $db->fetchAll("SELECT name, region, formid, world, tags FROM locations");
            if (is_array($got)) {
                $rows = $got;
            }
        }
    } catch (Throwable $e) {
        ctfLog("reading locations failed: " . $e->getMessage());
    }
    $GLOBALS["CTF_LOCATIONS"] = $rows;
    return $rows;
}

/** the place an action names, the way CHIM's post-filter cuts it */
function ctfPlaceOf(string $param): string
{
    $p = explode(",", $param)[0];
    $p = explode(" and ", $p)[0];
    $p = explode("(", $p)[0];
    $p = explode("--", $p)[0];
    return trim($p);
}

/**
 * The hook: TravelTo@<name> -> TravelToRaw@<formid>. Leaves alone what CHIM already handles
 * well: "Skyrim" (leaving a building), a name from the "places to go" list CHIM gave the
 * agent, and parameters in JSON.
 */
function ctfFixActions($actions)
{
    if (!is_array($actions)) {
        return $actions;
    }
    foreach ($actions as $n => $action) {
        if (!is_string($action)) {
            continue;
        }
        $parts = explode("|", $action, 3);
        if (count($parts) < 3) {
            continue;
        }
        $cmd = explode("@", $parts[2], 2);
        if (trim($cmd[0]) !== "TravelTo" || !isset($cmd[1])) {
            continue;
        }
        $param = trim($cmd[1]);
        $place = ctfPlaceOf($param);
        if ($place === "" || $param[0] === "{" || stripos($place, "Skyrim") === 0) {
            continue;
        }
        $listed = function_exists("DataPosibleLocationsToGo") ? (array) DataPosibleLocationsToGo() : [];
        if (in_array($param, $listed, true) || in_array($place, $listed, true)) {
            continue; // CHIM passes a listed place on by name
        }
        $hit = ctfMatch($place, ctfLocations());
        if ($hit) {
            $actions[$n] = "{$parts[0]}|{$parts[1]}|TravelToRaw@{$hit["formid"]}";
            ctfLog("{$parts[0]}: <{$place}> -> {$hit["name"]} ({$hit["formid"]}, score {$hit["score"]})");
        } else {
            // nothing close enough: CHIM would pick a random place - let it pass the name on
            // as it is (a name in its "places to go" list goes to the game unchanged)
            $GLOBALS["CACHE_POSIBLE_LOCATIONS_TO_GO"] = array_values(array_unique(array_merge($listed, [$place])));
            $actions[$n] = "{$parts[0]}|{$parts[1]}|TravelTo@{$place}";
            ctfLog("{$parts[0]}: <{$place}> - no known place close enough, passed on by name");
        }
    }
    return $actions;
}

/**
 * Added to the player's turn: write the place as the game names it, and the player's
 * houses CHIM knows (their names are in the game's language).
 */
function ctfCue(): string
{
    $player = strval($GLOBALS["PLAYER_NAME"] ?? "the player");
    $houses = ctfHouses(ctfLocations());
    return "(Travel_To: write the place exactly as the game names it, in the game's own language - as in the lists of places here, not translated."
        . ($houses ? " Houses a player can own, known so far: " . implode(", ", $houses) . " - when {$player} says \"home\" or \"my house\" in that city, it is that house, by this name." : "")
        . ")";
}

}
