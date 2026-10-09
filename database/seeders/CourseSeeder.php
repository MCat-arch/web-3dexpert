<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Paid Masterclass Course
        $blenderCourse = Course::create([
            'title' => [
                'id' => 'Masterclass Blender 3D: Pemodelan & Rendering Arsitektur',
                'en' => 'Blender 3D Masterclass: Architectural Modeling & Rendering',
            ],
            'slug' => 'masterclass-blender-3d-arsitektur',
            'description' => [
                'id' => "Pelajari teknik pemodelan arsitektur 3D dari nol hingga menghasilkan visualisasi fotorealistik tingkat industri. Kursus komprehensif ini dirancang khusus untuk arsitek, desainer interior, dan praktisi visualisasi 3D.\n\nAnda akan mempelajari workflow profesional: mulai dari membaca blueprint denah 2D, teknik blocking presisi, pengaturan lighting Cycles engine, pembuatan material PBR realistis, hingga render post-production di Blender Compositor.",
                'en' => "Learn 3D architectural modeling techniques from scratch to produce industry-standard photorealistic visualizations. This comprehensive course is tailored for architects, interior designers, and 3D artists.",
            ],
            'price' => 450000.00,
            'level' => 'intermediate',
            'estimated_minutes' => 240,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $section1 = CourseSection::create([
            'course_id' => $blenderCourse->id,
            'title' => [
                'id' => 'Fundamental & Workflow Arsitektur',
                'en' => 'Architecture Fundamentals & Workflow',
            ],
            'sort_order' => 1,
        ]);

        CourseLesson::create([
            'course_section_id' => $section1->id,
            'title' => [
                'id' => 'Pengenalan Interface & Navigasi Cepat Blender 4.x',
                'en' => 'Interface & Fast Navigation Overview in Blender 4.x',
            ],
            'content_type' => 'video',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'youtube_id' => 'dQw4w9WgXcQ',
            'duration_seconds' => 720,
            'is_free_preview' => true,
            'sort_order' => 1,
            'body' => [
                'id' => 'Pelajaran pengantar mengenai setup workspace arsitektur di Blender dan shortcut esensial.',
                'en' => 'Introductory lesson covering architecture workspace setup and essential shortcuts in Blender.',
            ],
        ]);

        CourseLesson::create([
            'course_section_id' => $section1->id,
            'title' => [
                'id' => 'Import Blueprint CAD & Setup Skala Presisi',
                'en' => 'Importing CAD Blueprints & Precise Scale Setup',
            ],
            'content_type' => 'video',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'youtube_id' => 'dQw4w9WgXcQ',
            'duration_seconds' => 960,
            'is_free_preview' => false,
            'sort_order' => 2,
        ]);

        $section2 = CourseSection::create([
            'course_id' => $blenderCourse->id,
            'title' => [
                'id' => 'Pemodelan Dinding, Pintu, Jendela & Furnitur',
                'en' => 'Wall, Door, Window & Furniture Modeling',
            ],
            'sort_order' => 2,
        ]);

        CourseLesson::create([
            'course_section_id' => $section2->id,
            'title' => [
                'id' => 'Teknik Extrude Dinding & Modifiers Presisi',
                'en' => 'Wall Extrusion Techniques & Precision Modifiers',
            ],
            'content_type' => 'video',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'youtube_id' => 'dQw4w9WgXcQ',
            'duration_seconds' => 1200,
            'is_free_preview' => false,
            'sort_order' => 1,
        ]);

        CourseLesson::create([
            'course_section_id' => $section2->id,
            'title' => [
                'id' => 'Material PBR & Pencahayaan Realistis Cycles',
                'en' => 'PBR Materials & Cycles Realistic Lighting',
            ],
            'content_type' => 'video',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'youtube_id' => 'dQw4w9WgXcQ',
            'duration_seconds' => 1500,
            'is_free_preview' => false,
            'sort_order' => 2,
        ]);

        // 2. Free Starter Course
        $printingCourse = Course::create([
            'title' => [
                'id' => 'Dasar 3D Printing & Slicing untuk Pemula',
                'en' => '3D Printing & Slicing Essentials for Beginners',
            ],
            'slug' => 'dasar-3d-printing-dan-slicing',
            'description' => [
                'id' => "Pelajari cara mengubah file model 3D digital (.STL / .OBJ) menjadi objek fisik siap cetak menggunakan mesin 3D printer FDM.\n\nKursus pengantar gratis ini membahas jenis filamen (PLA, PETG, ABS), kalibrasi bed, orientasi cetak, hingga pengaturan software slicing (Bambu Studio & Ultimaker Cura) untuk hasil cetak yang kuat dan rapi.",
                'en' => "Learn how to turn 3D digital models into real physical parts using FDM 3D printing technology.",
            ],
            'price' => 0.00,
            'level' => 'beginner',
            'estimated_minutes' => 90,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $pSection1 = CourseSection::create([
            'course_id' => $printingCourse->id,
            'title' => [
                'id' => 'Pengenalan Mesin & Material 3D Printing',
                'en' => 'Introduction to 3D Printers & Materials',
            ],
            'sort_order' => 1,
        ]);

        CourseLesson::create([
            'course_section_id' => $pSection1->id,
            'title' => [
                'id' => 'Cara Kerja 3D Printer FDM & Anatomi Nozzle',
                'en' => 'How FDM 3D Printers Work & Nozzle Anatomy',
            ],
            'content_type' => 'video',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'youtube_id' => 'dQw4w9WgXcQ',
            'duration_seconds' => 600,
            'is_free_preview' => true,
            'sort_order' => 1,
        ]);

        CourseLesson::create([
            'course_section_id' => $pSection1->id,
            'title' => [
                'id' => 'Setting Infill, Support, dan Layer Height di Slicer',
                'en' => 'Infill, Support, and Layer Height in Slicer',
            ],
            'content_type' => 'video',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'youtube_id' => 'dQw4w9WgXcQ',
            'duration_seconds' => 850,
            'is_free_preview' => true,
            'sort_order' => 2,
        ]);
    }
}
