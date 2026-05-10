# Security Skill

## Symfony Security layer

```php
// Voters for access control — never hard-code role checks in controllers
#[IsGranted('VIEW', subject: 'report')]
public function show(AnimalReport $report): Response {}

// Voter implementation
final class AnimalReportVoter extends Voter {
    protected function supports(string $attribute, mixed $subject): bool {
        return in_array($attribute, ['VIEW', 'EDIT', 'DELETE'])
            && $subject instanceof AnimalReport;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool {
        $user = $token->getUser();
        return match($attribute) {
            'VIEW'   => true, // public reports are viewable
            'EDIT', 'DELETE' => $subject->isOwnedBy($user),
            default  => false,
        };
    }
}
```

## CSRF protection

```php
// Forms: automatic via Symfony Form component (already configured via csrf.yaml)
// API endpoints that mutate state: use #[IsCsrfTokenValid]
#[IsCsrfTokenValid('delete-report')]
public function delete(AnimalReport $report): Response {}

// Twig: always include CSRF token in custom forms
{{ csrf_token('delete-report') }}
```

## Input validation — on DTOs, never on entities

```php
final class ReportLostAnimalInput {
    #[NotBlank]
    #[Length(min: 2, max: 100)]
    public string $animalName = '';

    #[NotBlank]
    #[Valid]
    public LocationInput $location;

    #[NotBlank]
    #[Email]
    public string $contactEmail = '';
}
```

## SQL injection

```php
// Doctrine QueryBuilder is safe by default when using parameters
->where('r.city = :city')
->setParameter('city', $city); // SAFE

// Raw SQL only when necessary — always use prepared statements
$conn->executeQuery('SELECT * FROM report WHERE city = ?', [$city]); // SAFE
$conn->executeQuery("SELECT * FROM report WHERE city = '$city'"); // NEVER
```

## XSS prevention

```twig
{# Twig auto-escapes by default — never disable without reason #}
{{ report.description }}           {# safe #}
{{ report.description|raw }}       {# DANGEROUS — only for trusted HTML #}
```

## Environment & secrets

```bash
# Secrets in .env only — never hard-coded
DATABASE_URL="..."
APP_SECRET="..."

# Use Symfony Secrets vault for production
symfony console secrets:set DATABASE_URL
```

## Rate limiting

```php
// Use Symfony RateLimiter on sensitive endpoints
#[RateLimiter('report_submission')]
public function create(Request $request): Response {}

// Configure in config/packages/rate_limiter.yaml
```

## Security checklist per feature

- [ ] Access control via Voter (not role-only)
- [ ] CSRF token on all state-mutating forms
- [ ] Input validated via DTO + Symfony Validator
- [ ] No entity exposure in API responses
- [ ] Secrets in env vars, not in code
- [ ] Rate limiting on public-facing forms
- [ ] File uploads: validate MIME type server-side, store outside webroot
- [ ] `composer audit` passes (run in CI)

## Rules

- Never trust user input — validate at system boundary (controller/form)
- Secrets in env vars only
- Least privilege: Voters grant only what is explicitly needed
- Passwords: always via `PasswordHasherInterface`, never plain text
- No `security.access_control` bypass in controllers
