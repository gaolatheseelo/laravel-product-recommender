<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('user_interactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('session_id');
            $table->unsignedBigInteger('product_id');
            $table->enum('interaction_type', ['view', 'click', 'add_to_cart', 'purchase', 'wishlist', 'rating']);
            $table->json('metadata')->nullable(); // Store additional context
            $table->decimal('value', 10, 2)->nullable(); // For purchases, ratings
            $table->timestamp('created_at');
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            
            $table->index(['user_id', 'interaction_type']);
            $table->index(['session_id', 'interaction_type']);
            $table->index(['product_id', 'interaction_type']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_interactions');
    }
};


?>