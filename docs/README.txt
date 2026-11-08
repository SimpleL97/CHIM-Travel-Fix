CHIM Travel Fix 1.0.0 by SaurOff
================================

CHIM AI agents told to go somewhere ("go to my house in Whiterun", "wait for me at
the inn") walk to the place they named - in any game language - instead of a
random place nearby, often a cave or a ruin.


THE PROBLEM
-----------
When an agent uses Travel_To, CHIM looks the place up in its table of known
locations. That lookup does not work:
- the query that compares the names fails (it sorts by a column the table does not
  have), so CHIM only compares the name asked for with the REGION names;
- CHIM's database compares text with a setting (LC_CTYPE=C) under which letters
  outside plain English - Cyrillic, Polish, Czech, German umlauts, Chinese,
  Japanese - never match anything.
The "best" location CHIM picks is then simply the first one in the table. The agent
says "I'll go home" and walks into a cave.


WHAT THIS MOD DOES
------------------
- Finds the place the agent named itself, in any language: the exact name first;
  "home" / "my house" with a city's name -> the player house in that city; a name
  that contains what was asked ("Riften" -> Riften's square, not its warehouse);
  then the closest spelling ("Bannered Mare" -> The Bannered Mare).
- Hands CHIM the exact place (TravelToRaw with the location's form id), which CHIM
  passes to the game as it is.
- When no known place is close enough, the agent's own words go to the game, which
  looks the place up by itself - never a random place.
- On your turn the AI is told to write place names exactly as the game names them,
  in the game's language (a Russian game names Whiterun "Вайтран"), and which player
  houses CHIM knows, so "go home" works.
- CHIM itself is not changed: when CHIM fixes this, the mod can simply be removed.


REQUIREMENTS
------------
- CHIM (AI agents, Nexus 126330), plugin and server (tested with 3.4.2)
Nothing else: no .esp, no scripts, nothing in your save.


INSTALLATION
------------
1. Install with Vortex or MO2. It only adds
   Data/CHIM/server-plugins/chim_travel_fix/1.0.0.dwpkg.
2. Start the CHIM server, then the game: CHIM's game plugin uploads bundled server
   plugins to its server at startup (HerikaServer/ext/chim_travel_fix).

If your CHIM version does not install bundled server plugins (SKSE/AIAgent.log has
no "[SERVER_PLUGIN_SYNC] Installed chim_travel_fix" line), choose "Manual server
install tools" in the installer and run CHIM Travel Fix Server
Tools/Install-ServerPlugin.bat once.

Uninstalling: run Uninstall-ServerPlugin.bat from the manual tools, then remove the mod.


GOOD TO KNOW
------------
- CHIM learns locations as you go: a house you have never been to is not in its
  table yet, so the agent cannot be sent there by name until you have visited it.
- "Houses a player can own" are the game's player houses CHIM knows (the game's
  own "Player House" tag), owned or not.
- Log: log/chim_travel_fix.log on the CHIM server - every place asked for and what
  it was matched to. Attach it to a bug report.


TOGETHER WITH MY OTHER MODS
---------------------------
Works on its own, and next to my CHIM bridges (each works on its own too):
- CHIM OStim Bridge (Nexus 194761, github.com/SimpleL97/CHIM-OStim-Bridge)
- CHIM ORomance Bridge (github.com/SimpleL97/CHIM-ORomance-Bridge)
- CHIM Vampire Bridge (github.com/SimpleL97/CHIM-Vampire-Bridge) - its thralls are
  often sent somewhere ("go to my house and wait there").


CREDITS
-------
CHIM by Dwemer Dynamics.
