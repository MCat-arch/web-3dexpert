# Phase P4 — Course Delivery, Progress & Quizzes

| | |
|---|---|
| **Depends on** | P3 |
| **Blocks** | — |
| **Blocked by** | Nothing once P3 passes |
| **Plan stability** | **Expected revision** — revisit after P3 settles the payload and Filament nesting |

## Objective

The learning experience for someone who has paid: navigate a course, watch
lessons, track progress, take optional self-check quizzes. Decision **D7**,
assumptions **A9** (free navigation), **A11** (self-check quizzes).

**Constraint carried from P3:** every read of lesson content goes through
`CourseLessonPolicy` and `LessonPayload`. This phase adds **no** new access path.
If it feels like it needs one, the P3 design is wrong — fix it there.

---

## Step 1 — Schema

```bash
php artisan make:migration create_lesson_completions_table --no-interaction
php artisan make:migration create_quizzes_table --no-interaction
php artisan make:migration create_quiz_questions_table --no-interaction
php artisan make:migration create_quiz_options_table --no-interaction
php artisan make:migration create_quiz_attempts_table --no-interaction
```

**`lesson_completions`** — `id`, `course_enrollment_id` cascadeOnDelete,
`course_lesson_id` cascadeOnDelete, `completed_at`, timestamps.
**Unique (`course_enrollment_id`, `course_lesson_id`)** — this unique index *is*
the idempotence guarantee; do not rely on an application-level check alone.

**`quizzes`** — `id`, `course_lesson_id` nullable cascadeOnDelete,
`course_section_id` nullable cascadeOnDelete, `title` json, `pass_mark` unsigned
tinyint (percentage), timestamps. Exactly one parent must be set — enforce in the
model and in Filament, since a DB-level check constraint is awkward across MySQL
versions.

**`quiz_questions`** — `id`, `quiz_id` cascadeOnDelete, `prompt` json,
`type` string (`single` / `multiple`), `sort_order`.

**`quiz_options`** — `id`, `quiz_question_id` cascadeOnDelete, `label` json,
`is_correct` boolean, `sort_order`.

**`quiz_attempts`** — `id`, `course_enrollment_id` cascadeOnDelete,
`quiz_id` cascadeOnDelete, `score` unsigned tinyint, `passed` boolean,
`answers` json, `attempted_at`, timestamps. Index
(`course_enrollment_id`, `quiz_id`).

Attempts are append-only history — never updated, never deduplicated. A retake is
a new row.

---

## Step 2 — Models

```bash
php artisan make:model LessonCompletion --factory --no-interaction
php artisan make:model Quiz --factory --no-interaction
php artisan make:model QuizQuestion --factory --no-interaction
php artisan make:model QuizOption --factory --no-interaction
php artisan make:model QuizAttempt --factory --no-interaction
```

`Quiz`, `QuizQuestion`, `QuizOption` use `HasTranslations` for `title`, `prompt`,
`label`.

Add to `CourseEnrollment` (from P3): `completions()`, `quizAttempts()`,
`progressPercent(): int`.

**`is_correct` must never be serialized to the student.** Keep it out of
`$visible` on `QuizOption`, or exclude it in whatever payload the player uses. The
answer key reaching page source is the same class of leak as P3's video ID.

---

## Step 3 — Progress

**`app/Actions/Courses/CompleteLesson.php`**

1. Authorize via `CourseLessonPolicy` — the same gate as viewing. A lesson you
   cannot view cannot be completed.
2. `firstOrCreate` on (`enrollment`, `lesson`) — idempotent by the unique index.
3. If every lesson in the course is now complete, stamp
   `course_enrollments.completed_at`.
4. Invalidate the cached progress for that enrollment.

**Progress caching** (`guide.md` §5 rule 2 and §4):

- Derive from `lesson_completions` count against the course lesson count.
- Cache **per enrollment**, key constant on `CourseEnrollment`, invalidated by
  `CompleteLesson`.
- **Never** recompute inside a view loop. The "my courses" dashboard lists many
  enrollments; a count query per row is the N+1 this rule exists to prevent.
- This cache is keyed per enrollment, so it carries no cross-entitlement risk —
  unlike a lesson payload, which is not cached at all.

---

## Step 4 — Quizzes

**`app/Actions/Courses/SubmitQuizAttempt.php`**

1. Authorize: active enrollment on the quiz's course.
2. Score server-side against `quiz_options.is_correct`. Never trust a client-sent
   score.
3. Record the attempt with the submitted answers.
4. Compare to `pass_mark` for the `passed` flag.

**A failed attempt changes nothing.** It does not revoke access, block
navigation, or lock a lesson (A11). Quizzes are feedback, not a gate — the
gradebook option was explicitly rejected (D7).

---

## Step 5 — Player and dashboard

**Controllers** `app/Http/Controllers/Course/`:

| Controller | Route |
|---|---|
| `LessonPlayerController` | `GET /kursus/{course:slug}/{lesson}` |
| `LessonCompletionController` | `POST /kursus/{course:slug}/{lesson}/selesai` |
| `QuizAttemptController` | `POST /kuis/{quiz}/jawab` |
| `MyCoursesController` | `GET /akun/kursus` |

All inside the `{locale?}` group, all behind `auth:customer` except the player,
which must stay reachable for free-preview lessons by guests (P3, A15).

**Navigation:** free movement across sections and lessons (A9). No sequential
unlock.

**Player view** — embed via `youtube-nocookie.com`, `rel=0`, modest branding.
Overlay the signed-in student's own email on the player as a sharing deterrent
(`notes.md` §3.2). It deters; it does not prevent, and nothing here substitutes
for the P3 gate.

**Dashboard** — mounts into the P1 account shell. Lists active enrollments with
cached progress and a resume link.

**State-changing routes are POST.** Marking a lesson complete over GET would
violate `guide.md` §10.4 and make progress forgeable by link.

---

## Step 6 — Filament

Nested under `CourseResource` from P3: Quizzes, Questions, Options. Reuse the
nesting approach P3 settled on rather than inventing a second pattern.

Read-only quiz-attempt view per enrollment, so staff can see where students
struggle.

---

## Step 7 — Tests

`tests/Feature/Courses/`.

**`LessonProgressTest`**
- `test_completing_a_lesson_twice_creates_one_record`
- `test_progress_percentage_is_accurate`
- `test_course_completes_when_all_lessons_are_complete`
- `test_non_entitled_customer_cannot_complete_a_lesson`
- `test_completion_invalidates_cached_progress`
- `test_dashboard_does_not_issue_a_query_per_enrollment` — assert query count with
  `DB::listen` for 10 enrollments.

**`QuizAttemptTest`**
- `test_scoring_is_correct_for_single_and_multiple_choice`
- `test_client_submitted_score_is_ignored`
- `test_failed_attempt_does_not_revoke_access_or_block_navigation`
- `test_retake_creates_a_second_attempt`
- `test_answer_key_is_not_present_in_the_response` — assert `is_correct` never
  reaches the body.

**`LessonPlayerTest`**
- `test_player_reads_content_through_the_entitlement_gate` — re-assert P3's
  guarantee from this entry point. The gate is one path; prove the player uses it.
- `test_guest_can_play_a_free_preview_lesson`

---

## Validation

```bash
php artisan migrate
php artisan test --filter=Course
php artisan test
vendor/bin/pint --dirty --format agent
```

Manual: purchase a course, complete lessons in a non-linear order, confirm
progress tracks, take a quiz and fail it, confirm nothing locks.

---

## Acceptance

From `task.md` Phase P4:

- [ ] A purchaser can navigate a course, complete lessons, and see accurate progress.
- [ ] Marking a lesson complete twice produces one record.
- [ ] The player page reads content through the P3 gate and no other path.
- [ ] Quiz results are recorded without gating progress.

Plus: no N+1 on the player or the dashboard, verified with query counts.
