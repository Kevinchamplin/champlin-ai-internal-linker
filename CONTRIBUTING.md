# Contributing

Thanks for considering a contribution to Champlin AI Internal Linker.

## Ground rules

- Open an issue before starting non-trivial work, so we can align on direction.
- Match the existing code style (PSR-12 + WordPress Coding Standards via `phpcs.xml`).
- Every PR must include a test, a justification for skipping tests, or be a docs-only change.
- Plugin Check must report **zero errors** on the resulting build. We don't merge work that regresses this.

## Local dev

```bash
composer install
npm install
npm run build
composer test
composer lint
```

## Branches

- Branch from `main` with `feature/`, `fix/`, or `chore/` prefixes.
- Open a PR against `main`. Squash-merge is the default.

## Releasing

Maintainer-only:

1. Bump the version in `champlin-ai-internal-linker.php`, `readme.txt` (`Stable tag`), `package.json`, and the changelog block.
2. Tag the release: `git tag v1.x.y && git push origin v1.x.y`
3. Create a GitHub Release with the changelog as the description.
4. Run `./scripts/build-wp-org.sh` and `svn ci` to the WP.org repo for the WP.org distribution.

## Code of Conduct

Be kind. Assume good faith. If a reviewer asks for a change, treat it as a starting point for discussion, not a directive.
