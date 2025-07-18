<?php

namespace App\Http\Controllers;

use League\Csv\Reader;
use League\Csv\Writer;
use Illuminate\Http\Request;

class CsvFilterController extends Controller
{
    public function filterEnglishCourses()
    {
        // Đường dẫn đến file CSV đầu vào và đầu ra
        $inputFile = storage_path('app/public/udemy_coursesReal.csv'); // File CSV gốc
        $outputFile = storage_path('app/public/english_courses.csv'); // File CSV mới

        // Đọc file CSV
        $csv = Reader::createFromPath($inputFile, 'r');
        $csv->setHeaderOffset(0); // Giả sử dòng đầu tiên là tiêu đề

        // Lấy tất cả bản ghi
        $records = $csv->getRecords();

        // Tạo file CSV mới
        $writer = Writer::createFromPath($outputFile, 'w+');
        $writer->insertOne($csv->getHeader()); // Ghi tiêu đề vào file mới

        // Hàm kiểm tra xem tiêu đề có phải tiếng Anh
        $isEnglish = function ($title) {
            // Chỉ cho phép chữ cái Latin, số, dấu cách, và một số ký tự đặc biệt
            return preg_match('/^[\p{Latin}\p{N}\s\.,!?\-\(\)\&]*$/u', $title);
        };

        // Lọc và ghi các khóa học tiếng Anh
        foreach ($records as $record) {
            $title = $record['course_title'];
            if ($isEnglish($title)) {
                $writer->insertOne($record); // Ghi bản ghi vào file mới
            }
        }

        return response()->json([
            'message' => 'File CSV mới đã được tạo tại: ' . $outputFile
        ]);
    }
}