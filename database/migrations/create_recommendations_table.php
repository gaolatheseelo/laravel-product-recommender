<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('session_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->enum('recommendation_type', ['collaborative', 'content_based', 'trending', 'similar', 'ai_generated']);
            $table->decimal('score', 5, 4); // Recommendation confidence score
            $table->json('reasoning')->nullable(); // Why this was recommended
            $table->boolean('was_clicked')->default(false);
            $table->boolean('was_purchased')->default(false);
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            
            $table->index(['user_id', 'score']);
            $table->index(['session_id', 'score']);
            $table->index('recommendation_type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('recommendations');
    }
};


?>