<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\Position;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class InventoryStorage
{
    /**
     * Serialize inventory writes so records and photo changes cannot overlap.
     */
    public function synchronized(Closure $operation): mixed
    {
        $lockPath = Storage::disk('soloinventory')->path('.lock');
        File::ensureDirectoryExists(dirname($lockPath));
        $lockHandle = fopen($lockPath, 'c');

        if ($lockHandle === false || ! flock($lockHandle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock storage.');
        }

        try {
            return $operation();
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /** @param array<string, mixed> $data */
    public function save(
        ?InventoryItem $item,
        array $data,
        ?UploadedFile $photo,
        bool $removePhoto,
    ): InventoryItem {
        return $this->synchronized(function () use ($item, $data, $photo, $removePhoto): InventoryItem {
            if ($item !== null) {
                $item = $item->fresh();
                abort_if($item === null, 404);
            } else {
                $item = new InventoryItem;
            }
            $oldPhotoPath = $item->photo_path;
            $newPhotoPath = $photo?->store('photos', 'soloinventory');

            try {
                DB::transaction(function () use ($item, $data, $newPhotoPath, $removePhoto): void {
                    $item->fill($data);
                    if ($newPhotoPath !== null) {
                        $item->photo_path = $newPhotoPath;
                    } elseif ($removePhoto) {
                        $item->photo_path = null;
                    }
                    $item->save();
                    if ($item->position !== null) {
                        Position::firstOrCreate([
                            'section_id' => $item->section_id,
                            'name' => $item->position,
                        ]);
                    }
                });
            } catch (Throwable $exception) {
                if ($newPhotoPath !== null) {
                    Storage::disk('soloinventory')->delete($newPhotoPath);
                }
                throw $exception;
            }

            if ($oldPhotoPath !== null && $oldPhotoPath !== $item->photo_path) {
                Storage::disk('soloinventory')->delete($oldPhotoPath);
            }

            return $item->load(['section', 'category']);
        });
    }

    public function delete(InventoryItem $item): void
    {
        $this->synchronized(function () use ($item): void {
            $item->refresh();
            $photo = $item->photo_path;
            $item->delete();

            if ($photo !== null) {
                Storage::disk('soloinventory')->delete($photo);
            }
        });
    }
}
