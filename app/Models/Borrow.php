<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Borrow extends Model
{
    use HasFactory;

    protected $fillable = ['user_name', 'book_id', 'borrowed_at', 'returned_at'];

    protected $casts = ['borrowed_at' => 'datetime', 'returned_at' => 'datetime'];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
