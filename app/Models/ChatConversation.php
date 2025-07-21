<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'session_id', 'message', 'sender',
        'context', 'recommended_products', 'message_type'
    ];

    protected $casts = [
        'context' => 'array',
        'recommended_products' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForSession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeByMessageType($query, $type)
    {
        return $query->where('message_type', $type);
    }
}



?>