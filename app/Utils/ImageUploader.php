<?php

namespace App\Utils;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploader
{
    /**
     * Guarda una imagen y retorna la URL pública
     * 
     * @param UploadedFile $image Archivo de imagen
     * @param string $folder Carpeta base (ej: 'profiles', 'service-requests')
     * @param string|null $subfolder Subcarpeta opcional (ej: 'user-123')
     * @param string $disk Disco de almacenamiento (default: public)
     * @return string URL pública de la imagen
     */
    public static function store(
        UploadedFile $image, 
        string $folder, 
        ?string $subfolder = null, 
        string $disk = 'public'
    ): string {
        $path = self::buildPath($folder, $subfolder);
        $filename = self::generateFilename($image);
        
        $fullPath = $image->storeAs($path, $filename, $disk);
        
        return $fullPath;
    }

    /**
     * Guarda múltiples imágenes
     * 
     * @param array|UploadedFile[] $images
     * @param string $folder
     * @param string|null $subfolder
     * @param string $disk
     * @return array|string[] URLs públicas
     */
    public static function storeMultiple(
        array $images, 
        string $folder, 
        ?string $subfolder = null, 
        string $disk = 'public'
    ): array {
        $urls = [];
        
        foreach ($images as $image) {
            if ($image instanceof UploadedFile) {
                $urls[] = self::store($image, $folder, $subfolder, $disk);
            }
        }
        
        return $urls;
    }

    /**
     * Elimina una imagen por su URL
     * 
     * @param string $url URL pública de la imagen
     * @param string $disk
     * @return bool
     */
    public static function delete(string $url, string $disk = 'public'): bool
    {
        $path = filter_var($url, FILTER_VALIDATE_URL) ? self::urlToPath($url, $disk) : $url;

        if (Storage::disk($disk)->exists($path)) {
            return Storage::disk($disk)->delete($path);
        }

        return false;
    }

    /**
     * Elimina múltiples imágenes
     * 
     * @param array|string[] $urls
     * @param string $disk
     */
    public static function deleteMultiple(array $urls, string $disk = 'public'): void
    {
        foreach ($urls as $url) {
            self::delete($url, $disk);
        }
    }

    /**
     * Actualiza una imagen (elimina la anterior y guarda la nueva)
     * 
     * @param string|null $oldUrl URL de la imagen anterior
     * @param UploadedFile $newImage Nueva imagen
     * @param string $folder
     * @param string|null $subfolder
     * @param string $disk
     * @return string Nueva URL
     */
    public static function update(
        ?string $oldUrl, 
        UploadedFile $newImage, 
        string $folder, 
        ?string $subfolder = null, 
        string $disk = 'public'
    ): string {
        // Elimina la imagen anterior si existe
        if ($oldUrl) {
            self::delete($oldUrl, $disk);
        }
        
        // Guarda la nueva
        return self::store($newImage, $folder, $subfolder, $disk);
    }

    /**
     * Construye el path de almacenamiento
     */
    protected static function buildPath(string $folder, ?string $subfolder): string
    {
        $parts = [$folder];
        
        if ($subfolder) {
            $parts[] = $subfolder;
        }
        
        // Organiza por fecha: profiles/user-123/2024/02/27/
        $parts[] = now()->format('Y/m/d');
        
        return implode('/', array_filter($parts));
    }

    /**
     * Genera nombre de archivo único
     */
    protected static function generateFilename(UploadedFile $image): string
    {
        $extension = $image->getClientOriginalExtension();
        $name = Str::slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME));
        $uniqueId = Str::random(8);
        
        return "{$name}-{$uniqueId}.{$extension}";
    }

    /**
     * Convierte URL pública a path relativo
     */
    protected static function urlToPath(string $url, string $disk): string
    {
        $baseUrl = Storage::disk($disk)->url('');
        return str_replace($baseUrl, '', $url);
    }
}