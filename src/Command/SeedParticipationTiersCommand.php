<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ParticipationTier;
use App\Repository\ParticipationTierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 * Seeds the participation_tier table from config/tiers.yaml.
 *
 * The table is the runtime source of truth for the catalog; this command makes
 * the historical YAML values available in the DB so the site renders identically.
 * It is idempotent (upsert by id), so it can be re-run safely. Translation KEYS
 * are stored in the title/detail/price/price_suffix columns — texts stay
 * translatable through the existing translation files / admin editor.
 */
#[AsCommand(
    name: 'app:seed-participation-tiers',
    description: 'Seeds the participation_tier table from config/tiers.yaml (idempotent upsert)',
)]
final class SeedParticipationTiersCommand extends Command
{
    private const TIERS_FILE = '/config/tiers.yaml';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ParticipationTierRepository $repository,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            null,
            InputOption::VALUE_NONE,
            'Update existing tiers with the YAML values (otherwise existing tiers are left untouched)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        $file = $this->projectDir . self::TIERS_FILE;
        if (!is_file($file)) {
            $io->error(sprintf('Seed source not found: %s', $file));

            return Command::FAILURE;
        }

        /** @var array{tiers: array<int, array<string, mixed>>} $data */
        $data = Yaml::parseFile($file);
        $tiers = $data['tiers'] ?? [];

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach (array_values($tiers) as $sortOrder => $def) {
            $id = $this->str($def, 'id');
            if ($id === '') {
                continue;
            }
            $existing = $this->repository->find($id);

            if ($existing !== null && !$force) {
                ++$skipped;
                continue;
            }

            $entity = $existing ?? (new ParticipationTier())->setId($id);
            $this->hydrate($entity, $def, $sortOrder);
            $this->entityManager->persist($entity);

            if ($existing !== null) {
                ++$updated;
            } else {
                ++$created;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            '%d tier(s) created, %d updated, %d skipped (%d total in YAML).',
            $created,
            $updated,
            $skipped,
            count($tiers),
        ));

        return Command::SUCCESS;
    }

    /**
     * Maps a YAML tier definition onto a ParticipationTier entity.
     *
     * Translatable fields use the *_key entry when present (begonia title/detail,
     * free_donation price, begonia price suffix), falling back to the literal value.
     *
     * @param array<string, mixed> $def The YAML tier definition
     */
    private function hydrate(ParticipationTier $entity, array $def, int $sortOrder): void
    {
        $priceUnit = $this->str($def, 'price_unit');

        $entity
            ->setTitle($this->keyOrValue($def, 'title_key', 'title'))
            ->setDetail($this->keyOrValue($def, 'detail_key', 'detail'))
            ->setPriceLabel($this->keyOrValue($def, 'price_key', 'price'))
            ->setPriceUnit($priceUnit !== '' ? $priceUnit : '€')
            ->setPriceSuffix($this->nullableKeyOrValue($def, 'price_suffix_key', 'price_suffix'))
            ->setUnitPriceEur(number_format($this->float($def, 'unit_price_eur'), 2, '.', ''))
            ->setPricedPerUnit($this->bool($def, 'priced_per_unit'))
            ->setMinQty($this->int($def, 'min_qty', 1))
            ->setMaxQty($this->int($def, 'max_qty', 1))
            ->setTierGroup($this->str($def, 'group', 'standard'))
            ->setDonorField($this->bool($def, 'donor_field'))
            ->setSortOrder($sortOrder)
            ->setActive(true);
    }

    /**
     * Returns the translation key if present, otherwise the literal value, as a string.
     *
     * @param array<string, mixed> $def
     */
    private function keyOrValue(array $def, string $keyName, string $valueName): string
    {
        $key = $this->str($def, $keyName);

        return $key !== '' ? $key : $this->str($def, $valueName);
    }

    /**
     * Same as keyOrValue but returns null when neither the key nor the value is a string.
     *
     * @param array<string, mixed> $def
     */
    private function nullableKeyOrValue(array $def, string $keyName, string $valueName): ?string
    {
        if (isset($def[$keyName]) && is_string($def[$keyName])) {
            return $def[$keyName];
        }
        if (isset($def[$valueName]) && is_string($def[$valueName])) {
            return $def[$valueName];
        }

        return null;
    }

    /**
     * Reads a string value from the YAML definition, with a default fallback.
     *
     * @param array<string, mixed> $def
     */
    private function str(array $def, string $name, string $default = ''): string
    {
        $value = $def[$name] ?? null;

        return is_string($value) ? $value : $default;
    }

    /**
     * Reads an integer value from the YAML definition, with a default fallback.
     *
     * @param array<string, mixed> $def
     */
    private function int(array $def, string $name, int $default = 0): int
    {
        $value = $def[$name] ?? null;

        return is_int($value) ? $value : $default;
    }

    /**
     * Reads a float value from the YAML definition (accepts int/float/numeric string).
     *
     * @param array<string, mixed> $def
     */
    private function float(array $def, string $name): float
    {
        $value = $def[$name] ?? null;

        return is_int($value) || is_float($value) || is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * Reads a boolean value from the YAML definition.
     *
     * @param array<string, mixed> $def
     */
    private function bool(array $def, string $name): bool
    {
        return ($def[$name] ?? false) === true;
    }
}
