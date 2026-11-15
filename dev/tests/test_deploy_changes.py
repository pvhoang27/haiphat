import contextlib
import importlib.util
import io
import pathlib
import subprocess
import tempfile
import unittest

spec = importlib.util.spec_from_file_location("changed_deploy", pathlib.Path(__file__).parents[1] / "changed-deploy.py")
deploy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(deploy)


class DeployChangesTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.repo = pathlib.Path(self.temp.name)
        self.git("init", "--quiet")
        self.git("config", "user.name", "Deploy Tests")
        self.git("config", "user.email", "tests@example.test")
        self.write("README.md")
        self.initial = self.commit()

    def git(self, *args):
        return subprocess.check_output(["git", *args], cwd=self.repo, stderr=subprocess.PIPE).decode().strip()

    def write(self, name):
        path = self.repo / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text("fixture\n")

    def commit(self):
        self.git("add", "--all")
        self.git("commit", "--quiet", "-m", "Fixture")
        return self.git("rev-parse", "HEAD")

    def test_snapshot_and_docs_do_not_deploy(self):
        self.write("site-design/current/pages/1.html")
        self.write("docs/design.md")
        self.assertFalse(deploy.needs_deploy(self.initial, self.commit(), self.repo))

    def test_runtime_locations_and_whole_push(self):
        before = self.initial
        for path in ("hai-phat-vina/style.css", "hai-phat-vina-core/includes/design-sync.php",
                     "dev/deploy/remote.sh", ".github/workflows/deploy.yml", "dev/changed-deploy.py"):
            with self.subTest(path=path):
                self.write(path)
                after = self.commit()
                self.assertTrue(deploy.needs_deploy(before, after, self.repo))
                before = after
        self.write("docs/last-commit.md")
        self.assertTrue(deploy.needs_deploy(self.initial, self.commit(), self.repo))

    def test_snapshot_after_unshipped_code_compares_with_production_not_previous_push(self):
        self.write('hai-phat-vina/functions.php')
        unshipped = self.commit()
        self.write('site-design/current/pages/1.html')
        latest = self.commit()
        self.assertFalse(deploy.needs_deploy(unshipped, latest, self.repo))
        self.assertTrue(deploy.needs_deploy(self.initial, latest, self.repo))

    def test_deletion_and_moves_out_of_runtime_deploy(self):
        self.write("hai-phat-vina/deleted.php")
        self.write("hai-phat-vina/moved.php")
        before = self.commit()
        (self.repo / "hai-phat-vina/deleted.php").unlink()
        (self.repo / "hai-phat-vina/moved.php").rename(self.repo / "moved.php")
        self.assertTrue(deploy.needs_deploy(before, self.commit(), self.repo))

    def test_large_push_has_no_file_list_limit(self):
        for index in range(350):
            self.write(f"docs/{index}.md")
        self.write("hai-phat-vina/z-runtime.php")
        self.assertTrue(deploy.needs_deploy(self.initial, self.commit(), self.repo))

    def test_missing_base_deploys_and_missing_target_fails(self):
        with contextlib.redirect_stderr(io.StringIO()):
            self.assertTrue(deploy.needs_deploy("0" * 40, self.initial, self.repo))
            self.assertTrue(deploy.needs_deploy("f" * 40, self.initial, self.repo))
        with self.assertRaises(RuntimeError):
            deploy.needs_deploy(self.initial, "f" * 40, self.repo)
