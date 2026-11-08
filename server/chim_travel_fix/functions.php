<?php

/**
 * CHIM Travel Fix by SaurOff - the hook on the agents' actions (see ctf_lib.php).
 * Loaded inside a function: use $GLOBALS.
 */

require_once(__DIR__ . DIRECTORY_SEPARATOR . "ctf_lib.php");

// functions.php can be loaded more than once in a request: hook in once
if (empty($GLOBALS["CTF_HOOKED"])) {
    $GLOBALS["CTF_HOOKED"] = true;
    $GLOBALS["action_post_process_fnct_ex"][] = function ($actions) {
        try {
            return ctfFixActions($actions);
        } catch (Throwable $e) {
            error_log("[chim_travel_fix] " . $e->getMessage());
            ctfLog("error: " . $e->getMessage());
            return $actions;
        }
    };
}
