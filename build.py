"""CHIM Travel Fix by SaurOff - assembles the release.

python3 -I build.py [release dir]   (any Python 3.8+, standard library only; no game files)
Produces:
  build/chim_travel_fix-<version>.dwpkg   CHIM server plugin package (schema 4)
  <release dir>/CHIM Travel Fix.zip       FOMOD archive for Nexus / Vortex / MO2; the version
                                          it replaces goes to <release dir>/archive/
"""
import hashlib, json, os, re, shutil, sys, zipfile

ROOT = os.path.dirname(os.path.abspath(__file__))
RELEASE_DIR = sys.argv[1] if len(sys.argv) > 1 else os.path.join(ROOT, "release")
NAME = "chim_travel_fix"
TITLE = "CHIM Travel Fix"

version = re.search(r'define\("CTF_VERSION", "([^"]+)"\)', open(os.path.join(ROOT, "server", NAME, "ctf_lib.php"), encoding="utf-8").read()).group(1)
for path, pattern in [("fomod/info.xml", r'MachineVersion="([^"]+)"'),
                      ("server/" + NAME + "/manifest.json", r'"version": "([^"]+)"')]:
    found = re.search(pattern, open(os.path.join(ROOT, path), encoding="utf-8").read()).group(1)
    assert found == version, f"{path} says {found}, ctf_lib.php says {version}"


def build_dwpkg(out):
    src = os.path.join(ROOT, "server", NAME)
    files = {}
    for name in sorted(os.listdir(src)):
        full = os.path.join(src, name)
        if os.path.isfile(full):
            files["server/" + name] = open(full, "rb").read()
    manifest = {"schema_version": 4, "name": NAME, "version": version, "author": "SaurOff",
                "description": TITLE + " server plugin", "server": {"mutable_paths": []}}
    files["manifest.json"] = (json.dumps(manifest, indent=2) + "\n").encode()
    sums = "".join(f"{hashlib.sha256(data).hexdigest()}  {p}\n" for p, data in sorted(files.items()))
    os.makedirs(os.path.dirname(out), exist_ok=True)
    with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as z:
        for p, data in sorted(files.items()):
            z.writestr(p, data)
        z.writestr("checksums.sha256", sums)


def copy(src, dst):
    os.makedirs(os.path.dirname(dst), exist_ok=True)
    shutil.copyfile(src, dst)


stage = os.path.join(ROOT, "build", "stage")
shutil.rmtree(stage, ignore_errors=True)
pkg = os.path.join(ROOT, "build", f"{NAME}-{version}.dwpkg")
build_dwpkg(pkg)

# 00 Core: only Data/CHIM/server-plugins/<name>/<version>.dwpkg - CHIM's game plugin
# uploads it to its server at game start. No plugin (.esp), no scripts.
copy(pkg, os.path.join(stage, "00 Core", "CHIM", "server-plugins", NAME, version + ".dwpkg"))
tools = os.path.join(stage, "10 Server Tools", TITLE + " Server Tools")
for f in ["Install-ServerPlugin.bat", "Install-ServerPlugin.ps1", "Uninstall-ServerPlugin.bat", "Uninstall-ServerPlugin.ps1"]:
    copy(os.path.join(ROOT, "server", f), os.path.join(tools, f))
for f in sorted(os.listdir(os.path.join(ROOT, "server", NAME))):
    copy(os.path.join(ROOT, "server", NAME, f), os.path.join(tools, NAME, f))
# the installer picture comes from art/render_banner.ps1 (a smaller copy of art/banner.png)
for f in ["info.xml", "ModuleConfig.xml", "images/banner.jpg"]:
    copy(os.path.join(ROOT, "fomod", f), os.path.join(stage, "fomod", f))
for f in ["README.txt", "CHANGELOG.txt"]:
    copy(os.path.join(ROOT, "docs", f), os.path.join(stage, f))


def zip_version(path):
    try:
        with zipfile.ZipFile(path) as z:
            return re.search(r'MachineVersion="([^"]+)"', z.read("fomod/info.xml").decode("utf-8")).group(1)
    except Exception:
        return None


os.makedirs(RELEASE_DIR, exist_ok=True)
out = os.path.join(RELEASE_DIR, TITLE + ".zip")
if os.path.exists(out):
    old = zip_version(out)
    if old and old != version:
        archive = os.path.join(RELEASE_DIR, "archive")
        os.makedirs(archive, exist_ok=True)
        dest = os.path.join(archive, f"{TITLE}-{old}.zip")
        if os.path.exists(dest):
            os.remove(dest)
        shutil.move(out, dest)
        print("archived", dest)
    else:
        os.remove(out)
with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as z:
    for dirpath, _, names in os.walk(stage):
        for n in sorted(names):
            full = os.path.join(dirpath, n)
            z.write(full, os.path.relpath(full, stage).replace(os.sep, "/"))
print("version", version)
print("package", pkg)
print("release", out, os.path.getsize(out), "bytes")
