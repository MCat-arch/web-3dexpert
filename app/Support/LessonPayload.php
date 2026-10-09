<?php

namespace App\Support;

use App\Models\CourseLesson;

class LessonPayload
{
    public static function for(CourseLesson $lesson, bool $unlocked): array
    {
        $payload = [
            'id' => $lesson->id,
            'title' => $lesson->title,
            'content_type' => $lesson->content_type,
            'duration_seconds' => $lesson->duration_seconds,
            'duration_formatted' => $lesson->durationFormatted(),
            'is_free_preview' => (bool) $lesson->is_free_preview,
            'sort_order' => $lesson->sort_order,
            'unlocked' => $unlocked,
        ];

        if (! $unlocked) {
            return $payload; // No youtube_id, no body. Ever.
        }

        return $payload + [
            'youtube_id' => $lesson->youtube_id,
            'embed_url' => $lesson->embed_url,
            'body' => $lesson->body,
            'attachment_url' => $lesson->attachmentUrl(),
        ];
    }
}
