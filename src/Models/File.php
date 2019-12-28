<?php

namespace Sebastienheyd\BoilerplateMediaManager\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\File as BaseFile;
use Intervention\Image\Facades\Image;
use Storage;

class File extends BaseFile
{
    private $file;
    private $storage;
    private $pathinfo;
    private $path;

    /**
     * File constructor.
     *
     * @param string $file
     */
    public function __construct($file)
    {
        $this->file = $file;
        $this->storage = Storage::disk('public');
        $this->pathinfo = pathinfo($this->getFullPath());
        $this->path = rtrim(preg_replace('#'.$this->pathinfo['basename'].'$#', '', $this->file), '/');
    }

    /**
     * Rename current file.
     *
     * @param string $newName
     */
    public function rename($newName)
    {
        foreach ($this->getThumbs() as $thumb) {
            $this->storage->move($thumb['fullpath'], $thumb['dirname'].'/'.$newName);
        }

        $this->storage->move($this->file, $this->path.'/'.$newName);

        if (is_file($this->getFullPath($this->getThumbPath()))) {
            $this->storage->move($this->getThumbPath(), $this->path.'/thumb_'.$newName);
        }
    }

    /**
     * Delete current file.
     */
    public function delete()
    {
        foreach ($this->getThumbs() as $thumb) {
            $this->storage->delete($thumb['fullpath']);
        }

        if (is_file($this->getFullPath($this->getThumbPath()))) {
            $this->storage->delete($this->getThumbPath());
        }

        $this->storage->delete($this->file);
    }

    /**
     * Move current file.
     *
     * @param string $destinationPath
     */
    public function move($destinationPath)
    {
        $dest = rtrim($destinationPath, '/');

        foreach ($this->getThumbs() as $thumb) {
            $this->storage->move($thumb['fullpath'], 'thumbs'.$dest.'/'.$thumb['path'].'/'.$thumb['basename']);
        }

        $this->storage->move($this->file, $dest.'/'.$this->pathinfo['basename']);

        if (is_file($this->getFullPath($this->getThumbPath()))) {
            $this->storage->move($this->getThumbPath(), $dest.'/thumb_'.$this->pathinfo['basename']);
        }
    }

    /**
     * Get list of thumbs in thumbs folder.
     *
     * @return array
     */
    private function getThumbs()
    {
        $result = [];

        $path = rtrim($this->path, '/');

        foreach (['fit', 'resize'] as $type) {
            if ($this->storage->exists('thumbs'.$path.'/'.$type)) {
                foreach ($this->storage->allFiles('thumbs'.$path.'/'.$type) as $file) {
                    if (preg_match('#thumbs/.*?'.$type.'/(.*?)/'.$this->pathinfo['basename'].'$#', $file, $m)) {
                        $info = pathinfo($file);
                        $info['fullpath'] = $file;
                        $info['path'] = $type.'/'.$m[1];
                        $result[] = $info;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Get full path for a given relative path.
     *
     * @param string $file
     *
     * @return mixed
     */
    public function getFullPath($file = '')
    {
        return $this->storage->getDriver()->getAdapter()->applyPathPrefix($file === '' ? $this->file : $file);
    }

    /**
     * Get thumb path.
     *
     * @return string
     */
    public function getThumbPath()
    {
        return $this->path.'/thumb_'.$this->pathinfo['basename'];
    }

    /**
     * Automatically generate thumb for image files if not exists.
     */
    public function generateThumb()
    {
        if (preg_match('#^thumb_#', $this->pathinfo['basename'])) {
            return;
        }

        $ext = ['jpg', 'jpeg', 'gif', 'png', 'bmp', 'tif'];
        $destFile = $this->getFullPath($this->getThumbPath());

        if (in_array(strtolower($this->pathinfo['extension'] ?? ''), $ext) && !is_file($destFile)) {
            Image::make($this->getFullPath())->fit(150)->save($destFile, 75);
        }
    }

    /**
     * Return date as array.
     *
     * @return array
     */
    public function toArray()
    {
        $ts = filemtime($this->getFullPath());

        return [
            'download' => '',
            'icon'     => $this->getIcon(),
            'type'     => $this->detectFileType(),
            'name'     => basename($this->file),
            'isDir'    => false,
            'size'     => $this->getFilesize(),
            'link'     => route('mediamanager.index', ['path' => $this->file], false),
            'url'      => $this->storage->url($this->file).'?'.$ts,
            'time'     => $this->getFileChangeTime(),
            'ts'       => $ts,
        ];
    }

    /**
     * Get icon for a given file.
     *
     * @return mixed
     */
    public function getIcon()
    {
        $type = $this->detectFileType();
        $icons = config('boilerplate.mediamanager.icons');

        return $icons[$type];
    }

    /**
     * Return type for a given file.
     *
     * @return bool|int|mixed|string
     */
    public function detectFileType()
    {
        $extension = self::extension($this->file);
        foreach (config('boilerplate.mediamanager.filetypes') as $type => $regex) {
            if (preg_match("/^($regex)$/i", $extension) !== 0) {
                return $type;
            }
        }

        return 'file';
    }

    /**
     * Return size for a given file.
     *
     * @return string
     */
    public function getFilesize()
    {
        $bytes = filesize($this->getFullPath());
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Return modification time for a given file.
     *
     * @return false|string
     */
    public function getFileChangeTime()
    {
        return Carbon::createFromTimestamp(filectime($this->getFullPath()))
            ->isoFormat(__('boilerplate::date.YmdHis'));
    }
}
