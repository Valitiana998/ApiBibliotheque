<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'isbn', 'year', 'available', 'author_id'];

    protected $casts = ['year' => 'integer', 'available' => 'boolean'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function borrows(): HasMany
    {
        return $this->hasMany(Borrow::class);
    }
}
