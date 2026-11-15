<?php

namespace App\Service\Integration\OpenLibrary;

/**
 * Résultat d'une tentative d'import pour un ISBN donné.
 */
final class ImportResult
{
    public const STATUS_CREATED = 'created';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED  = 'failed';

    private function __construct(
        public readonly string  $isbn,
        public readonly string  $status,     // 'created' | 'skipped' | 'failed'
        public readonly ?string $title,
        public readonly ?string $message,
    ) {}

    public static function created(string $isbn, string $title): self
    {
        return new self($isbn, self::STATUS_CREATED, $title, null);
    }

    public static function skipped(string $isbn, string $title): self
    {
        return new self($isbn, self::STATUS_SKIPPED, $title, 'ISBN déjà présent dans le catalogue.');
    }

    public static function failed(string $isbn, string $reason): self
    {
        return new self($isbn, self::STATUS_FAILED, null, $reason);
    }

    public function isCreated(): bool  { return $this->status === self::STATUS_CREATED; }
    public function isSkipped(): bool  { return $this->status === self::STATUS_SKIPPED; }
    public function isFailed(): bool   { return $this->status === self::STATUS_FAILED; }

    /** Badge Bootstrap CSS à afficher dans le template. */
    public function badgeClass(): string
    {
        return match ($this->status) {
            'created' => 'success',
            'skipped' => 'warning',
            default   => 'danger',
        };
    }

    /** Icône Bootstrap à afficher dans le template. */
    public function icon(): string
    {
        return match ($this->status) {
            'created' => 'bi-check-circle-fill',
            'skipped' => 'bi-skip-forward-fill',
            default   => 'bi-x-circle-fill',
        };
    }

    /** Libellé lisible du statut. */
    public function label(): string
    {
        return match ($this->status) {
            'created' => 'Créé',
            'skipped' => 'Ignoré (doublon)',
            default   => 'Échec',
        };
    }
}
