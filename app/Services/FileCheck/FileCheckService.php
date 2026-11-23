<?php

namespace App\Services\FileCheck;

use App\Enums\FileCheckStatus;
use App\Exceptions\FileCheckDeletionException;
use App\Exceptions\FileCheckProcessingException;
use App\Models\FileCheck;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\FilesystemException;

class FileCheckService
{
    public const MAX_SIZE_KILOBYTES = 10240;

    public const RETENTION_DAYS = 30;

    public function create(User $user, UploadedFile $upload): FileCheck
    {
        $storageKey = $user->getKey().'/'.Str::uuid().'.bin';
        $fileCheck = $user->fileChecks()->create([
            'storage_key' => $storageKey,
            'original_name' => $this->displayName($upload->getClientOriginalName()),
            'size_bytes' => $upload->getSize(),
            'mime_type' => $upload->getMimeType() ?: 'application/octet-stream',
            'status' => FileCheckStatus::Processing,
        ]);
        $disk = Storage::disk('file-checks');

        try {
            $storedKey = $upload->storeAs(
                dirname($storageKey),
                basename($storageKey),
                'file-checks',
            );

            if ($storedKey !== $storageKey) {
                throw new FileCheckProcessingException('The private upload could not be stored.');
            }

            $sha256 = hash_file('sha256', $disk->path($storageKey));

            if (! is_string($sha256)) {
                throw new FileCheckProcessingException('The uploaded file could not be hashed.');
            }

            $fileCheck->forceFill([
                'sha256' => $sha256,
                'status' => FileCheckStatus::Completed,
                'processed_at' => now(),
            ])->save();
        } catch (FilesystemException|FileCheckProcessingException $exception) {
            $fileCheck->forceFill([
                'status' => FileCheckStatus::Failed,
                'failure_code' => 'processing_failed',
                'processed_at' => now(),
            ])->save();

            $this->removeIncompleteUpload($disk, $storageKey, $fileCheck);
            Log::error('File check processing failed.', [
                'file_check_id' => $fileCheck->getKey(),
                'exception_class' => $exception::class,
            ]);

            throw new FileCheckProcessingException(
                'The file could not be processed. The failed attempt has been recorded.',
                previous: $exception,
            );
        }

        return $fileCheck->refresh();
    }

    public function delete(FileCheck $fileCheck): void
    {
        $disk = Storage::disk('file-checks');

        try {
            if ($disk->exists($fileCheck->storage_key) && ! $disk->delete($fileCheck->storage_key)) {
                throw new FileCheckDeletionException('The private upload could not be deleted.');
            }
        } catch (FilesystemException|FileCheckDeletionException $exception) {
            Log::error('Private file-check object deletion failed.', [
                'file_check_id' => $fileCheck->getKey(),
                'exception_class' => $exception::class,
            ]);

            throw new FileCheckDeletionException(
                'The file could not be deleted. Please try again later.',
                previous: $exception,
            );
        }

        $fileCheck->delete();
    }

    private function displayName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';
        $name = trim(mb_substr($name, 0, 255));

        return $name === '' ? 'uploaded-file' : $name;
    }

    private function removeIncompleteUpload(
        FilesystemAdapter $disk,
        string $storageKey,
        FileCheck $fileCheck,
    ): void
    {
        try {
            if ($disk->exists($storageKey) && ! $disk->delete($storageKey)) {
                Log::error('Incomplete private file-check object could not be removed.', [
                    'file_check_id' => $fileCheck->getKey(),
                    'failure_code' => 'incomplete_object_cleanup_failed',
                ]);
            }
        } catch (FilesystemException $exception) {
            Log::error('Incomplete private file-check object cleanup failed.', [
                'file_check_id' => $fileCheck->getKey(),
                'exception_class' => $exception::class,
            ]);
        }
    }
}
