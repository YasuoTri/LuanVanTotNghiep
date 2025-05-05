<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Course;
use App\Models\Interaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed Users - Create 100 diverse users
        $this->seedUsers();
        
        // Courses are already seeded with 3522 records
        $this->command->info('Courses are already seeded with 1000 records.');
        
        // Seed Interactions - Create diverse interactions between users and courses
        $this->seedInteractions();
    }
    
    /**
     * Seed users table with 100 diverse users
     */
    private function seedUsers()
    {
        $this->command->info('Seeding 100 users...');
        
        // Create admin user
        User::create([
            'userid_DI' => 'admin_user',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'final_cc_cname_DI' => 'United States',
            'LoE_DI' => 'PhD',
            'YoB' => 1985,
            'gender' => 'Male',
        ]);

        // Create test user
        User::create([
            'userid_DI' => 'test_user',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'final_cc_cname_DI' => 'Canada',
            'LoE_DI' => 'Bachelor',
            'YoB' => 1990,
            'gender' => 'Female',
        ]);
        
        // Create 98 more diverse users
        $educationLevels = ['High School', 'Bachelor', 'Master', 'PhD', 'Associate', 'Unknown'];
        $countries = [
            'United States', 'India', 'United Kingdom', 'Canada', 'Australia', 
            'Germany', 'France', 'Brazil', 'Japan', 'China', 'Mexico', 'Spain',
            'Italy', 'Russia', 'South Korea', 'Netherlands', 'Sweden', 'Switzerland',
            'Singapore', 'South Africa', 'Unknown'
        ];
        $genders = ['Male', 'Female', 'Other', 'Prefer not to say', null];
        
        for ($i = 0; $i < 98; $i++) {
            $birthYear = rand(1960, 2005);
            
            User::create([
                'userid_DI' => 'user_' . Str::uuid(),
                'email' => 'user' . ($i + 3) . '@example.com',
                'password' => Hash::make('password'),
                'final_cc_cname_DI' => $countries[array_rand($countries)],
                'LoE_DI' => $educationLevels[array_rand($educationLevels)],
                'YoB' => $birthYear,
                'gender' => $genders[array_rand($genders)],
            ]);
        }
        
        $this->command->info('Users seeded successfully!');
    }
    
    /**
     * Seed interactions table with diverse interactions
     */
    private function seedInteractions()
    {
        $this->command->info('Seeding interactions...');
        
        $users = User::all();
        $totalCourses = Course::count();
        $this->command->info("Total courses available: {$totalCourses}");
        
        // For each user, create between 5-30 interactions with random courses
        foreach ($users as $user) {
            // Determine how many interactions this user will have
            $interactionCount = rand(5, 30);
            $this->command->info("Creating {$interactionCount} interactions for user {$user->id}");
            
            // Get random course IDs for this user
            $courseIds = [];
            for ($i = 0; $i < $interactionCount; $i++) {
                $courseId = rand(1, $totalCourses);
                // Avoid duplicate interactions with the same course
                if (!in_array($courseId, $courseIds)) {
                    $courseIds[] = $courseId;
                }
            }
            
            // Create interactions for each selected course
            foreach ($courseIds as $courseId) {
                $viewed = rand(0, 100) < 80; // 80% chance of being viewed
                $explored = $viewed ? (rand(0, 100) < 60) : false; // 60% chance of being explored if viewed
                $certified = $explored ? (rand(0, 100) < 40) : false; // 40% chance of being certified if explored
                
                $startTime = now()->subDays(rand(1, 365));
                $lastEvent = $viewed ? $startTime->copy()->addDays(rand(1, 30)) : null;
                
                $nevents = $viewed ? rand(1, 100) : 0;
                $ndaysAct = $viewed ? rand(1, 30) : 0;
                $nplayVideo = $viewed ? rand(0, 50) : 0;
                $nchapters = $viewed ? rand(0, 20) : 0;
                $nforumPosts = $viewed ? rand(0, 10) : 0;
                
                // Rating is more likely to be higher for certified users
                $rating = null;
                if ($viewed) {
                    if ($certified) {
                        $rating = rand(30, 50) / 10; // 3.0-5.0 for certified users
                    } elseif ($explored) {
                        $rating = rand(20, 50) / 10; // 2.0-5.0 for explored users
                    } else {
                        $rating = rand(0, 100) < 70 ? rand(10, 50) / 10 : null; // 70% chance of rating if only viewed
                    }
                }
                
                Interaction::create([
                    'user_id' => $user->id,
                    'course_id' => $courseId,
                    'rating' => $rating,
                    'viewed' => $viewed,
                    'explored' => $explored,
                    'certified' => $certified,
                    'start_time' => $startTime,
                    'last_event' => $lastEvent,
                    'nevents' => $nevents,
                    'ndays_act' => $ndaysAct,
                    'nplay_video' => $nplayVideo,
                    'nchapters' => $nchapters,
                    'nforum_posts' => $nforumPosts,
                ]);
            }
        }
        
        // Create some "popular" courses with many interactions
        $this->createPopularCourseInteractions();
        
        $this->command->info('Interactions seeded successfully!');
    }
    
    /**
     * Create interactions for some popular courses
     */
    private function createPopularCourseInteractions()
    {
        $this->command->info('Creating popular course interactions...');
        
        // Select 20 random courses to be "popular"
        $popularCourseIds = [];
        for ($i = 0; $i < 20; $i++) {
            $popularCourseIds[] = rand(1, 1000);
        }
        
        $users = User::all();
        
        // For each popular course, create additional interactions
        foreach ($popularCourseIds as $courseId) {
            // Determine how many users will interact with this popular course
            $interactionUserCount = rand(30, 70);
            
            // Shuffle users and take a subset
            $shuffledUsers = $users->shuffle()->take($interactionUserCount);
            
            foreach ($shuffledUsers as $user) {
                // Check if interaction already exists
                $existingInteraction = Interaction::where('user_id', $user->id)
                    ->where('course_id', $courseId)
                    ->first();
                
                if (!$existingInteraction) {
                    // Higher chance of positive interactions for popular courses
                    $viewed = true; // 100% viewed
                    $explored = rand(0, 100) < 80; // 80% explored
                    $certified = $explored ? (rand(0, 100) < 60) : false; // 60% certified if explored
                    
                    $startTime = now()->subDays(rand(1, 180));
                    $lastEvent = $startTime->copy()->addDays(rand(1, 60));
                    
                    $nevents = rand(5, 150);
                    $ndaysAct = rand(3, 45);
                    $nplayVideo = rand(10, 80);
                    $nchapters = rand(5, 25);
                    $nforumPosts = rand(0, 15);
                    
                    // Higher ratings for popular courses
                    $rating = rand(0, 100) < 90 ? rand(35, 50) / 10 : rand(25, 50) / 10; // 90% chance of 3.5-5.0 rating
                    
                    Interaction::create([
                        'user_id' => $user->id,
                        'course_id' => $courseId,
                        'rating' => $rating,
                        'viewed' => $viewed,
                        'explored' => $explored,
                        'certified' => $certified,
                        'start_time' => $startTime,
                        'last_event' => $lastEvent,
                        'nevents' => $nevents,
                        'ndays_act' => $ndaysAct,
                        'nplay_video' => $nplayVideo,
                        'nchapters' => $nchapters,
                        'nforum_posts' => $nforumPosts,
                    ]);
                }
            }
        }
    }
}
