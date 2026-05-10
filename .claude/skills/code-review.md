# Code Review Skill

## Checklist

### Architecture
- [ ] Business logic is in Domain or Application layer, not in Controller or Twig
- [ ] Module boundaries respected — no direct cross-module entity imports
- [ ] Repository interface in Domain, implementation in Infrastructure
- [ ] Commands/Queries return correct types (void / DTO)

### Code quality
- [ ] `declare(strict_types=1)` on every file
- [ ] No `mixed` types without justification
- [ ] Constructor promotion used where applicable
- [ ] No public mutable properties on domain entities (use asymmetric visibility or methods)
- [ ] Naming: classes in PascalCase, methods/vars in camelCase, no abbreviations

### Duplication & simplicity
- [ ] No copy-paste code — extract to a shared service or trait if needed
- [ ] No over-engineering — the simplest solution that works
- [ ] No unused imports, unused variables, dead code

### Tests
- [ ] Unit tests for domain logic
- [ ] Functional test for the happy path of each controller
- [ ] Edge cases covered (empty input, not found, access denied)
- [ ] No test that only tests the framework (avoid testing `find()` without domain logic)

### Performance
- [ ] No N+1 queries (check DQL/QueryBuilder for missing joins)
- [ ] No `findAll()` without pagination
- [ ] Heavy operations dispatched to Messenger async queue

### Security
- [ ] Access control via Voter, not raw role checks in controller
- [ ] CSRF protection on state-mutating forms
- [ ] DTOs validated with Symfony Validator
- [ ] No entity exposure in responses

## Reject if

- Business logic in a Controller or Twig template
- Missing tests for critical domain logic
- Doctrine entity returned directly from API endpoint
- Hard-coded credentials or secrets in code
- `dd()`, `dump()`, `var_dump()` left in code

## Always suggest

- Replace inline logic with a Value Object or Domain Service
- Symfony-native solution over custom implementation
- Simpler query (JOIN FETCH vs lazy loading)
- Descriptive method name over a comment
