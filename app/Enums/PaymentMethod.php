<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case DebitCard = 'debit_card';
    case CreditCard = 'credit_card';
}
