<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'price', 'category', 
        'tags', 'image_url', 'stock', 'is_active',
        'avg_rating', 'total_reviews'
    ];

    protected $casts = [
        'tags' => 'array',
        'price' => 'decimal:2',
        'avg_rating' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    public function interactions()
    {
        return $this->hasMany(UserInteraction::class);
    }

    public function recommendations()
    {
        return $this->hasMany(Recommendation::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function getViewCountAttribute()
    {
        return $this->interactions()->where('interaction_type', 'view')->count();
    }

    public function getPurchaseCountAttribute()
    {
        return $this->interactions()->where('interaction_type', 'purchase')->count();
    }
}


?>