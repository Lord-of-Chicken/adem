<?php
declare(strict_types=1);

use App\Cart\CartService;
use App\Entity\ParticipationTier;
use App\Kernel;
use App\Participation\ParticipationCatalog;
use App\Repository\MediaItemRepository;
use App\Repository\ParticipationTierRepository;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\AbstractManagerRegistry;
use Psr\Log\AbstractLogger;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

$project = dirname(__DIR__);
require $project.'/vendor/autoload.php';
// Optional immutable reference exported from Git; the working tree is never switched.
$reference = getenv('RUELLE_PERF_REFERENCE_DIR');
if (is_string($reference) && $reference !== '') {
    require $reference.'/CartService.php';
    require $reference.'/MediaItemRepository.php';
}

if (getenv('APP_ENV') !== 'test' || getenv('TEST_TOKEN') !== '_audit_20261006') {
    throw new LogicException('Only the dedicated local audit test environment is permitted.');
}
(new Dotenv())->bootEnv($project.'/.env');

function measure(callable $operation, int $iterations = 1): array {
    for ($i=0; $i<3; ++$i) { $operation(); }
    $samples = [];
    for ($i=0; $i<21; ++$i) {
        $start = hrtime(true);
        for ($j=0; $j<$iterations; ++$j) { $operation(); }
        $samples[] = (hrtime(true)-$start)/1e6/$iterations;
    }
    sort($samples);
    memory_reset_peak_usage();
    $memoryBefore = memory_get_usage();
    $operation();
    $peakExtra = memory_get_peak_usage() - $memoryBefore;
    return ['samples'=>21, 'median_ms'=>$samples[10], 'p95_ms'=>$samples[19], 'peak_extra_bytes'=>$peakExtra];
}
$repository = new class extends ParticipationTierRepository {
    public function __construct() {}
    public function findAllActiveOrdered(): array {
        return [
            (new ParticipationTier())->setTitle('Fixture')->setDetail('Fixture')->setPriceLabel('Fixture')->setPriceUnit('€')->setId('unit')->setUnitPriceEur('1.23')->setPricedPerUnit(true),
            (new ParticipationTier())->setTitle('Fixture')->setDetail('Fixture')->setPriceLabel('Fixture')->setPriceUnit('€')->setId('flat')->setUnitPriceEur('20.00')->setPricedPerUnit(false),
            (new ParticipationTier())->setTitle('Fixture')->setDetail('Fixture')->setPriceLabel('Fixture')->setPriceUnit('€')->setId('free_donation')->setUnitPriceEur('0.00')->setPricedPerUnit(false),
        ];
    }
};
$catalog = new ParticipationCatalog($repository);
$results = ['environment'=>['php'=>PHP_VERSION, 'xdebug_mode'=>getenv('XDEBUG_MODE'), 'opcache_cli'=>ini_get('opcache.enable_cli')], 'cart'=>[], 'media'=>[]];
foreach ([10,100,1000] as $size) {
    $session = new class(new MockArraySessionStorage()) extends Session {
        public int $reads=0;
        public function get(string $name, mixed $default = null): mixed { ++$this->reads; return parent::get($name, $default); }
    };
    $lines=[]; $expected=0;
    for ($i=0; $i<$size; ++$i) {
        $type=$i%3;
        $lines[]=['line_id'=>'fixture_'.$i, 'tier_id'=>['unit','flat','free_donation'][$type], 'quantity'=>3, 'donor_name'=>null, 'custom_price_cents'=>$type===2 ? 777 : null];
        $expected += [369,2000,2331][$type];
    }
    $session->set('participation_cart_v1',$lines);
    $request=new Request(); $request->setSession($session); $stack=new RequestStack(); $stack->push($request);
    $cart=new CartService($stack);
    $session->reads=0;
    if ($cart->totalCents($catalog)!==$expected) { throw new LogicException('Incorrect cart total.'); }
    $reads=$session->reads;
    $results['cart'][$size]=measure(fn()=>$cart->totalCents($catalog), $size===1000 ? 1 : 10)+['session_reads'=>$reads,'total_cents'=>$expected];
}
$kernel=new Kernel('test',false); $kernel->boot();
$original=$kernel->getContainer()->get('doctrine')->getManager();
$params=$original->getConnection()->getParams();
if (($params['dbname']??null)!=='ruellenadem_test_audit_20261006' || ($params['host']??null)!=='127.0.0.1') { throw new LogicException('Unsafe database target.'); }
$counter=new class extends AbstractLogger {
    public int $selects=0;
    public function log($level, string|Stringable $message, array $context=[]): void {
        if (isset($context['sql']) && str_starts_with(strtoupper($context['sql']), 'SELECT')) { ++$this->selects; }
    }
};
$configuration=new Configuration(); $configuration->setMiddlewares([new Middleware($counter)]);
$db=DriverManager::getConnection($params,$configuration);
if ($db->fetchOne('SELECT DATABASE()')!=='ruellenadem_test_audit_20261006') { throw new LogicException('Unexpected actual database.'); }
$em=new EntityManager($db,$original->getConfiguration());
$registry=new class($em) extends AbstractManagerRegistry {
    public function __construct(private EntityManager $em) { parent::__construct('performance', ['default'=>'db'], ['default'=>'em'], 'default', 'default'); }
    protected function getService(string $name): object { return $name==='db' ? $this->em->getConnection() : $this->em; }
    protected function resetService(string $name): void { throw new LogicException('Reset not supported.'); }
};
$media=new MediaItemRepository($registry);
$beforeCount=$db->fetchOne('SELECT COUNT(*) FROM media_item');
$db->beginTransaction();
try {
    foreach ([4,50,200] as $size) {
        $positions=[];
        for ($i=0; $i<$size; ++$i) {
            $db->insert('media_item',['asset_path'=>'img/performance-fixture.jpg','title'=>'Performance fixture','alt'=>null,'sort_order'=>$i,'published'=>1]);
            $positions[(int)$db->lastInsertId()]=$i;
        }
        $run=function() use($em,$counter,$media,$positions,$size): void {
            $em->clear(); $counter->selects=0;
            $media->updateSortOrders($positions);
            if ($em->getUnitOfWork()->size()!==$size) { throw new LogicException('Unexpected hydrated media count.'); }
        };
        $stats=measure($run);
        $stats['selects']=$counter->selects;
        foreach ($positions as $id=>$position) {
            if ((int)$db->fetchOne('SELECT sort_order FROM media_item WHERE id = ?',[$id])!==$position) { throw new LogicException('Incorrect persisted media order.'); }
        }
        $results['media'][$size]=$stats;
    }
} finally {
    $db->rollBack(); $em->clear();
}
if ($db->fetchOne('SELECT COUNT(*) FROM media_item')!==$beforeCount) { throw new LogicException('Fixture rollback failed.'); }
$results['database_fixture_rollback_verified']=true;
$kernel->shutdown();
echo json_encode($results,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
