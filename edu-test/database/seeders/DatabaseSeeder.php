<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Tạo giảng viên
        $instructor = User::create([
            'name' => 'Nguyen Van A',
            'email' => 'instructor@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Tạo học viên
        $student = User::create([
            'name' => 'Tran Thi B',
            'email' => 'student@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Tạo danh mục
        $categories = [
            ['name' => 'Lập trình Web', 'slug' => 'lap-trinh-web', 'description' => 'Các khóa học về lập trình web'],
            ['name' => 'Mobile App', 'slug' => 'mobile-app', 'description' => 'Phát triển ứng dụng di động'],
            ['name' => 'Data Science', 'slug' => 'data-science', 'description' => 'Khoa học dữ liệu và AI'],
            ['name' => 'DevOps', 'slug' => 'devops', 'description' => 'Triển khai và vận hành hệ thống'],
        ];

        foreach ($categories as $cat) {
            Category::create($cat);
        }

        // Tạo khóa học
        $courses = [
            [
                'title' => 'Laravel từ cơ bản đến nâng cao',
                'slug' => 'laravel-co-ban-den-nang-cao',
                'description' => 'Học Laravel từ zero đến hero',
                'category_id' => 1,
                'instructor_id' => $instructor->id,
                'price' => 499000,
                'level' => 'beginner',
                'status' => 'published',
                'duration_hours' => 40,
                'max_students' => 100,
            ],
            [
                'title' => 'React Native cho người mới',
                'slug' => 'react-native-cho-nguoi-moi',
                'description' => 'Xây dựng app mobile với React Native',
                'category_id' => 2,
                'instructor_id' => $instructor->id,
                'price' => 599000,
                'level' => 'beginner',
                'status' => 'published',
                'duration_hours' => 35,
            ],
            [
                'title' => 'Machine Learning với Python',
                'slug' => 'machine-learning-python',
                'description' => 'Nhập môn Machine Learning',
                'category_id' => 3,
                'instructor_id' => $instructor->id,
                'price' => 799000,
                'level' => 'intermediate',
                'status' => 'draft',
                'duration_hours' => 60,
            ],
        ];

        foreach ($courses as $course) {
            Course::create($course);
        }

        // Đăng ký học viên vào khóa học
        $student->enrolledCourses()->attach(1, [
            'status' => 'active',
            'progress' => 30,
        ]);
    }
}
