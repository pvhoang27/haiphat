#!/usr/bin/env python3
"""Print whether a complete Git push changes production runtime files."""
import argparse
import subprocess
import sys

RUNTIME_PREFIXES = (b"hai-phat-vina/", b"hai-phat-vina-core/", b"dev/deploy/")
RUNTIME_FILES = {b".github/workflows/deploy.yml", b"dev/changed-deploy.py"}


def resolve_commit(revision, cwd=None):
    if not revision or set(revision) == {"0"}:
        return None
    result = subprocess.run(
        ["git", "rev-parse", "--verify", "--quiet", "--end-of-options", f"{revision}^{{commit}}"],
        cwd=cwd, capture_output=True, check=False,
    )
    return result.stdout.strip().decode("ascii") if result.returncode == 0 else None


def needs_deploy(before, after, cwd=None):
    target = resolve_commit(after, cwd)
    if target is None:
        raise RuntimeError("Cannot resolve target commit")
    base = resolve_commit(before, cwd)
    if base is None:
        print("Previous commit unavailable; deploying conservatively.", file=sys.stderr)
        return True
    result = subprocess.run(
        ["git", "diff", "--name-only", "-z", "--no-renames", base, target, "--"],
        cwd=cwd, capture_output=True, check=True,
    )
    return any(path in RUNTIME_FILES or path.startswith(RUNTIME_PREFIXES)
               for path in result.stdout.split(b"\0") if path)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("before")
    parser.add_argument("after")
    args = parser.parse_args()
    try:
        print("true" if needs_deploy(args.before, args.after) else "false")
    except (OSError, RuntimeError, subprocess.CalledProcessError) as error:
        sys.exit(f"Cannot detect deployment changes: {error}")
