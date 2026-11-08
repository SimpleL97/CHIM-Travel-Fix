<?php

/**
 * CHIM Travel Fix - tests of the place matching and the action hook, without CHIM.
 * php tests/test_match.php   (exit code 1 on a failure)
 * The rows are real rows of CHIM's `locations` table: a Russian game, plus English and
 * German names the way an English or German game stores them.
 */

require_once(__DIR__ . "/../server/chim_travel_fix/ctf_lib.php");

$rows = [
    ["name" => "Вайтран", "region" => "Вайтран", "formid" => "100950", "world" => "Whiterun", "tags" => "Habitation,City,"],
    ["name" => "Дом теплых ветров", "region" => "Вайтран", "formid" => "129135", "world" => "", "tags" => "Dwelling,House,Player House,"],
    ["name" => "Гарцующая кобыла", "region" => "Вайтран", "formid" => "129133", "world" => "", "tags" => "Dwelling,Inn,"],
    ["name" => "Ветреный пик", "region" => "Вайтран", "formid" => "102121", "world" => "Skyrim", "tags" => "Dungeon,Draugr Crypt,Nordic Ruin,"],
    ["name" => "Камнепадная пещера", "region" => "Рифт", "formid" => "1004250", "world" => "Skyrim", "tags" => ""],
    ["name" => "Рифтен - Главная площадь", "region" => "Riften", "formid" => "290325", "world" => "", "tags" => ""],
    ["name" => "Рифтен - Крысиная нора", "region" => "Riften", "formid" => "243825", "world" => "", "tags" => ""],
    ["name" => "Рифтен - Склад", "region" => "Riften", "formid" => "140883", "world" => "", "tags" => "Dwelling,Store,"],
    // a house whose region is stored in English in a Russian game, and its hold
    ["name" => "Медовик", "region" => "Riften", "hold" => "Рифт", "formid" => "100010", "world" => "", "tags" => "Dwelling,Player House,House,"],
    // a house of the same hold outside the city: the one in the city comes first
    ["name" => "Жилище в тундре", "region" => "Жилище в тундре", "hold" => "Вайтран", "formid" => "100011", "world" => "", "tags" => "Dwelling,House,Player House,"],
    // a house mod's storage cell, tagged as a player house too
    ["name" => "Luxury Suite Storage Location", "region" => "Luxury Suite Exterior", "hold" => "Вайтран", "formid" => "100012", "world" => "", "tags" => "Dwelling,Inn,Player House,"],
];
// an English and a German game
$rowsEn = [
    ["name" => "Riften", "region" => "Riften", "formid" => "100005", "world" => "Riften", "tags" => "Habitation,City,"],
    ["name" => "Honeyside", "region" => "Riften", "formid" => "100001", "world" => "", "tags" => "Dwelling,House,Player House,"],
    ["name" => "Breezehome", "region" => "Whiterun", "formid" => "100002", "world" => "", "tags" => "Dwelling,House,Player House,"],
    ["name" => "The Bannered Mare", "region" => "Whiterun", "formid" => "100003", "world" => "", "tags" => "Dwelling,Inn,"],
    ["name" => "Haus Warmer Winde", "region" => "Weißlauf", "formid" => "100004", "world" => "", "tags" => "Dwelling,House,Player House,"],
];

$cases = [
    // rows, asked => formid expected (null: nothing close enough)
    [$rows, "Вайтран", "100950"],
    [$rows, "вайтран", "100950"],
    [$rows, "Дом теплых ветров", "129135"],
    [$rows, "Дом тёплых ветров", "129135"],
    [$rows, "мой дом в Вайтране", "129135"],
    [$rows, "дом в Вайтране", "129135"],
    [$rows, "Гарцующая кобыла", "129133"],
    [$rows, "Рифтен", "290325"],
    [$rows, "Каменная пещера", "1004250"],
    [$rows, "мой дом в Рифтене", "100010"],
    [$rows, "Медовик", "100010"],
    [$rows, "Совнгард", null],
    [$rows, "", null],
    [$rowsEn, "Honeyside", "100001"],
    [$rowsEn, "honeyside in Riften", "100001"],
    [$rowsEn, "my house in Riften", "100001"],
    [$rowsEn, "my house in Whiterun", "100002"],
    [$rowsEn, "Bannered Mare", "100003"],
    [$rowsEn, "mein Haus in Weißlauf", "100004"],
    [$rowsEn, "Morthal", null],
];

$fail = 0;
foreach ($cases as [$set, $ask, $want]) {
    $hit = ctfMatch($ask, $set);
    $got = $hit ? $hit["formid"] : null;
    $ok = $got === $want;
    $fail += $ok ? 0 : 1;
    printf("%s  %-28s => %s\n", $ok ? "ok  " : "FAIL", $ask, $hit ? "{$hit["name"]} ({$hit["formid"]}, {$hit["score"]})" : "-");
}

// the hook: a match becomes TravelToRaw, "Skyrim" and listed places stay, no match passes the name on
$GLOBALS["CTF_LOCATIONS"] = $rows;
$GLOBALS["CACHE_POSIBLE_LOCATIONS_TO_GO"] = ["Гарцующая кобыла"];
function DataPosibleLocationsToGo() { return $GLOBALS["CACHE_POSIBLE_LOCATIONS_TO_GO"]; }
$out = ctfFixActions([
    "Agent|command|TravelTo@Вайтран",
    "Agent|command|TravelTo@Skyrim",
    "Agent|command|TravelTo@Гарцующая кобыла",
    "Agent|command|TravelTo@Морфал (the town)",
    "Agent|command|Follow@Player",
]);
$expect = [
    "Agent|command|TravelToRaw@100950",
    "Agent|command|TravelTo@Skyrim",
    "Agent|command|TravelTo@Гарцующая кобыла",
    "Agent|command|TravelTo@Морфал",
    "Agent|command|Follow@Player",
];
foreach ($expect as $i => $e) {
    $ok = $out[$i] === $e;
    $fail += $ok ? 0 : 1;
    printf("%s  hook %-40s => %s\n", $ok ? "ok  " : "FAIL", $e, $out[$i]);
}
$ok = in_array("Морфал", $GLOBALS["CACHE_POSIBLE_LOCATIONS_TO_GO"], true);
$fail += $ok ? 0 : 1;
printf("%s  an unknown place is listed for CHIM, so it is passed on by name\n", $ok ? "ok  " : "FAIL");

// the houses named to the AI: no storage cells, the region shown only when it is not the name
$houses = ctfHouses($rows);
$ok = $houses === ["Дом теплых ветров (Вайтран)", "Медовик (Riften)", "Жилище в тундре"];
$fail += $ok ? 0 : 1;
printf("%s  houses for the AI: %s\n", $ok ? "ok  " : "FAIL", implode(", ", $houses));

echo $fail ? "\n$fail FAILED\n" : "\nall passed\n";
exit($fail ? 1 : 0);
