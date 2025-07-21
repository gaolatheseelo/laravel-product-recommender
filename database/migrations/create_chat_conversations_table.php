<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('session_id');
            $table->text('message');
            $table->enum('sender', ['user', 'bot']);
            $table->json('context')->nullable(); // Store conversation context
            $table->json('recommended_products')->nullable(); // Products mentioned/recommended
            $table->enum('message_type', ['text', 'product_recommendation', 'query_response']);
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'created_at']);
            $table->index(['session_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('chat_conversations');
    }
};


?>