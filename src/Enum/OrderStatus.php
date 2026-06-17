<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Lifecycle status of an {@see \App\Entity\Order}.
 *
 * Backed string values are stored as-is in the database and must remain stable
 * ('pending'/'paid'/'failed') to preserve compatibility with existing rows.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
}
