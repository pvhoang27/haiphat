import importlib.util
import json
import pathlib
import tempfile
import unittest

spec = importlib.util.spec_from_file_location("sync_design", pathlib.Path(__file__).parents[1] / "sync-design.py")
sync = importlib.util.module_from_spec(spec)
spec.loader.exec_module(sync)


class DesignSyncTests(unittest.TestCase):
    def export(self):
        manifest = {"schema": 1, "theme": "hai-phat-vina", "site": "https://example.test/", "pages": []}
        return {"schema": 1, "files": {"manifest.json": json.dumps(manifest), "global-styles.json": '{"version":3}', "parts/header.html": '<!-- wp:site-title /-->'}}

    def test_only_allowed_paths_and_types(self):
        for path in ("../../.github/workflows/deploy.yml", "parts/../../bad.html", "posts/1.html", "uploads/a.jpg", "wp-config.php"):
            data = self.export()
            data["files"][path] = "bad"
            with self.assertRaises(ValueError):
                sync.validate(json.dumps(data))

    def test_reject_executable_content_and_metadata_dump(self):
        data = self.export()
        data["files"]["parts/header.html"] = "<?php system('id');"
        with self.assertRaises(ValueError):
            sync.validate(json.dumps(data))
        data = self.export()
        manifest = json.loads(data["files"]["manifest.json"])
        manifest["users"] = []
        data["files"]["manifest.json"] = json.dumps(manifest)
        with self.assertRaises(ValueError):
            sync.validate(json.dumps(data))

    def test_unpublished_removed_and_noop_deterministic(self):
        files = sync.validate(json.dumps(self.export()))
        with tempfile.TemporaryDirectory() as directory:
            root = pathlib.Path(directory) / "current"
            sync.write_export(files, root)
            first = (root / "parts/header.html").read_bytes()
            sync.write_export(files, root)
            self.assertEqual(first, (root / "parts/header.html").read_bytes())
            (root / "pages").mkdir()
            (root / "pages/99.html").write_text("Previously public page")
            sync.write_export(files, root)
            self.assertFalse((root / "pages/99.html").exists())

    def test_symlink_cannot_escape_generated_directory(self):
        files = sync.validate(json.dumps(self.export()))
        with tempfile.TemporaryDirectory() as directory:
            base = pathlib.Path(directory)
            (base / "outside").mkdir()
            (base / "current").mkdir()
            (base / "current/parts").symlink_to(base / "outside", target_is_directory=True)
            with self.assertRaises(ValueError):
                sync.write_export(files, base / "current")
            self.assertFalse((base / "outside/header.html").exists())

    def test_oversize_and_unindexed_pages_rejected(self):
        with self.assertRaises(ValueError):
            sync.validate(b" " * (sync.MAX_BYTES + 1))
        data = self.export()
        data["files"]["pages/1.html"] = "unindexed"
        with self.assertRaises(ValueError):
            sync.validate(json.dumps(data))


if __name__ == "__main__":
    unittest.main()
