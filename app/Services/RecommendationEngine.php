<?php


namespace App\Services;

use App\Models\Product;
use App\Models\UserInteraction;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class RecommendationEngine
{
    private AnalyticsService $analytics;

    public function __construct(AnalyticsService $analytics)
    {
        $this->analytics = $analytics;
    }

    /**
     * Generate personalized recommendations for a user or session
     */
    public function generateRecommendations($userId = null, $sessionId = null, $limit = 10): Collection
    {
        $recommendations = collect();

        // Get collaborative filtering recommendations
        $collaborative = $this->getCollaborativeRecommendations($userId, $sessionId, $limit);
        $recommendations = $recommendations->merge($collaborative);

        // Get content-based recommendations
        $contentBased = $this->getContentBasedRecommendations($userId, $sessionId, $limit);
        $recommendations = $recommendations->merge($contentBased);

        // Get trending products
        $trending = $this->getTrendingRecommendations($limit);
        $recommendations = $recommendations->merge($trending);

        // Remove duplicates and sort by score
        $recommendations = $recommendations
            ->groupBy('product_id')
            ->map(fn($group) => $group->sortByDesc('score')->first())
            ->sortByDesc('score')
            ->take($limit);

        // Store recommendations
        $this->storeRecommendations($recommendations, $userId, $sessionId);

        return $recommendations;
    }

    /**
     * Collaborative filtering based on user behavior similarity
     */
    private function getCollaborativeRecommendations($userId, $sessionId, $limit): Collection
    {
        if (!$userId && !$sessionId) {
            return collect();
        }

        // Get user's interaction history
        $userInteractions = UserInteraction::query()
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->when(!$userId, fn($q) => $q->where('session_id', $sessionId))
            ->whereIn('interaction_type', ['view', 'purchase', 'add_to_cart'])
            ->with('product')
            ->get();

        if ($userInteractions->isEmpty()) {
            return collect();
        }

        $userProductIds = $userInteractions->pluck('product_id')->unique();

        // Find similar users based on product interactions
        $similarUsers = UserInteraction::query()
            ->whereIn('product_id', $userProductIds)
            ->when($userId, fn($q) => $q->where('user_id', '!=', $userId))
            ->when(!$userId, fn($q) => $q->where('session_id', '!=', $sessionId))
            ->select('user_id', 'session_id', DB::raw('COUNT(*) as similarity_score'))
            ->groupBy('user_id', 'session_id')
            ->having('similarity_score', '>', 1)
            ->orderByDesc('similarity_score')
            ->limit(20)
            ->get();

        // Get products liked by similar users
        $similarUserIds = $similarUsers->pluck('user_id')->filter();
        $similarSessionIds = $similarUsers->pluck('session_id')->filter();

        $recommendedProducts = UserInteraction::query()
            ->when($similarUserIds->isNotEmpty(), fn($q) => $q->whereIn('user_id', $similarUserIds))
            ->when($similarSessionIds->isNotEmpty(), fn($q) => $q->orWhereIn('session_id', $similarSessionIds))
            ->whereNotIn('product_id', $userProductIds)
            ->whereIn('interaction_type', ['purchase', 'add_to_cart', 'wishlist'])
            ->select('product_id', DB::raw('COUNT(*) as recommendation_count'))
            ->groupBy('product_id')
            ->orderByDesc('recommendation_count')
            ->limit($limit)
            ->get();

        return $recommendedProducts->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'recommendation_type' => 'collaborative',
                'score' => min($item->recommendation_count / 10, 1.0), // Normalize score
                'reasoning' => ['Similar users also liked this product']
            ];
        });
    }

    /**
     * Content-based recommendations using product attributes
     */
    private function getContentBasedRecommendations($userId, $sessionId, $limit): Collection
    {
        // Get user's recently viewed/purchased products
        $userProducts = UserInteraction::query()
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->when(!$userId, fn($q) => $q->where('session_id', $sessionId))
            ->whereIn('interaction_type', ['view', 'purchase', 'add_to_cart'])
            ->with('product')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->pluck('product')
            ->filter();

        if ($userProducts->isEmpty()) {
            return collect();
        }

        // Extract categories and tags from user's products
        $preferredCategories = $userProducts->pluck('category')->countBy();
        $preferredTags = $userProducts->pluck('tags')
            ->flatten()
            ->filter()
            ->countBy();

        $userProductIds = $userProducts->pluck('id');

        // Find similar products based on category and tags
        $similarProducts = Product::query()
            ->active()
            ->whereNotIn('id', $userProductIds)
            ->get()
            ->map(function ($product) use ($preferredCategories, $preferredTags) {
                $score = 0;
                
                // Category similarity
                if ($preferredCategories->has($product->category)) {
                    $score += $preferredCategories[$product->category] * 0.3;
                }
                
                // Tag similarity
                $productTags = $product->tags ?? [];
                foreach ($productTags as $tag) {
                    if ($preferredTags->has($tag)) {
                        $score += $preferredTags[$tag] * 0.2;
                    }
                }
                
                // Rating boost
                $score += $product->avg_rating * 0.1;
                
                return [
                    'product_id' => $product->id,
                    'recommendation_type' => 'content_based',
                    'score' => min($score / 10, 1.0), // Normalize
                    'reasoning' => [
                        'Similar to products you\'ve viewed',
                        "Category: {$product->category}",
                        "Rating: {$product->avg_rating}/5"
                    ]
                ];
            })
            ->sortByDesc('score')
            ->take($limit);

        return $similarProducts;
    }

    /**
     * Get trending/popular products
     */
    private function getTrendingRecommendations($limit): Collection
    {
        $cacheKey = 'trending_products_' . $limit;
        
        return Cache::remember($cacheKey, 3600, function () use ($limit) {
            $trending = UserInteraction::query()
                ->where('created_at', '>=', now()->subDays(7))
                ->whereIn('interaction_type', ['view', 'purchase', 'add_to_cart'])
                ->select('product_id', DB::raw('COUNT(*) as interaction_count'))
                ->groupBy('product_id')
                ->orderByDesc('interaction_count')
                ->limit($limit)
                ->get();

            return $trending->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'recommendation_type' => 'trending',
                    'score' => min($item->interaction_count / 100, 1.0),
                    'reasoning' => ['Currently trending and popular']
                ];
            });
        });
    }

    /**
     * Get products similar to a specific product
     */
    public function getSimilarProducts($productId, $limit = 6): Collection
    {
        $product = Product::find($productId);
        if (!$product) {
            return collect();
        }

        // Find products in same category with similar tags
        $similarProducts = Product::query()
            ->active()
            ->where('id', '!=', $productId)
            ->where('category', $product->category)
            ->get()
            ->map(function ($similarProduct) use ($product) {
                $score = 0;
                
                // Tag similarity
                $productTags = $product->tags ?? [];
                $similarTags = $similarProduct->tags ?? [];
                $commonTags = array_intersect($productTags, $similarTags);
                $score += count($commonTags) * 0.3;
                
                // Price similarity (closer prices get higher scores)
                $priceDiff = abs($product->price - $similarProduct->price);
                $maxPrice = max($product->price, $similarProduct->price);
                $priceScore = $maxPrice > 0 ? (1 - $priceDiff / $maxPrice) : 1;
                $score += $priceScore * 0.2;
                
                // Rating similarity
                $ratingDiff = abs($product->avg_rating - $similarProduct->avg_rating);
                $ratingScore = (5 - $ratingDiff) / 5;
                $score += $ratingScore * 0.3;
                
                // Popularity boost
                $score += $similarProduct->total_reviews * 0.001;
                
                return [
                    'product_id' => $similarProduct->id,
                    'recommendation_type' => 'similar',
                    'score' => min($score, 1.0),
                    'reasoning' => [
                        'Similar to the product you\'re viewing',
                        "Same category: {$similarProduct->category}",
                        count($commonTags) . ' common tags'
                    ]
                ];
            })
            ->sortByDesc('score')
            ->take($limit);

        return $similarProducts;
    }

    /**
     * Store recommendations in database
     */
    private function storeRecommendations(Collection $recommendations, $userId, $sessionId): void
    {
        $data = $recommendations->map(function ($rec) use ($userId, $sessionId) {
            return [
                'user_id' => $userId,
                'session_id' => $sessionId,
                'product_id' => $rec['product_id'],
                'recommendation_type' => $rec['recommendation_type'],
                'score' => $rec['score'],
                'reasoning' => $rec['reasoning'],
                'created_at' => now(),
                'updated_at' => now()
            ];
        })->toArray();

        if (!empty($data)) {
            Recommendation::insert($data);
        }
    }

    /**
     * Track recommendation performance
     */
    public function trackRecommendationClick($recommendationId): void
    {
        Recommendation::where('id', $recommendationId)
            ->update(['was_clicked' => true]);
            
        $this->analytics->track('recommendation_clicked', [
            'recommendation_id' => $recommendationId
        ]);
    }

    public function trackRecommendationPurchase($productId, $userId = null, $sessionId = null): void
    {
        Recommendation::query()
            ->where('product_id', $productId)
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->when($sessionId, fn($q) => $q->where('session_id', $sessionId))
            ->update(['was_purchased' => true]);
            
        $this->analytics->track('recommendation_purchased', [
            'product_id' => $productId,
            'user_id' => $userId,
            'session_id' => $sessionId
        ]);
    }
}



?>