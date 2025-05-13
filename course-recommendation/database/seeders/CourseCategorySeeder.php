<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class CourseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define constants
        $totalCourses = 3447; // Number of courses
        $categoryIds = range(1, 10); // Category IDs (1 to 10)

        // Prepare data for insertion
        $courseCategories = [];

        // Loop through each course
        for ($courseId = 1; $courseId <= $totalCourses; $courseId++) {
            // Randomly select 1 to 3 categories
            $numCategories = rand(1, 3);
            $selectedCategories = array_rand(array_flip($categoryIds), $numCategories);

            // Ensure selectedCategories is an array
            if (!is_array($selectedCategories)) {
                $selectedCategories = [$selectedCategories];
            }

            // Add course-category pairs
            foreach ($selectedCategories as $categoryId) {
                $courseCategories[] = [
                    'course_id' => $courseId,
                    'category_id' => $categoryId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Insert data in chunks to avoid memory issues
        $chunks = array_chunk($courseCategories, 1000);
        foreach ($chunks as $chunk) {
            DB::table('course_category')->insert($chunk);
        }
    }
}