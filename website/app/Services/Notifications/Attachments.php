<?php
namespace App\Services\Notifications;

use Illuminate\Support\Facades\{Storage, Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Attachments
{
    public const MAX_FILES = 5;
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const EXTENSIONS = 'pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx';

    public function update(array $existing, array $uploads, array $remove, array &$created): array
    {
        Validator::make(['attachments'=>$uploads,'remove_attachments'=>$remove], [
            'attachments'=>'array|max:'.self::MAX_FILES,
            'attachments.*'=>['required','file','max:10240','mimes:'.self::EXTENSIONS,'extensions:'.self::EXTENSIONS],
            'remove_attachments'=>'array|max:'.self::MAX_FILES,
            'remove_attachments.*'=>'required|uuid|distinct',
        ], ['attachments.max'=>'Choose up to five files.',
            'attachments.*.max'=>'Each attachment must be 10 MB or smaller.',
            'attachments.*.mimes'=>'Use PDF, JPG, PNG, WebP, Word or Excel files.',
            'attachments.*.extensions'=>'Use PDF, JPG, PNG, WebP, Word or Excel files.'])->validate();
        if (array_diff($remove, array_column($existing,'id'))) {
            throw ValidationException::withMessages(['attachments'=>'The saved attachments changed. Reload the draft before removing files.']);
        }
        $files = array_values(array_filter($existing, fn($file)=>!in_array($file['id'],$remove,true)));
        if (count($files) + count($uploads) > self::MAX_FILES) {
            throw ValidationException::withMessages(['attachments'=>'A notification can contain up to five attachments. Remove a saved file before adding more.']);
        }
        if (array_sum(array_column($files,'size')) + array_sum(array_map(fn($upload)=>$upload->getSize(),$uploads)) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['attachments'=>'The combined attachment size must be 10 MB or smaller, including saved files.']);
        }
        foreach ($uploads as $upload) {
            if ($upload->getSize() < 1) throw ValidationException::withMessages(['attachments'=>'Empty files cannot be attached.']);
            $id = (string) Str::uuid();
            $extension = strtolower($upload->getClientOriginalExtension());
            $original = basename(str_replace('\\','/',$upload->getClientOriginalName()));
            $stem = pathinfo($original, PATHINFO_FILENAME);
            $stem = trim(preg_replace('/[\x00-\x1F\x7F<>:"|?*]/u','_', $stem) ?? 'Attachment', '. ');
            $name = mb_substr($stem ?: 'Attachment',0,150).'.'.$extension;
            $path = 'notification-attachments/'.$id.'.'.$extension;
            // New immutable objects only. Never overwrite a file referenced by a tested version.
            $created[] = $path;
            if (!Storage::disk('local')->putFileAs('notification-attachments',$upload,$id.'.'.$extension,['visibility'=>'private'])) {
                throw ValidationException::withMessages(['attachments'=>'An attachment could not be saved. Please try again.']);
            }
            // Production uses a shared private directory for PHP-FPM and the mmcdeploy scheduler.
            // Flysystem's private files default to 0600, which would block the other process user.
            if (!chmod(Storage::disk('local')->path($path),0660)) {
                throw ValidationException::withMessages(['attachments'=>'An attachment could not be prepared for the email queue. Please try again.']);
            }
            $files[] = ['id'=>$id,'path'=>$path,'name'=>$name,'mime'=>$upload->getMimeType() ?: 'application/octet-stream',
                'size'=>$upload->getSize(),'sha256'=>hash_file('sha256',$upload->getRealPath())];
        }
        return $files;
    }

    public function read(array $file): string
    {
        $path = $file['path'] ?? '';
        $size = $file['size'] ?? 0;
        if (!preg_match('~^notification-attachments/[a-f0-9-]{36}\.(?:'.str_replace(',','|',self::EXTENSIONS).')$~D',$path)
            || !is_int($size) || $size < 1 || $size > self::MAX_BYTES) throw new AttachmentUnavailable;
        try {
            $disk = Storage::disk('local');
            if (!$disk->exists($path) || $disk->size($path) !== $size) throw new AttachmentUnavailable;
            $bytes = $disk->get($path);
            if (!is_string($bytes) || strlen($bytes)!==$size || !hash_equals($file['sha256']??'',hash('sha256',$bytes))) throw new AttachmentUnavailable;
            return $bytes;
        } catch (\Throwable $e) {
            throw new AttachmentUnavailable;
        }
    }

    public function verify(array $files): void
    {
        if (count($files)>self::MAX_FILES || array_sum(array_column($files,'size'))>self::MAX_BYTES) throw new AttachmentUnavailable;
        foreach ($files as $file) $this->read($file);
    }

    public function assertReady(array $files): void
    {
        try { $this->verify($files); }
        catch (AttachmentUnavailable $e) { throw ValidationException::withMessages(['attachments'=>$e->getMessage()]); }
    }

    public function discardUncommitted(array $paths): void
    {
        // Only objects created by this failed save; retained files and historical versions are untouched.
        foreach ($paths as $path) Storage::disk('local')->delete($path);
    }
}
