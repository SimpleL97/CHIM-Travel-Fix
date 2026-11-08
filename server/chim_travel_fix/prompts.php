<?php

/**
 * CHIM Travel Fix by SaurOff - one line added to the player's turn: place names as the game
 * writes them, and the player houses CHIM knows. A line in the turn's own instruction is
 * followed far better than one among the rules at the end of the prompt.
 * Loaded inside a function: use $GLOBALS.
 */

require_once(__DIR__ . DIRECTORY_SEPARATOR . "ctf_lib.php");

try {
    $ctfCue = ctfCue();
    foreach (["inputtext", "inputtext_s", "ginputtext", "ginputtext_s"] as $ctfType) {
        if (isset($GLOBALS["PROMPTS"][$ctfType]["cue"]) && is_array($GLOBALS["PROMPTS"][$ctfType]["cue"])) {
            foreach ($GLOBALS["PROMPTS"][$ctfType]["cue"] as $ctfI => $ctfText) {
                // prompts.php can be loaded more than once in a request
                if (is_string($ctfText) && strpos($ctfText, "(Travel_To: ") === false) {
                    $GLOBALS["PROMPTS"][$ctfType]["cue"][$ctfI] = $ctfText . " " . $ctfCue;
                }
            }
        }
    }
} catch (Throwable $e) {
    error_log("[chim_travel_fix] cue: " . $e->getMessage());
}
