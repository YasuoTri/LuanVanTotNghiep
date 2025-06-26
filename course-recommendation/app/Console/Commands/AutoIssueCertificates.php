<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Enrollment;
use App\Models\Certificate;
use App\Http\Controllers\CertificateController;
use Illuminate\Support\Facades\App;

class AutoIssueCertificates extends Command
{
    protected $signature = 'certificates:auto-issue';
    protected $description = 'Tự động cấp chứng chỉ cho các learner đã hoàn thành đủ điều kiện';

    public function handle()
{
    $issuedCount = 0;
    
    // ❌ Nên lọc những enrollment chưa có certificate
    $enrollments = Enrollment::whereDoesntHave('certificates')->get();
    
    $controller = App::make(CertificateController::class);
    
    foreach ($enrollments as $enrollment) {
        try {
            $response = $controller->issueCertificate($enrollment->course_id, $enrollment->user_id);
            
            if ($response instanceof \Illuminate\Http\JsonResponse) {
                $data = $response->getData();
                if (isset($data->eligible) && $data->eligible) {
                    $issuedCount++;
                    $this->info("✔️ Cấp chứng chỉ: user_id={$enrollment->user_id}, course_id={$enrollment->course_id}");
                }
            }
        } catch (\Exception $e) {
            $this->error("❌ Lỗi user_id={$enrollment->user_id}: " . $e->getMessage());
        }
    }
    
    $this->info("🎓 Tổng số chứng chỉ đã cấp: $issuedCount");
}

}
