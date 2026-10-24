# Contributing

Thank you for helping with PTD GTFS Schedules. Bug reports, ideas and pull requests are all welcome.

## Reporting a problem or an idea

[Open an issue](https://github.com/publictransitdata/WP-GTFS-schedules/issues/new/choose). For a bug, include the plugin, WordPress and PHP versions and, if you can, a link to the GTFS feed. Security problems go through the [security policy](SECURITY.md) instead.

## Pull requests

1. For anything bigger than a small fix, open an issue first, so we can agree on the approach.
2. Create a branch from `main`, for example `feat/stop-search` or `fix/night-lines`.
3. Run the checks before you push:
   ```bash
   composer install
   composer check
   ```
4. If you changed translatable strings, translate the new ones in `languages/*.po` and run `composer i18n`.
5. Open the pull request against `main` and fill in the template.

Every pull request needs green CI. Pull requests from outside the organisation also need an approval from a maintainer. They are merged with a merge commit, so the commits of the branch stay in the history.

## Code

- WordPress Coding Standards (PHPCS) and PHPStan level 6 must pass.
- Templates in `templates/` are part of the public interface: themes copy them, so change their variables with care.
- Plain JavaScript without a build step, so the plugin stays readable as shipped.

## Commit messages

[Conventional Commits](https://www.conventionalcommits.org/): `feat: …`, `fix: …`, `docs: …`, `chore: …`. Mark breaking changes with `!`, for example `feat!: rename the shortcodes`.

## Licence

By contributing, you agree that your contribution is licensed under GPL-2.0-or-later, like the rest of the plugin.
