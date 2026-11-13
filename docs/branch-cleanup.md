# Legacy branch cleanup

All listed heads were verified as ancestors of Simplified-System at 39011d5382a740887369bffa43fe6d5bad69ed7e on 2026-11-13. Demo-System preserves that history.

Deletion is pending: the connector has no branch-delete operation, command-line Git has no write credentials in this session, and the browser is signed out.

Run this in Git Bash inside your authenticated local repository. It deletes only the named remote references, atomically. Each lease rejects deletion if that branch has changed since inspection. Main, Simplified-System, Demo-System and the category branches are not targeted.

```bash
git push --atomic \
  --force-with-lease=refs/heads/System-Consolidation:5158907479f9bf8af461fd2b29ed796f5cda6f7c \
  --force-with-lease=refs/heads/feature/beginner-readable-code:2349cceb1e16cb1d23efa69f6434f4bee42b20d2 \
  --force-with-lease=refs/heads/feature/code-readability-cleanup:a1c344c7379af968aa82af7937ad3d99aaf479a4 \
  --force-with-lease=refs/heads/feature/repair-alert-delivery:4fc90390a1396576b4e8d10db3e05aa9c5452c52 \
  --force-with-lease=refs/heads/feature/simple-access-activity:5158907479f9bf8af461fd2b29ed796f5cda6f7c \
  --force-with-lease=refs/heads/feature/simple-foundation:25ba69f2924f832cb90db95942c9aca7a6b4788b \
  --force-with-lease=refs/heads/feature/simple-intake-tracking:5158907479f9bf8af461fd2b29ed796f5cda6f7c \
  --force-with-lease=refs/heads/feature/simple-quotes-cash:5158907479f9bf8af461fd2b29ed796f5cda6f7c \
  --force-with-lease=refs/heads/feature/simple-repair-custody:5158907479f9bf8af461fd2b29ed796f5cda6f7c \
  --force-with-lease=refs/heads/feature/simple-reports:5158907479f9bf8af461fd2b29ed796f5cda6f7c \
  --force-with-lease=refs/heads/feature/sogod-branch-maps:10a5f7a7178aae603c8de5d04f91e92aacc52b9f \
  origin \
  :refs/heads/System-Consolidation \
  :refs/heads/feature/beginner-readable-code \
  :refs/heads/feature/code-readability-cleanup \
  :refs/heads/feature/repair-alert-delivery \
  :refs/heads/feature/simple-access-activity \
  :refs/heads/feature/simple-foundation \
  :refs/heads/feature/simple-intake-tracking \
  :refs/heads/feature/simple-quotes-cash \
  :refs/heads/feature/simple-repair-custody \
  :refs/heads/feature/simple-reports \
  :refs/heads/feature/sogod-branch-maps
```

After success, run `git fetch --prune` to remove stale remote-tracking names. This does not delete local branches. If a lease fails, inspect new work before retrying; do not remove the checks.
