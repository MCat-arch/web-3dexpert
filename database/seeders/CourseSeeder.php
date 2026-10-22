<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use Illuminate\Database\Seeder;

/**
 * Dummy course data for the 3D Expert company profile.
 *
 * 3-tier structure:
 *  1. Beginner   (has_lms = true)  → Online LMS with video lessons
 *  2. Intermediate (has_lms = true) → Online LMS with video lessons
 *  3. Advanced   (has_lms = false) → Real-world mentoring, WhatsApp CTA only
 *
 * Safe to run on an existing DB: every Course::updateOrCreate() is keyed on slug.
 */
class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBeginner();
        $this->seedIntermediate();
        $this->seedAdvanced();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. BEGINNER — Online LMS
    // ─────────────────────────────────────────────────────────────────────────
    private function seedBeginner(): void
    {
        $course = Course::updateOrCreate(
            ['slug' => 'dasar-desain-3d-untuk-pemula'],
            [
                'title' => [
                    'id' => 'Dasar Desain 3D untuk Pemula',
                    'en' => '3D Design Fundamentals for Beginners',
                ],
                'description' => [
                    'id' => "Mulai perjalanan desain 3D Anda dari nol. Kursus ini dirancang khusus untuk pemula yang belum pernah menyentuh software 3D sama sekali.\n\nAnda akan belajar memahami antarmuka Blender, teknik pemodelan dasar, pengaturan cahaya sederhana, dan cara menghasilkan render 3D pertama Anda. Tidak perlu pengalaman sebelumnya — cukup semangat belajar!",
                    'en' => "Start your 3D design journey from scratch. This course is designed for complete beginners with no prior 3D software experience.\n\nYou'll learn to navigate Blender, basic modeling techniques, simple lighting setup, and how to produce your first 3D render.",
                ],
                'price' => 0.00,       // FREE beginner course
                'level' => 'beginner',
                'has_lms' => true,
                'category' => 'Desain 3D',
                'estimated_minutes' => 120,
                'is_published' => true,
                'published_at' => now()->subDays(10),
            ]
        );

        // Only seed lessons if none exist yet
        if ($course->sections()->count() > 0) {
            return;
        }

        $sec1 = CourseSection::create([
            'course_id' => $course->id,
            'title' => ['id' => 'Mengenal Antarmuka Blender', 'en' => 'Getting to Know the Blender Interface'],
            'sort_order' => 1,
        ]);

        CourseLesson::create([
            'course_section_id' => $sec1->id,
            'title' => ['id' => 'Download & Instalasi Blender Gratis', 'en' => 'Download & Install Blender for Free'],
            'content_type' => 'video',
            'youtube_url' => 'https://www.youtube.com/watch?v=nIoXOplUvAw',
            'youtube_id' => 'nIoXOplUvAw',
            'duration_seconds' => 600,
            'is_free_preview' => true,
            'sort_order' => 1,
            'body' => ['id' => 'Instalasi Blender untuk Windows, Mac, dan Linux.', 'en' => 'Installing Blender on Windows, Mac, and Linux.'],
        ]);

        CourseLesson::create([
            'course_section_id' => $sec1->id,
            'title' => ['id' => 'Navigasi 3D Viewport & Panel Dasar', 'en' => 'Navigating the 3D Viewport & Basic Panels'],
            'content_type' => 'video',
            'youtube_url' => 'https://www.youtube.com/watch?v=nIoXOplUvAw',
            'youtube_id' => 'nIoXOplUvAw',
            'duration_seconds' => 780,
            'is_free_preview' => false,
            'sort_order' => 2,
        ]);

        $sec2 = CourseSection::create([
            'course_id' => $course->id,
            'title' => ['id' => 'Pemodelan Objek Sederhana', 'en' => 'Modeling Simple Objects'],
            'sort_order' => 2,
        ]);

        CourseLesson::create([
            'course_section_id' => $sec2->id,
            'title' => ['id' => 'Teknik Extrude & Scale Dasar', 'en' => 'Basic Extrude & Scale Techniques'],
            'content_type' => 'video',
            'youtube_url' => 'https://www.youtube.com/watch?v=nIoXOplUvAw',
            'youtube_id' => 'nIoXOplUvAw',
            'duration_seconds' => 900,
            'is_free_preview' => false,
            'sort_order' => 1,
        ]);

        CourseLesson::create([
            'course_section_id' => $sec2->id,
            'title' => ['id' => 'Render Pertama Anda: Output PNG', 'en' => 'Your First Render: PNG Output'],
            'content_type' => 'video',
            'youtube_url' => 'https://www.youtube.com/watch?v=nIoXOplUvAw',
            'youtube_id' => 'nIoXOplUvAw',
            'duration_seconds' => 720,
            'is_free_preview' => false,
            'sort_order' => 2,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. INTERMEDIATE — Online LMS
    // ─────────────────────────────────────────────────────────────────────────
    private function seedIntermediate(): void
    {
        $course = Course::updateOrCreate(
            ['slug' => 'masterclass-blender-3d-arsitektur'],
            [
                'title' => [
                    'id' => 'Masterclass Blender 3D: Pemodelan & Rendering Arsitektur',
                    'en' => 'Blender 3D Masterclass: Architectural Modeling & Rendering',
                ],
                'description' => [
                    'id' => "Pelajari teknik pemodelan arsitektur 3D dari nol hingga menghasilkan visualisasi fotorealistik tingkat industri. Kursus komprehensif ini dirancang khusus untuk arsitek, desainer interior, dan praktisi visualisasi 3D.\n\nAnda akan mempelajari workflow profesional: mulai dari membaca blueprint denah 2D, teknik blocking presisi, pengaturan lighting Cycles engine, pembuatan material PBR realistis, hingga render post-production di Blender Compositor.",
                    'en' => 'Learn 3D architectural modeling techniques from scratch to produce industry-standard photorealistic visualizations. This comprehensive course is tailored for architects, interior designers, and 3D artists.',
                ],
                'price' => 450000.00,
                'level' => 'intermediate',
                'has_lms' => true,
                'category' => 'Arsitektur & Interior',
                'estimated_minutes' => 240,
                'is_published' => true,
                'published_at' => now()->subDays(5),
            ]
        );

        if ($course->sections()->count() > 0) {
            return;
        }

        $sec1 = CourseSection::create([
            'course_id' => $course->id,
            'title' => ['id' => 'Fundamental & Workflow Arsitektur', 'en' => 'Architecture Fundamentals & Workflow'],
            'sort_order' => 1,
        ]);

        CourseLesson::create([
            'course_section_id' => $sec1->id,
            'title' => ['id' => 'Pengenalan Interface & Navigasi Cepat Blender 4.x', 'en' => 'Interface & Fast Navigation Overview in Blender 4.x'],
            'content_type' => 'video',
            'youtube_url' => 'https://www.youtube.com/watch?v=nIoXOplUvAw',
            'youtube_id' => 'nIoXOplUvAw',
            'duration_seconds' => 720,
            'is_free_preview' => true,
            'sort_order' => 1,
            'body' => ['id' => 'Pelajaran pengantar mengenai setup workspace arsitektur di Blender dan shortcut esensial.', 'en' => 'Introductory lesson covering architecture workspace setup and essential shortcuts.'],
        ]);

        CourseLesson::create([
            'course_section_id' => $sec1->id,
            'title' => ['id' => 'Import Blueprint CAD & Setup Skala Presisi', 'en' => 'Importing CAD Blueprints & Precise Scale Setup'],
            'content_type' => 'video',
            'youtube_url' => 'https://www.youtube.com/watch?v=nIoXOplUvAw',
            'youtube_id' => 'nIoXOplUvAw',
            'duration_seconds' => 960,
            'is_free_preview' => false,
            'sort_order' => 2,
        ]);

        $sec2 = CourseSection::create([
            'course_id' => $course->id,
            'title' => ['id' => 'Pemodelan Dinding, Pintu, Jendela & Furnitur', 'en' => 'Wall, Door, Window & Furniture Modeling'],
            'sort_order' => 2,
        ]);

        CourseLesson::create([
            'course_section_id' => $sec2->id,
            'title' => ['id' => 'Teknik Extrude Dinding & Modifiers Presisi', 'en' => 'Wall Extrusion Techniques & Precision Modifiers'],
            'content_type' => 'video',
            'youtube_url' => 'https://www.youtube.com/watch?v=nIoXOplUvAw',
            'youtube_id' => 'nIoXOplUvAw',
            'duration_seconds' => 1200,
            'is_free_preview' => false,
            'sort_order' => 1,
        ]);

        CourseLesson::create([
            'course_section_id' => $sec2->id,
            'title' => ['id' => 'Material PBR & Pencahayaan Realistis Cycles', 'en' => 'PBR Materials & Cycles Realistic Lighting'],
            'content_type' => 'video',
            'youtube_url' => 'https://www.youtube.com/watch?v=nIoXOplUvAw',
            'youtube_id' => 'nIoXOplUvAw',
            'duration_seconds' => 1500,
            'is_free_preview' => false,
            'sort_order' => 2,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. ADVANCED — Real-world mentoring, NO LMS player
    // ─────────────────────────────────────────────────────────────────────────
    private function seedAdvanced(): void
    {
        // No sections/lessons — advanced is a real-world mentoring program
        Course::updateOrCreate(
            ['slug' => 'mentoring-proyek-nyata-arsitektur-3d'],
            [
                'title' => [
                    'id' => 'Mentoring Proyek Nyata: Arsitektur & Interior 3D',
                    'en' => 'Real-World Project Mentoring: Architecture & Interior 3D',
                ],
                'description' => [
                    'id' => "Program mentoring eksklusif untuk profesional yang ingin mengerjakan proyek arsitektur dan interior 3D nyata bersama mentor berpengalaman dari tim 3D Expert.\n\nPeserta akan ditugaskan dalam proyek sungguhan — mulai dari pengukuran lapangan, desain konsep, hingga presentasi ke klien. Ini bukan kursus online biasa: Anda bekerja langsung di lapangan dan mendapat bimbingan intensif.\n\nKuota sangat terbatas. Hubungi kami untuk informasi batch berikutnya.",
                    'en' => "Exclusive mentoring program for professionals who want to work on real architectural and interior 3D projects alongside experienced mentors from the 3D Expert team.\n\nParticipants are assigned to actual projects — from site measurement, concept design, to client presentation. This is not a typical online course: you work directly in the field with intensive guidance.\n\nSeats are very limited. Contact us for the next batch schedule.",
                ],
                'price' => 3500000.00,
                'level' => 'advanced',
                'has_lms' => false,   // ← key flag: no LMS player
                'category' => 'Mentoring Langsung',
                'whatsapp_cta' => 'https://wa.me/6282338389488?text=Halo%2C%20saya%20ingin%20tahu%20lebih%20lanjut%20tentang%20Program%20Mentoring%20Proyek%20Nyata.',
                'estimated_minutes' => null,    // duration is project-based
                'is_published' => true,
                'published_at' => now(),
            ]
        );
    }
}
