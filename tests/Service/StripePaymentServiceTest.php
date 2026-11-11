<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\StripePaymentService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Stripe\ApiRequestor;
use Stripe\Exception\ApiConnectionException;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;

final class StripePaymentServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(new CurlClient());
    }

    public function testCheckoutUsesCardEurItemsMetadataAndReturnUrls(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects(self::once())->method('request')->willReturnCallback(
            /**
             * @param array<string> $headers
             * @param array<string, mixed> $params
             * @return array{string, int, array<string, string>}
             */
            static function (string $method, string $url, array $headers, array $params): array {
                self::assertSame('post', $method);
                self::assertStringEndsWith('/v1/checkout/sessions', $url);
                self::assertSame('payment', $params['mode']);
                self::assertSame(['card'], $params['payment_method_types']);
                self::assertSame('person@example.test', $params['customer_email']);
                self::assertSame('https://example.test/success', $params['success_url']);
                self::assertSame('https://example.test/cancel', $params['cancel_url']);
                self::assertSame(['order_ref' => 'fixture', 'order_id' => '42'], $params['metadata']);
                self::assertSame(['metadata' => ['order_id' => '42']], $params['payment_intent_data']);
                self::assertContains('Idempotency-Key: adem-order-42', $headers);
                return [json_encode(['object' => 'checkout.session', 'id' => 'cs_fixture', 'url' => 'https://checkout.stripe.com/fixture'], JSON_THROW_ON_ERROR), 200, []];
            }
        );
        ApiRequestor::setHttpClient($http);
        $session = (new StripePaymentService('sk_test_fixture', $this->createStub(LoggerInterface::class)))->createCheckoutSession(
            [['price_data' => ['currency' => 'eur', 'unit_amount' => 100, 'product_data' => ['name' => 'Fixture']], 'quantity' => 1]],
            'person@example.test', ['order_ref' => 'fixture', 'order_id' => '42'], 'https://example.test/success', 'https://example.test/cancel'
        );
        self::assertSame('cs_fixture', $session->id);
    }

    public function testPaymentStatusAndTransportFailureAreHandled(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects(self::exactly(3))->method('request')->willReturnOnConsecutiveCalls(
            ['{"object":"checkout.session","id":"cs_fixture","payment_status":"paid"}', 200, []],
            ['{"object":"checkout.session","id":"cs_fixture","payment_status":"unpaid"}', 200, []],
            self::throwException(new ApiConnectionException('fixture timeout'))
        );
        ApiRequestor::setHttpClient($http);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $service = new StripePaymentService('sk_test_fixture', $logger);
        self::assertTrue($service->isSessionPaid('cs_fixture'));
        self::assertFalse($service->isSessionPaid('cs_fixture'));
        self::assertFalse($service->isSessionPaid('cs_fixture'));
    }

    public function testMetadataFilteringAndCustomerScope(): void
    {
        $http = $this->createStub(ClientInterface::class);
        $http->method('request')->willReturnCallback(
            /** @return array{string, int, array<string, string>} */
            static function (string $method, string $url): array {
                if (str_ends_with($url, '/payment_intents/pi_1')) {
                    return ['{"object":"payment_intent","id":"pi_1"}', 200, []];
                }
                return ['{"object":"list","data":[{"object":"payment_intent","id":"pi_1","metadata":{"user_id":"one"}},{"object":"payment_intent","id":"pi_2","metadata":{"user_id":"two"}}]}', 200, []];
            }
        );
        ApiRequestor::setHttpClient($http);
        $service = new StripePaymentService('sk_test_fixture', $this->createStub(LoggerInterface::class));
        self::assertCount(2, $service->listAllPaymentIntents());
        self::assertCount(2, $service->listPaymentIntentsByMetadata([]));
        self::assertSame('pi_1', $service->listPaymentIntentsByMetadata(['user_id' => 'one'])[0]->id);
        self::assertSame([], $service->listPaymentIntentsByMetadata(['user_id' => 'absent']));
        self::assertCount(2, $service->listPaymentIntentsByCustomer('cus_fixture'));
        self::assertSame('pi_1', $service->getPaymentIntent('pi_1')->id);
    }
}
