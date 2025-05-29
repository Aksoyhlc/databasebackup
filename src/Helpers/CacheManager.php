<?php

namespace Aksoyhlc\Databasebackup\Helpers;

/**
 * CacheManager - Cache management for database backup operations
 *
 * This class manages cache data used in database backup operations.
 * It performs basic caching operations using a JSON file
 * and provides timeout control.
 *
 * @package Aksoyhlc\Databasebackup\Helpers
 */
class CacheManager
{
    /**
     * Cache file path
     * @var string
     */
    private string $cacheFilePath;
    
    /**
     * Cache duration (seconds)
     * @var int
     */
    private int $cacheTime;
    
    /**
     * Initializes the CacheManager class
     *
     * @param string $cacheFilePath Path to the cache file
     * @param int $cacheTime Cache duration (in seconds)
     */
    public function __construct(string $cacheFilePath, int $cacheTime = 3600)
    {
        $this->cacheFilePath = $cacheFilePath;
        $this->cacheTime = $cacheTime;
    }
    
    /**
     * Gets the cache data for the specified key
     *
     * @param string $key Cache key
     * @return mixed|null Cache data or null (if data doesn't exist or has expired)
     */
    public function get(string $key)
    {
        if (!file_exists($this->cacheFilePath)) {
            return null;
        }
        
        $cacheData = $this->readCacheFile();
        
        if (isset($cacheData[$key]) && (time() - $cacheData[$key]['timestamp']) < $this->cacheTime) {
            return $cacheData[$key]['data'];
        }
        
        return null;
    }
    
    /**
     * Saves data to cache for the specified key
     *
     * @param string $key Cache key
     * @param mixed $data Data to be saved
     * @return bool Is operation successful?
     */
    public function set(string $key, $data): bool
    {
        $cacheData = [];
        
        if (file_exists($this->cacheFilePath)) {
            $cacheData = $this->readCacheFile();
        }
        
        $cacheData[$key] = [
            'timestamp' => time(),
            'data' => $data
        ];
        
        return $this->writeCacheFile($cacheData);
    }
    
    /**
     * Deletes cache data for the specified key
     *
     * @param string $key Cache key
     * @return bool Is operation successful?
     */
    public function forget(string $key): bool
    {
        if (!file_exists($this->cacheFilePath)) {
            return true;
        }
        
        $cacheData = $this->readCacheFile();
        
        if (isset($cacheData[$key])) {
            unset($cacheData[$key]);
            return $this->writeCacheFile($cacheData);
        }
        
        return true;
    }
    
    /**
     * Clears all cache
     *
     * @return bool Is operation successful?
     */
    public function clear(): bool
    {
        if (file_exists($this->cacheFilePath)) {
            return $this->writeCacheFile([]);
        }
        
        return true;
    }
    
    /**
     * Reads the cache file
     *
     * @return array Cache data
     */
    private function readCacheFile(): array
    {
        $content = file_get_contents($this->cacheFilePath);
        return json_decode($content, true) ?: [];
    }
    
    /**
     * Writes data to cache file
     *
     * @param array $data Data to be written
     * @return bool Is operation successful?
     */
    private function writeCacheFile(array $data): bool
    {
        return file_put_contents($this->cacheFilePath, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Cleans expired cache data
     *
     * @return bool Is operation successful?
     */
    public function gc(): bool
    {
        if (!file_exists($this->cacheFilePath)) {
            return true;
        }
        
        $cacheData = $this->readCacheFile();
        $now = time();
        $modified = false;
        
        foreach ($cacheData as $key => $value) {
            if (($now - $value['timestamp']) >= $this->cacheTime) {
                unset($cacheData[$key]);
                $modified = true;
            }
        }
        
        if ($modified) {
            return $this->writeCacheFile($cacheData);
        }
        
        return true;
    }
    
    /**
     * Checks if the cache file exists
     *
     * @return bool Does the cache file exist?
     */
    public function exists(): bool
    {
        return file_exists($this->cacheFilePath);
    }
}