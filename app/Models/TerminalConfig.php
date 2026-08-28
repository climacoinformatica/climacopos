<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TerminalConfig extends Model
{
    protected $table = 'terminal_config';

    protected $guarded = [];

    public $timestamps = false;

    public function terminal()
    {
        return $this->belongsTo(Terminal::class);
    }
}
