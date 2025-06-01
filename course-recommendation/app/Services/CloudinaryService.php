<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;

class CloudinaryService
{
    protected $cloudinary;

    public function __construct(Cloudinary $cloudinary)
    {
        $this->cloudinary = $cloudinary;
    }

    /**
     * Upload file to Cloudinary
     *
     * @param UploadedFile $file
     * @param string $folder
     * @param string $resourceType auto|image|video|raw
     * @return string URL của file đã upload
     */
    public function upload(UploadedFile $file, string $folder = 'default', string $resourceType = 'auto')
    {
        $result = $this->cloudinary->uploadApi()->upload(
            $file->getRealPath(),
            [
                'folder' => $folder,
                'resource_type' => $resourceType,
            ]
        );

        return $result;
    }

    /**
     * Upload image to Cloudinary
     *
     * @param UploadedFile $file
     * @param string $folder
     * @return string URL của hình ảnh đã upload
     */
    public function uploadImage(UploadedFile $file, string $folder = 'images'): string
    {
        return $this->upload($file, $folder, 'image');
    }

    /**
     * Upload video to Cloudinary
     *
     * @param UploadedFile $file
     * @param string $folder
     * @return string URL của video đã upload
     */
    public function uploadVideo(UploadedFile $file, string $folder = 'videos'): string
    {
        return $this->upload($file, $folder, 'video');
    }

    /**
     * Extract public ID from Cloudinary URL
     * 
     * @param string $url
     * @return string|null
     */
    public function getPublicIdFromUrl(string $url): ?string
    {
        // Cloudinary URL format: https://res.cloudinary.com/{cloud_name}/image/upload/v{version}/{public_id}.{format}
        $pattern = '/\/v\d+\/([^\.]+)/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Delete file from Cloudinary using URL
     *
     * @param string $url
     * @return bool
     */
    public function deleteByUrl(string $url): bool
    {
        $publicId = $this->getPublicIdFromUrl($url);
        if (!$publicId) {
            return false;
        }

        // Đoán loại resource dựa vào phần mở rộng
        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        $resourceType = in_array($extension, ['mp4', 'mov', 'avi']) ? 'video' : 'image';

        $result = $this->cloudinary->uploadApi()->destroy($publicId, [
            'resource_type' => $resourceType,
        ]);
        return $result['result'] === 'ok';
    }
}
