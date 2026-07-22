<?php

namespace App\Enums;

/**
 * The five MVP push mutation types (Design 02 §5.3). Server-side mirror of the
 * `namain/sync-protocol` `MutationType` enum; the wire values are the contract
 * and change only with a protocol version bump.
 */
enum MutationType: string
{
    case PosSessionOpen = 'pos_session.open';
    case PosSessionClose = 'pos_session.close';
    case SaleCreate = 'sale.create';
    case CustomerCreate = 'customer.create';
    case ExpenseCreate = 'expense.create';
    case FavoriteSet = 'favorite.set';
    case ProductCreate = 'product.create';
}
