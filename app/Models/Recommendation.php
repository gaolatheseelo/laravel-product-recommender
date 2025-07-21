<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recommendation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'session_id', 'product_id', 'recommendation_type',
        'score', 'reasoning', 'was_clicked', 'was_purchased'
    ];

    protected $casts = [
        'reasoning' => 'array',
        'score' => 'decimal:4',
        'was_clicked' => 'boolean',
        'was_purchased' => 'boolean'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForSession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('recommendation_type', $type);
    }

    public function scopeHighScore($query, $minScore = 0.5)
    {
        return $query->where('score', '>=', $minScore);
    }
}


?>