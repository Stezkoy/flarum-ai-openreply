<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::addColumns('ai_openreply_sessions', function (Blueprint $table) {
    $table->unsignedInteger('message_count')->default(0);
});
