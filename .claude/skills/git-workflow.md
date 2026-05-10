# Git Workflow Skill

## Rules

- Feature branches only — no direct commits to `main`
- Conventional commits (see format below)
- Atomic commits — one logical change per commit
- PR required before merge
- No direct push to `main` or `master`

## Branch naming

```
feature/report-lost-animal
fix/geolocation-timeout
refactor/animal-report-repository
chore/upgrade-symfony-8-0-1
docs/add-setup-guide
```

## Conventional commits format

```
<type>(<scope>): <short description>

[optional body]

[optional footer]
```

Types:
- `feat` — new feature
- `fix` — bug fix
- `refactor` — code change without behavior change
- `test` — add or update tests
- `chore` — tooling, deps, config
- `docs` — documentation only
- `perf` — performance improvement
- `ci` — CI/CD changes

Examples:
```
feat(animal-report): add photo upload to lost animal form
fix(geolocation): handle browser permission denial gracefully
refactor(domain): extract Location into a Value Object
test(controller): add functional test for report creation flow
chore(deps): upgrade symfony/ux-turbo to 3.1
```

## Flow

```
main
 └── feature/my-feature
      ├── commit: feat(module): implement X
      ├── commit: test(module): add unit tests for X
      └── PR → review → squash merge → main
```

## PR checklist before merge

- [ ] All CI checks pass (lint, PHPStan, tests, security)
- [ ] Self-reviewed diff
- [ ] Tests added for new behavior
- [ ] No debug code (`dump()`, `dd()`, `var_dump()`)
- [ ] No commented-out code
- [ ] Migration generated if schema changed

## Git config

```bash
# Always rebase on pull to keep history clean
git config pull.rebase true

# Sign commits if team requires it
git config commit.gpgsign true
```
