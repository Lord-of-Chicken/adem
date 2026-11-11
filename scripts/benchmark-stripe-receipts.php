<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
$reference = getenv('RUELLE_PERF_REFERENCE_DIR');
if (is_string($reference) && $reference !== '') {
    require $reference.'/StripePaymentService.php';
    require $reference.'/StripeReceiptProvider.php';
}
final class FakeReceiptHttp implements \Stripe\HttpClient\ClientInterface {
    public int $calls = 0;
    public array $urls = [];
    public function __construct(private int $pages) {}
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null) {
        ++$this->calls; $this->urls[] = $absUrl; usleep(5000);
        if ($method !== 'get') { throw new \LogicException('Unexpected fake HTTP method'); }
        if (str_ends_with($absUrl, '/checkout/sessions/cs_benchmark')) {
            $data = ['object'=>'checkout.session','id'=>'cs_benchmark','payment_intent'=>'pi_benchmark','payment_status'=>'paid','amount_total'=>2000,'currency'=>'eur','livemode'=>false];
        } elseif (str_ends_with($absUrl, '/checkout/sessions/cs_benchmark/line_items')) {
            if (($params['expand'] ?? null) !== ['data.price.product']) { throw new \LogicException('Missing product expansion'); }
            $last = $this->pages === 1 || isset($params['starting_after']);
            $items = $this->pages === 1 ? [1,2] : ($last ? [2] : [1]);
            $data=['object'=>'list','url'=>'/v1/checkout/sessions/cs_benchmark/line_items','has_more'=>!$last,'data'=>array_map(static fn(int $id): array => ['object'=>'item','id'=>'li_'.$id,'quantity'=>1,'amount_total'=>1000,'currency'=>'eur','price'=>['object'=>'price','unit_amount'=>1000,'product'=>['object'=>'product','name'=>'Fixture']]],$items)];
        } elseif (str_ends_with($absUrl, '/payment_intents/pi_benchmark')) {
            $charge=['object'=>'charge','id'=>'ch_benchmark','payment_intent'=>'pi_benchmark','paid'=>true,'status'=>'succeeded','captured'=>true,'amount'=>2000,'amount_captured'=>2000,'currency'=>'eur','livemode'=>false,'receipt_url'=>'https://pay.stripe.com/receipts/payment/fixture'];
            $data=['object'=>'payment_intent','id'=>'pi_benchmark','status'=>'succeeded','amount'=>2000,'amount_received'=>2000,'currency'=>'eur','livemode'=>false,'latest_charge'=>($params['expand']??[])===['latest_charge']?$charge:'ch_benchmark'];
        } else { throw new \LogicException('Unexpected fake HTTP URL'); }
        return [json_encode($data, JSON_THROW_ON_ERROR),200,[]];
    }
}
$results=['simulation'=>'SDK fake HTTP, 5ms per request; no real Stripe or SMTP','repetitions'=>15,'scenarios'=>[]];
foreach ([1,2] as $pages) {
    $times=[]; $calls=[];
    for($i=0;$i<15;++$i) {
        $http=new FakeReceiptHttp($pages); \Stripe\ApiRequestor::setHttpClient($http);
        $provider=new \App\Service\StripeReceiptProvider(new \App\Service\StripePaymentService('sk_test_fixture',new \Psr\Log\NullLogger()));
        $order=(new \App\Entity\Order())->setStripeCheckoutSessionId('cs_benchmark')->setStripePaymentIntentId('pi_benchmark')->setTotalCents(2000)->setStatus(\App\Enum\OrderStatus::Paid);
        $start=hrtime(true); $details=$provider->fetch($order); $times[]=(hrtime(true)-$start)/1e6; $calls[]=$http->calls;
        if(count($details['lines'])!==2 || $details['amount_cents']!==2000) { throw new \LogicException('Changed receipt result'); }
    }
    sort($times); $results['scenarios'][]=['pages'=>$pages,'requests_each'=>array_values(array_unique($calls)),'median_ms'=>round($times[7],3),'p95_ms'=>round($times[14],3),'result'=>'same two verified lines, 2000 EUR cents'];
}
$json=json_encode($results,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
if(isset($argv[1])) { file_put_contents($argv[1],$json); }
echo $json;
