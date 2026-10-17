<?php

namespace App\Enums;

enum RecurringKind: string
{
    case Bill = 'bill';
    case Subscription = 'subscription';
    case Installment = 'installment';
    case Debt = 'debt';
}
