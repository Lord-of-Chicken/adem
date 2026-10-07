<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\StripePaymentService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;

final class StripeReceiptApiTest extends TestCase
{
    protected function tearDown(): void
    {
        $http = CurlClient::instance();
        self::assertInstanceOf(ClientInterface::class, $http);
        ApiRequestor::setHttpClient($http);
    }

    public function testReceiptLinesFetchEveryPageWithExpandedProducts(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $call = 0;
        $http->expects(self::exactly(3))->method('request')->willReturnCallback(
            /** @param list<string> $headers @param array<string, mixed> $params @return array{string, int, array<string, string>} */
            static function (string $method, string $url, array $headers, array $params) use (&$call): array {
                self::assertSame('get', $method);
                ++$call;
                if ($call === 1) {
                    self::assertStringEndsWith('/v1/checkout/sessions/cs_receipt_api', $url);
                    $data = ['object' => 'checkout.session', 'id' => 'cs_receipt_api'];
                } else {
                    self::assertStringEndsWith('/v1/checkout/sessions/cs_receipt_api/line_items', $url);
                    self::assertSame(['data.price.product'], $params['expand']);
                    self::assertSame(100, $params['limit']);
                    if ($call === 3) { self::assertSame('li_first', $params['starting_after']); }
                    $data = ['object' => 'list', 'url' => '/v1/checkout/sessions/cs_receipt_api/line_items',
                        'has_more' => $call === 2, 'data' => [['object' => 'item', 'id' => $call === 2 ? 'li_first' : 'li_second']]];
                }
                return [json_encode($data, JSON_THROW_ON_ERROR), 200, []];
            }
        );
        ApiRequestor::setHttpClient($http);
        $session = (new StripePaymentService('sk_test_fixture', new NullLogger()))->getPaymentReceiptSession('cs_receipt_api');
        self::assertNotNull($session->line_items);
        self::assertFalse($session->line_items->has_more);
        self::assertSame(['li_first', 'li_second'], array_map(static fn ($line) => $line->id, $session->line_items->data));
    }

    public function testChargeFetchRequestsExpansionAndRejectsUnavailableReceipt(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $call = 0;
        $http->expects(self::exactly(2))->method('request')->willReturnCallback(
            /** @param list<string> $headers @param array<string, mixed> $params @return array{string, int, array<string, string>} */
            static function (string $method, string $url, array $headers, array $params) use (&$call): array {
                self::assertSame('get', $method);
                self::assertStringEndsWith('/v1/payment_intents/pi_receipt_api', $url);
                self::assertSame(['latest_charge'], $params['expand']);
                ++$call;
                return [json_encode(['object' => 'payment_intent', 'id' => 'pi_receipt_api', 'latest_charge' =>
                    $call === 1 ? ['object' => 'charge', 'id' => 'ch_receipt_api'] : null], JSON_THROW_ON_ERROR), 200, []];
            }
        );
        ApiRequestor::setHttpClient($http);
        $service = new StripePaymentService('sk_test_fixture', new NullLogger());
        self::assertSame('ch_receipt_api', $service->getPaymentReceiptCharge('pi_receipt_api')->id);
        $this->expectException(\UnexpectedValueException::class);
        $service->getPaymentReceiptCharge('pi_receipt_api');
    }
}
