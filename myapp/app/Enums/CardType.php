<?php

namespace App\Enums;

enum CardType: string
{
    case Ewallet = 'ewallet';
    case Bank = 'bank';
    case Cash = 'cash';
    case Credit = 'credit';
}
