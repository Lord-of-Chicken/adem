# Testing Skill

## Stack

- **PHPUnit 13.1** (`phpunit/phpunit: ^13.1`)
- **Symfony BrowserKit** + **CssSelector** (functional tests)
- **Symfony Maker** (scaffolding)
- No Pest, no Panther by default — add only if justified

## Test types and when to use them

```
Unit tests       → Domain layer (entities, value objects, domain services)
                   No Symfony, no Doctrine, no I/O
                   Fast, pure PHP

Integration tests → Application layer (command/query handlers)
                   Uses KernelTestCase, real Doctrine with test DB
                   Real Messenger handlers

Functional tests  → UI layer (HTTP controllers)
                   Uses WebTestCase + BrowserKit
                   Full stack, real DB, real responses
```

## Unit test example (Domain)

```php
// tests/AnimalReport/Domain/AnimalReportTest.php
final class AnimalReportTest extends TestCase {
    public function testItCanBeCreated(): void {
        $report = AnimalReport::create(
            name: 'Milo',
            species: Species::CAT,
            location: new Location(48.8566, 2.3522, 'Paris'),
        );

        self::assertSame('Milo', $report->name);
        self::assertTrue($report->status->isLost());
    }
}
```

## Functional test example (Controller)

```php
// tests/AnimalReport/UI/ReportLostAnimalControllerTest.php
final class ReportLostAnimalControllerTest extends WebTestCase {
    public function testFormSubmitCreatesReport(): void {
        $client = static::createClient();
        $client->loginUser($this->createUser());

        $client->request('GET', '/reports/new');
        $client->submitForm('Signaler', [
            'report[name]' => 'Milo',
            'report[species]' => 'cat',
        ]);

        self::assertResponseRedirects('/reports');
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Milo');
    }
}
```

## Test DB setup

```yaml
# .env.test
DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app_test"
```

```php
// tests/bootstrap.php — reset DB before suite
use Symfony\Component\Dotenv\Dotenv;
(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
// Use doctrine:database:create + migrations:migrate in CI
```

## Rules

- Every domain aggregate and value object must have unit tests
- Every controller (happy path + main error cases) must have functional tests
- No merge without tests for new business logic
- Cover business logic first, infrastructure second
- Use data providers for multiple input scenarios
- Fixtures via `DoctrineFixturesBundle` or factory objects — never raw SQL in tests
- Test names describe behavior: `testItRefusesReportWithoutLocation()` not `testCreate()`

## Optional additions (install when needed)

```bash
# Browser-based E2E tests
composer require --dev symfony/panther

# Rich fixture factories
composer require --dev zenstruck/foundry

# Better test assertions
composer require --dev phpunit/phpunit  # already installed
```
