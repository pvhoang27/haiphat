#!/usr/bin/env python3
"""Validate an untrusted SSH export before writing to the generated Git subtree."""
import json
import pathlib
import re
import sys

MAX_BYTES = 20 * 1024 * 1024
PATH = re.compile(r"(?:pages|navigation|patterns)/[1-9][0-9]*\.html|(?:templates|parts)/[a-z0-9][a-z0-9_-]*\.html|(?:manifest|global-styles)\.json")


def validate(raw):
    if len(raw) > MAX_BYTES:
        raise ValueError("Design export exceeds 20 MiB")
    data = json.loads(raw)
    if not isinstance(data, dict) or set(data) != {"schema", "files"} or data["schema"] != 1:
        raise ValueError("Unsupported export envelope")
    files = data["files"]
    if not isinstance(files, dict) or len(files) > 5000 or not {"manifest.json", "global-styles.json"} <= files.keys():
        raise ValueError("Incomplete or oversized export")
    for name, content in files.items():
        if not PATH.fullmatch(name) or not isinstance(content, str) or "\x00" in content:
            raise ValueError("Unexpected source file or binary content")
        if "<?" in content:
            raise ValueError("Executable PHP is not accepted in design source")
    manifest = json.loads(files["manifest.json"])
    if set(manifest) != {"schema", "theme", "site", "pages"} or manifest["schema"] != 1 or manifest["theme"] != "hai-phat-vina":
        raise ValueError("Invalid design manifest")
    if not isinstance(manifest["site"], str) or not manifest["site"].startswith(("http://", "https://")) or not isinstance(manifest["pages"], list):
        raise ValueError("Invalid source site or page index")
    indexed = set()
    for page in manifest["pages"]:
        if not isinstance(page, dict) or set(page) != {"file", "path", "title", "template", "front_page", "posts_page"}:
            raise ValueError("Unexpected page metadata")
        if not all(isinstance(page[key], str) for key in ("file", "path", "title", "template")) or not all(isinstance(page[key], bool) for key in ("front_page", "posts_page")):
            raise ValueError("Invalid page metadata type")
        if page["file"] not in files or not page["file"].startswith("pages/") or page["file"] in indexed:
            raise ValueError("Missing or duplicate page source")
        indexed.add(page["file"])
    if indexed != {name for name in files if name.startswith("pages/")}:
        raise ValueError("Unindexed page source")
    styles = json.loads(files["global-styles.json"])
    if not isinstance(styles, dict) or not set(styles) <= {"version", "styles", "settings"} or styles.get("version") != 3:
        raise ValueError("Unexpected global styles fields")
    return files


def write_export(files, root):
    # Only this generated subtree is mutable; never follow repo symlinks.
    root = pathlib.Path(root)
    for parent in (root, *root.parents):
        if parent.is_symlink():
            raise ValueError("Symlink in export destination")
    if root.exists():
        for path in root.rglob("*"):
            if path.is_symlink() or (path.is_file() and not PATH.fullmatch(path.relative_to(root).as_posix())):
                raise ValueError("Unexpected file in generated design subtree")
    root.mkdir(parents=True, exist_ok=True)
    for name, content in sorted(files.items()):
        path = root / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(content, encoding="utf-8")
    # Deleted/unpublished sources disappear from the current snapshot, not history.
    for path in root.rglob("*"):
        if path.is_file() and path.relative_to(root).as_posix() not in files:
            path.unlink()


if __name__ == "__main__":
    source = pathlib.Path(sys.argv[1])
    with source.open("rb") as stream:
        files = validate(stream.read(MAX_BYTES + 1))
    write_export(files, pathlib.Path(__file__).resolve().parent.parent / "site-design" / "current")
    print(f"Validated {len(files)} design source files; no media or database dump.")
