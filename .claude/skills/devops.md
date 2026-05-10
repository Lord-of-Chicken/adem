# DevOps Skill

## Package manager

**Always use `bun` instead of `npm`** — faster, compatible with npm packages.

```bash
bun install          # instead of npm install
bun add <pkg>        # instead of npm install <pkg>
bun run <script>     # instead of npm run <script>
```

## Stack (confirmed in this project)

- **Docker** + `compose.yaml` / `compose.override.yaml` at project root
- **FrankenPHP** (target production runtime — PHP + Caddy in one binary)
- **Caddy** (TLS termination, HTTP/2, HTTP/3)
- **PostgreSQL 16** (`postgres:16-alpine` in compose.yaml)
- **Redis** (add for cache + sessions in production)

## Local development

```bash
# Start services
docker compose up -d

# Symfony console (always use symfony console, not php bin/console)
symfony console ...
# or with Docker
docker compose exec php symfony console ...

# Database setup
symfony console doctrine:migrations:migrate

# Clear cache
symfony console cache:clear
```

## FrankenPHP specifics

```dockerfile
# Dockerfile (FrankenPHP)
FROM dunglas/frankenphp

RUN install-php-extensions \
    pdo_pgsql \
    redis \
    intl \
    opcache \
    apcu

COPY . /app
WORKDIR /app

RUN composer install --no-dev --optimize-autoloader
RUN php bin/console cache:warmup --env=prod  # bin/console dans Dockerfile (symfony CLI absent)
```

## CI/CD pipeline — mandatory steps

```yaml
# .github/workflows/ci.yml (example)
steps:
  - name: PHP CS Fixer
    run: vendor/bin/php-cs-fixer fix --dry-run --diff

  - name: PHPStan
    run: vendor/bin/phpstan analyse --level=max
    # Install: composer require --dev phpstan/phpstan phpstan/phpstan-symfony

  - name: PHPUnit
    run: php bin/phpunit --testdox

  - name: Security check
    run: symfony security:check
    # Also: composer audit

  - name: Build assets
    run: symfony console asset-map:compile
```

## Environment variables

```bash
# .env — defaults and documentation
APP_ENV=dev
APP_SECRET=change_me
DATABASE_URL="postgresql://app:!ChangeMe!@database:5432/app"

# .env.local — local overrides (gitignored)
# .env.test — test environment
# .env.prod — never committed — use Docker secrets or Symfony Secrets vault
```

## Production requirements

- **Monitoring**: Sentry or equivalent for error tracking
- **Logs**: structured JSON logs via Monolog (`symfony/monolog-bundle`)
- **Health check**: `/health` endpoint + Docker healthcheck
- **Backups**: PostgreSQL `pg_dump` on schedule, offsite storage
- **Rollback strategy**: keep previous Docker image tagged, `docker service update --rollback`
- **OPcache + APCu** enabled in production PHP config

## Rules

- No infrastructure changes without updating `compose.yaml`
- Secrets never in git — use `.env.local` locally, Symfony Secrets vault in prod
- Migrations run automatically on deploy (or explicitly in CI)
- Production assets compiled: `symfony console asset-map:compile`
- Always test Docker build locally before pushing to CI
