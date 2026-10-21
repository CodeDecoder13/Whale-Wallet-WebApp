<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A message from the public contact form; read it in the database until a mail provider is connected. */
class ContactMessage extends Model
{
    protected $guarded = [];
}
