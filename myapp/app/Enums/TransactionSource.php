<?php

namespace App\Enums;

enum TransactionSource: string
{
    case Manual = 'manual';
    case Quick = 'quick';
    case Receipt = 'receipt';
    case Chat = 'chat';
}
