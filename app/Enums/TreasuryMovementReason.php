<?php

namespace App\Enums;

enum TreasuryMovementReason: string
{
    case PaymentReceived = 'payment_received';
    case PaymentRefunded = 'payment_refunded';
    case ExpensePaid = 'expense_paid';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case PosOpeningFloat = 'pos_opening_float';
    case PosClosingFloat = 'pos_closing_float';
    case ChequeDeposited = 'cheque_deposited';
    case ChequeBounced = 'cheque_bounced';
    case ManualAdjustment = 'manual_adjustment';
    case CustomerAdvanceGiven = 'customer_advance_given';
    case CustomerAdvanceRepaid = 'customer_advance_repaid';
    case ChequeReceived = 'cheque_received';
    case ChequeCleared = 'cheque_cleared';

    public function label(): string
    {
        return match ($this) {
            self::PaymentReceived => 'Payment Received',
            self::PaymentRefunded => 'Payment Refunded',
            self::ExpensePaid => 'Expense Paid',
            self::TransferIn => 'Transfer In',
            self::TransferOut => 'Transfer Out',
            self::PosOpeningFloat => 'POS Opening Float',
            self::PosClosingFloat => 'POS Closing Float',
            self::ChequeDeposited => 'Cheque Deposited',
            self::ChequeBounced => 'Cheque Bounced',
            self::ManualAdjustment => 'Manual Adjustment',
            self::CustomerAdvanceGiven => 'Customer Advance Given',
            self::CustomerAdvanceRepaid => 'Customer Advance Repaid',
            self::ChequeReceived => 'Cheque Received',
            self::ChequeCleared => 'Cheque Cleared',
        };
    }

    public function isCredit(): bool
    {
        return in_array($this, [
            self::PaymentReceived,
            self::TransferIn,
            self::PosOpeningFloat,
            self::ChequeDeposited,
            self::ManualAdjustment,
            self::CustomerAdvanceRepaid,
            self::ChequeReceived,
        ]);
    }
}
