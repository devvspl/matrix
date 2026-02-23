<?php

namespace App\Services;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Illuminate\Http\UploadedFile;

class S3UploadService
{
    protected $s3Client;
    protected $bucket;
    protected $region;

    public function __construct()
    {
        $this->bucket = env('AWS_BUCKET');
        $this->region = env('AWS_DEFAULT_REGION');
        
        $awsConfig = [
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
        ];
        
        $this->s3Client = new S3Client($awsConfig);
    }

    public function uploadFile(UploadedFile $file, string $fileName, string $uploadPath)
    {
        $key = rtrim($uploadPath, '/') . '/' . $fileName;
        $contentType = $file->getMimeType() ?: $file->getClientMimeType();

        try {
            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'SourceFile' => $file->getRealPath(),
                'ContentType' => $contentType,
            ]);

            $pathStyleUrl = "https://s3.{$this->region}.amazonaws.com/{$this->bucket}/{$key}";

            return [
                'success' => true,
                'url' => $pathStyleUrl,
                'key' => $key,
            ];
        } catch (AwsException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}

