# Testing Skill

## Stack

- **PHPUnit 13.1** (`phpunit/phpunit: ^13.1`)
- **Symfony BrowserKit** + **CssSelector** (functional tests)
- **Symfony Maker** (scaffolding)
- No Pest, no Panther by default — add only if justified

## Test types and when to use them

```
Unit tests        → Pure business logic (entities, enums, Services with no I/O)
                    e.g. CartService totals, ParticipationCatalog, OrderStatus
                    No Doctrine, no HTTP — fast, pure PHP

Integration tests → Services that touch Doctrine / external boundaries
                    Uses KernelTestCase, real Doctrine with a test DB
                    e.g. StripeWebhook idempotency, repositories

Functional tests  → HTTP controllers
                    Uses WebTestCase + BrowserKit
                    Full stack, real DB, real responses
```

## Unit test example (Service / domain logic)

```php
// tests/Cart/CartServiceTest.php
final class CartServiceTest extends TestCase {
    public function testItComputesTotalInCents(): void {
        $cart = new CartService(/* in-memory session stub */);
        $cart->add(tierId: 'vip-begonia', quantity: 2);   // 2 × 1500c

        self::assertSame(3000, $cart->totalCents());
    }
}
```

## Integration test example (webhook idempotency)

```php
// tests/Service/StripeWebhookIdempotencyTest.php
final class StripeWebhookIdempotencyTest extends KernelTestCase {
    public function testItProcessesEachStripeEventOnce(): void {
        // First processing marks the order paid and records the event
        // Replaying the same event id must be a no-op (StripeProcessedEvent unique)
        self::assertTrue($this->handler->isAlreadyProcessed('evt_123'));
    }
}
```

## Functional test example (Controller)

```php
// tests/Controller/CheckoutControllerTest.php
final class CheckoutControllerTest extends WebTestCase {
    public function testCheckoutRedirectsToStripe(): void {
        $client = static::createClient();
        $client->loginUser($this->createUser());

        $client->request('GET', '/fr/panier');
        $client->submitForm('Payer', [
            'cart[tier]' => 'standard-jardiniere',
            'cart[quantity]' => '1',
        ]);

        self::assertResponseRedirects();
    }
}
```

## Test DB setup

```bash
# .env.test
DATABASE_URL="mysql://app:!ChangeMe!@127.0.0.1:3306/app_test?serverVersion=8.4"
```

```php
// tests/bootstrap.php — boot env before suite
use Symfony\Component\Dotenv\Dotenv;
(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
// Use doctrine:database:create + migrations:migrate in CI
```

## Rules

- Cover business logic first (CartService, ParticipationCatalog, Stripe amount/idempotency checks), infrastructure second
- Every controller (happy path + main error cases) must have functional tests
- No merge without tests for new business logic
- Use data providers for multiple input scenarios
- Fixtures via `DoctrineFixturesBundle` or factory objects — never raw SQL in tests
- Test names describe behavior: `testItRefusesOrderWithEmptyCart()` not `testCreate()`

## Optional additions (install when needed)

```bash
# Browser-based E2E tests
composer require --dev symfony/panther

# Rich fixture factories
composer require --dev zenstruck/foundry
```
