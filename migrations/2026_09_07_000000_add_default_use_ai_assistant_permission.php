<?php

use Flarum\Database\Migration;
use Flarum\Group\Group;

return Migration::addPermissions([
    'discussion.useAIAssistant' => Group::MEMBER_ID,
]);
