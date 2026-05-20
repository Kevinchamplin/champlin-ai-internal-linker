<?php
/**
 * Plugin container. Registers every hook in a single place.
 *
 * @package Champlin\InternalLinker
 */

declare(strict_types=1);

namespace Champlin\InternalLinker;

use Champlin\InternalLinker\Admin\EditorAssets;
use Champlin\InternalLinker\Admin\IndexerPage;
use Champlin\InternalLinker\Admin\SettingsPage;
use Champlin\InternalLinker\Embeddings\ProviderFactory;
use Champlin\InternalLinker\Engine\AnchorExtractor;
use Champlin\InternalLinker\Engine\SuggestionEngine;
use Champlin\InternalLinker\Indexing\BulkIndexer;
use Champlin\InternalLinker\Indexing\ContentNormalizer;
use Champlin\InternalLinker\Indexing\IndexQueue;
use Champlin\InternalLinker\REST\IndexController;
use Champlin\InternalLinker\REST\SuggestionsController;
use Champlin\InternalLinker\Similarity\CosineCalculator;
use Champlin\InternalLinker\Storage\Schema;
use Champlin\InternalLinker\Storage\SuggestionLog;
use Champlin\InternalLinker\Storage\VectorStore;

final class Plugin
{
    private static ?self $instance = null;

    private VectorStore $vector_store;
    private SuggestionLog $suggestion_log;
    private ContentNormalizer $normalizer;
    private CosineCalculator $cosine;
    private ProviderFactory $provider_factory;
    private SuggestionEngine $suggestion_engine;
    private AnchorExtractor $anchor_extractor;
    private IndexQueue $index_queue;
    private BulkIndexer $bulk_indexer;
    private SettingsPage $settings_page;
    private IndexerPage $indexer_page;
    private EditorAssets $editor_assets;
    private SuggestionsController $suggestions_controller;
    private IndexController $index_controller;

    public static function boot(): void
    {
        if (self::$instance instanceof self) {
            return;
        }
        self::$instance = new self();
        self::$instance->build();
        self::$instance->register();
    }

    public static function instance(): self
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
            self::$instance->build();
        }
        return self::$instance;
    }

    public static function on_activate(): void
    {
        Schema::install();
    }

    public static function on_deactivate(): void
    {
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions('cil_index_post');
            as_unschedule_all_actions('cil_bulk_index_batch');
        }
    }

    private function build(): void
    {
        global $wpdb;

        $this->vector_store      = new VectorStore($wpdb);
        $this->suggestion_log    = new SuggestionLog($wpdb);
        $this->normalizer        = new ContentNormalizer();
        $this->cosine            = new CosineCalculator();
        $this->provider_factory  = new ProviderFactory();
        $this->anchor_extractor  = new AnchorExtractor(
            $this->provider_factory,
            $this->cosine,
            $this->normalizer
        );
        $this->suggestion_engine = new SuggestionEngine(
            $this->vector_store,
            $this->cosine,
            $this->anchor_extractor
        );
        $this->index_queue       = new IndexQueue(
            $this->provider_factory,
            $this->vector_store,
            $this->normalizer
        );
        $this->bulk_indexer      = new BulkIndexer($this->index_queue);
        $this->settings_page     = new SettingsPage();
        $this->indexer_page      = new IndexerPage($this->bulk_indexer);
        $this->editor_assets     = new EditorAssets();
        $this->suggestions_controller = new SuggestionsController(
            $this->suggestion_engine,
            $this->suggestion_log
        );
        $this->index_controller  = new IndexController($this->bulk_indexer);
    }

    private function register(): void
    {
        // Schema migrations on load (covers upgrades from older versions).
        add_action('init', [Schema::class, 'maybe_upgrade'], 0);

        // Indexing pipeline.
        add_action('save_post', [$this->index_queue, 'on_save_post'], 20, 3);
        add_action('cil_index_post', [$this->index_queue, 'run'], 10, 1);
        add_action('cil_bulk_index_batch', [$this->bulk_indexer, 'run_batch'], 10, 1);
        add_action('before_delete_post', [$this->vector_store, 'delete'], 10, 1);

        // Admin UI.
        add_action('admin_menu', [$this->settings_page, 'register']);
        add_action('admin_menu', [$this->indexer_page, 'register']);
        add_action('admin_init', [$this->settings_page, 'register_settings']);

        // Block editor sidebar.
        add_action('enqueue_block_editor_assets', [$this->editor_assets, 'enqueue']);

        // REST routes.
        add_action('rest_api_init', [$this->suggestions_controller, 'register_routes']);
        add_action('rest_api_init', [$this->index_controller, 'register_routes']);

        // i18n.
        add_action('init', [$this, 'load_textdomain']);
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            'champlin-internal-linker',
            false,
            dirname(plugin_basename(CIL_FILE)) . '/languages/'
        );
    }

    public function vector_store(): VectorStore
    {
        return $this->vector_store;
    }

    public function suggestion_log(): SuggestionLog
    {
        return $this->suggestion_log;
    }
}
