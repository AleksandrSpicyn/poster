<?php

declare(strict_types=1);

/** @return array<string,mixed> */
function tiktokCreatorResponse(): array
{
    return ['error' => ['code' => 'ok'], 'data' => ['creator_nickname' => 'Alpha', 'max_video_post_duration_sec' => 60,
        'privacy_level_options' => ['PUBLIC_TO_EVERYONE', 'SELF_ONLY'], 'comment_disabled' => false, 'duet_disabled' => true, 'stitch_disabled' => true]];
}
