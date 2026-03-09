<?php

declare(strict_types=1);

namespace App\Libraries;

use League\Flysystem\Filesystem;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use Aws\S3\S3Client;

/**
 * ImageUploadService — 商品圖片上傳（MinIO/S3）
 *
 * 使用 league/flysystem 統一儲存介面，支援本地 MinIO 與雲端 S3。
 * 上傳前驗證檔案類型、大小，產生唯一存放路徑，回傳 object key。
 */
class ImageUploadService
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB
    private const BUCKET_PREFIX  = 'items';

    private Filesystem $filesystem;
    private string     $bucket;
    private string     $endpoint;

    public function __construct()
    {
        $this->bucket   = (string) env('MINIO_BUCKET', 'inventory');
        $this->endpoint = (string) env('MINIO_ENDPOINT', 'http://minio:9000');

        $client = new S3Client([
            'version'                 => 'latest',
            'region'                  => env('AWS_REGION', 'us-east-1'),
            'endpoint'                => $this->endpoint,
            'use_path_style_endpoint' => true,
            'credentials'             => [
                'key'    => env('MINIO_ROOT_USER', 'minioadmin'),
                'secret' => env('MINIO_ROOT_PASSWORD', 'minioadmin'),
            ],
        ]);

        $adapter          = new AwsS3V3Adapter($client, $this->bucket);
        $this->filesystem = new Filesystem($adapter);
    }

    /**
     * 上傳商品圖片
     *
     * @param  \CodeIgniter\HTTP\Files\UploadedFile $file
     * @param  int $itemId  商品 ID（用於組織路徑）
     * @return array{path: string, url: string, mime_type: string, file_size: int}
     * @throws \InvalidArgumentException  若檔案類型或大小不符規定
     * @throws \RuntimeException          若上傳失敗
     */
    public function upload(\CodeIgniter\HTTP\Files\UploadedFile $file, int $itemId): array
    {
        $this->validate($file);

        $extension = strtolower($file->getClientExtension());
        $objectKey = sprintf(
            '%s/%d/%s.%s',
            self::BUCKET_PREFIX,
            $itemId,
            bin2hex(random_bytes(8)),
            $extension
        );

        $stream = fopen($file->getTempName(), 'rb');

        try {
            $this->filesystem->writeStream($objectKey, $stream, [
                'ContentType' => $file->getMimeType(),
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return [
            'path'      => $objectKey,
            'url'       => $this->buildUrl($objectKey),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ];
    }

    /**
     * 刪除圖片
     */
    public function delete(string $objectKey): void
    {
        if ($this->filesystem->fileExists($objectKey)) {
            $this->filesystem->delete($objectKey);
        }
    }

    /**
     * 驗證上傳圖片
     */
    private function validate(\CodeIgniter\HTTP\Files\UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new \InvalidArgumentException('無效的上傳檔案');
        }

        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw new \InvalidArgumentException(
                '僅支援 JPG、PNG、GIF、WebP 格式，實際類型：' . $file->getMimeType()
            );
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            $maxMb = self::MAX_SIZE_BYTES / (1024 * 1024);
            throw new \InvalidArgumentException("圖片大小不可超過 {$maxMb}MB");
        }
    }

    private function buildUrl(string $objectKey): string
    {
        // 開發環境使用 presigned URL 概念，實際 URL 透過 nginx/CDN 代理
        return rtrim($this->endpoint, '/') . '/' . $this->bucket . '/' . $objectKey;
    }
}
