#!/usr/bin/env python3
"""Build the installable zips and the delivery bundle for the media bank plugins.

    python3 scripts/build_release.py [--out DIR]

Writes, into DIR (default: ./dist):
  contenttype_mediabank_<version>.zip   installable with Site administration > Plugins > Install plugins
  local_mediabank_<version>.zip         installable the same way (install the content type first)
  mediabank-<release>-bundle.zip        both zips and the guides, for sending to a client; NOT installable itself

Each plugin zip has one top folder called "mediabank", which is what Moodle's installer expects. The zips are
reproducible: file order and timestamps come from the files and the last commit, not from the time of the build.
Run scripts/validate_plugin_zip.php against a Moodle tree to check a zip the way the installer does.
"""
import argparse
import re
import subprocess
import sys
import zipfile
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent
PLUGINS = ['contenttype_mediabank', 'local_mediabank']
SKIP_DIR_NAMES = {'node_modules', 'vendor', '__pycache__'}
GUIDES = ['INSTALL.md', 'ADMIN-GUIDE.md', 'TEACHER-GUIDE.md']


def read_version(plugin: str) -> tuple[str, str]:
    text = (REPO / plugin / 'version.php').read_text()
    version = re.search(r"\$plugin->version\s*=\s*(\d+)", text).group(1)
    release = re.search(r"\$plugin->release\s*=\s*'([^']+)'", text).group(1)
    return version, release


def commit_time() -> int:
    out = subprocess.run(['git', 'log', '-1', '--format=%ct'], cwd=REPO, capture_output=True, text=True)
    return int(out.stdout.strip()) if out.returncode == 0 and out.stdout.strip() else 315532800


def included(plugin_dir: Path):
    for path in sorted(plugin_dir.rglob('*')):
        relative = path.relative_to(plugin_dir)
        parts = relative.parts
        if any(part.startswith('.') or part in SKIP_DIR_NAMES for part in parts):
            continue
        if path.suffix == '.zip':
            continue
        yield path, relative


def add(zf: zipfile.ZipFile, name: str, data: bytes, timestamp: tuple, mode: int) -> None:
    info = zipfile.ZipInfo(name, timestamp)
    info.compress_type = zipfile.ZIP_DEFLATED
    info.external_attr = mode << 16
    zf.writestr(info, data)


def build_plugin(plugin: str, out: Path, timestamp: tuple) -> Path:
    version, _ = read_version(plugin)
    target = out / f'{plugin}_{version}.zip'
    with zipfile.ZipFile(target, 'w') as zf:
        for path, relative in included(REPO / plugin):
            name = 'mediabank/' + relative.as_posix()
            if path.is_dir():
                add(zf, name + '/', b'', timestamp, 0o40755)
            else:
                add(zf, name, path.read_bytes(), timestamp, 0o100644)
    return target


def build_bundle(zips: list, out: Path, timestamp: tuple) -> Path:
    _, release = read_version('contenttype_mediabank')
    target = out / f'mediabank-{release}-bundle.zip'
    root = f'mediabank-{release}-bundle/'
    with zipfile.ZipFile(target, 'w') as zf:
        for z in zips:
            add(zf, root + z.name, z.read_bytes(), timestamp, 0o100644)
        for guide in GUIDES:
            source = REPO / 'docs' / guide
            if source.exists():
                add(zf, root + guide, source.read_bytes(), timestamp, 0o100644)
            else:
                print(f'WARNING: docs/{guide} is missing, not in the bundle', file=sys.stderr)
        for plugin, label in (('contenttype_mediabank', 'contenttype'), ('local_mediabank', 'local')):
            source = REPO / plugin / 'CHANGES.md'
            add(zf, root + f'CHANGES-{label}_mediabank.md', source.read_bytes(), timestamp, 0o100644)
    return target


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument('--out', default=str(REPO / 'dist'), help='folder for the zips (default: ./dist)')
    args = parser.parse_args()
    out = Path(args.out)
    out.mkdir(parents=True, exist_ok=True)
    when = __import__('time').gmtime(commit_time())
    timestamp = (max(when.tm_year, 1980), when.tm_mon, when.tm_mday, when.tm_hour, when.tm_min, when.tm_sec)
    zips = [build_plugin(plugin, out, timestamp) for plugin in PLUGINS]
    bundle = build_bundle(zips, out, timestamp)
    for z in zips + [bundle]:
        print(f'{z}  ({z.stat().st_size} bytes)')
    return 0


if __name__ == '__main__':
    sys.exit(main())
