# Debugging Skill

## Systematic approach

1. **Reproduce** — isolate the exact conditions that trigger the issue
2. **Check logs** — `var/log/dev.log` or `tail -f var/log/dev.log | grep ERROR`
3. **Symfony Profiler** — `/_profiler` in dev for HTTP issues (exceptions, queries, events)
4. **Isolate layer** — is it Domain, Application, Infrastructure, or UI?
5. **Inspect database** — check actual DB state, not assumed state
6. **Validate assumptions** — add temporary `dump()` / `dd()` only in dev, remove before commit

## Common Symfony issues

### Container / Autowiring
```bash
symfony console debug:container MyService
symfony console debug:autowiring MyInterface
```

### Routing
```bash
symfony console debug:router
symfony console router:match /reports/123
```

### Doctrine
```bash
# Check schema diff
symfony console doctrine:schema:validate

# Raw query log — add to doctrine.yaml in dev
doctrine:
    dbal:
        logging: true

# DBAL profiler shows all queries in Symfony Profiler
```

### Messenger
```bash
# Check failed messages
symfony console messenger:failed:show

# Retry failed
symfony console messenger:failed:retry

# Check queue status
symfony console messenger:stats
```

### Assets (Asset Mapper)
```bash
# Recompile assets
symfony console asset-map:compile

# Check importmap
symfony console debug:asset-map
```

## Debugging tools in dev

```php
// Symfony VarDumper (already installed via debug-bundle)
dump($variable);   // continues execution
dd($variable);     // stops execution

// In Twig
{{ dump(variable) }}
```

## Output format

Always provide:

1. **Root cause** — the actual line and reason it fails
2. **Fix** — minimal change that resolves it
3. **Prevention** — how to avoid this class of issue in future (test, validation, type, etc.)

## Rules

- Never leave `dump()` or `dd()` in committed code
- Always check the Symfony Profiler before guessing
- Reproduce in a test if the bug could regress
- Fix root cause — never silence exceptions or add `try/catch` to hide problems
