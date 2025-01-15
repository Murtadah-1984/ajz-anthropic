<?php

namespace Ajz\Anthropic\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\Facades\Config;

class CacheCleanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'anthropic:cache:clean
                          {--tag= : Clean cache entries with specific tag}
                          {--all : Clean all Anthropic cache entries}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean Anthropic cache entries';

    /**
     * The cache manager instance.
     *
     * @var CacheManager
     */
    protected CacheManager $cache;

    /**
     * Create a new command instance.
     *
     * @param CacheManager $cache
     */
    public function __construct(CacheManager $cache)
    {
        parent::__construct();
        $this->cache = $cache;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $tag = $this->option('tag');
        $all = $this->option('all');
        $prefix = Config::get('anthropic.cache.prefix', 'anthropic:');

        if (!$tag && !$all) {
            $this->error('Please specify either --tag or --all option');
            return 1;
        }

        try {
            if ($all) {
                $this->cleanAllCache($prefix);
            } else {
                $this->cleanTaggedCache($tag);
            }

            $this->info('Cache cleaned successfully');
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to clean cache: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Clean all cache entries with the Anthropic prefix.
     *
     * @param string $prefix
     * @return void
     */
    protected function cleanAllCache(string $prefix): void
    {
        $store = $this->cache->store(Config::get('anthropic.cache.store', 'redis'));

        // For Redis, we can use the prefix to clean only Anthropic-related keys
        if (method_exists($store, 'getRedis')) {
            $redis = $store->getRedis();
            $keys = $redis->keys("{$prefix}*");
            if (!empty($keys)) {
                $redis->del($keys);
            }
        } else {
            // For other drivers, we'll have to clear everything
            $store->flush();
        }

        $this->info('Cleaned all Anthropic cache entries');
    }

    /**
     * Clean cache entries with specific tag.
     *
     * @param string $tag
     * @return void
     */
    protected function cleanTaggedCache(string $tag): void
    {
        $store = $this->cache->store(Config::get('anthropic.cache.store', 'redis'));

        if (!Config::get('anthropic.cache.tags_enabled', true)) {
            $this->warn('Cache tags are not enabled in configuration');
            return;
        }

        if (method_exists($store, 'tags')) {
            $store->tags(["anthropic:{$tag}"])->flush();
            $this->info("Cleaned cache entries tagged with 'anthropic:{$tag}'");
        } else {
            $this->warn('Current cache driver does not support tags');
        }
    }
}
