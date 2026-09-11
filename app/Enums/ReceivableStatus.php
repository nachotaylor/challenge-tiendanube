<?php

namespace App\Enums;

enum ReceivableStatus: string
{
    case Paid = 'paid';
    case WaitingFunds = 'waiting_funds';
}
