<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::addTableCreate('ai_openreply_sessions', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('discussion_id')->index();
    $table->string('session_id', 64)->index();
    $table->timestamp('created_at')->nullable();
    $table->timestamp('updated_at')->nullable();
});