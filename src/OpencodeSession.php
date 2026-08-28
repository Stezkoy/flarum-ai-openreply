<?php

namespace Stezkoy\FlarumAIOpenReply;

use Flarum\Database\AbstractModel;

/**
 * Maps a Flarum discussion to its persistent opencode session.
 */
class OpencodeSession extends AbstractModel
{
    protected $table = 'ai_openreply_sessions';

    protected $guarded = [];

    public $timestamps = true;
}