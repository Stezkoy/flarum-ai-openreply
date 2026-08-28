<?php

use Flarum\Database\Migration;

return Migration::addColumns('ai_openreply_sessions', [
    'message_count' => ['integer', 'unsigned' => true, 'default' => 0],
]);
