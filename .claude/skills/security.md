# Security Skill

## Symfony Security layer

```php
// Voters for access control — never hard-code role checks in controllers
#[IsGranted('VIEW', subject: 'order')]
public function show(Order $order): Response {}

// Voter implementation
final class OrderVoter extends Voter {
    protected function supports(string $attribute, mixed $subject): bool {
        return in_array($attribute, ['VIEW', 'CANCEL'])
            && $subject instanceof Order;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool {
        $user = $token->getUser();
        return match($attribute) {
            'VIEW', 'CANCEL' => $subject->getUser() === $user, // only the buyer
            default          => false,
        };
    }
}
```

## CSRF protection

```php
// Forms: automatic via Symfony Form component (already configured via csrf.yaml)
// API endpoints that mutate state: use #[IsCsrfTokenValid]
#[IsCsrfTokenValid('cancel-order')]
public function cancel(Order $order): Response {}

// Twig: always include CSRF token in custom forms
{{ csrf_token('cancel-order') }}
```

## Input validation — on the DTO / form-backed object, never trust raw input

```php
final class ContactInput {
    #[NotBlank]
    #[Length(min: 2, max: 100)]
    public string $name = '';

    #[NotBlank]
    #[Email]
    public string $email = '';

    #[NotBlank]
    #[Length(min: 10, max: 2000)]
    public string $message = '';
}
```

## SQL injection

```php
// Doctrine QueryBuilder is safe by default when using parameters
->where('o.status = :status')
->setParameter('status', $status); // SAFE

// Raw SQL only when necessary — always use prepared statements
$conn->executeQuery('SELECT * FROM `order` WHERE status = ?', [$status]); // SAFE
$conn->executeQuery("SELECT * FROM `order` WHERE status = '$status'"); // NEVER
```

## XSS prevention

```twig
{# Twig auto-escapes by default — never disable without reason #}
{{ mediaItem.caption }}           {# safe #}
{{ mediaItem.caption|raw }}       {# DANGEROUS — only for trusted HTML #}
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
#[RateLimiter('contact_submission')]
public function contact(Request $request): Response {}

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
