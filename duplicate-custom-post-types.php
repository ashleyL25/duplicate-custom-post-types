<?php
/**
Plugin Name: Duplicate Custom Post Types
Plugin URI: https://github.com/ashleyL25/duplicate-custom-post-types.git
Description: A description of your add-on plugin.
Version: 1.0.0
Author: BlueFrog DM 
Author URI: https://www.bluefrogdm.com/
GitHub Plugin URI: ashleyL25/duplicate-custom-post-types
GitHub Branch: main
Text Domain: duplicate-cpt
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Duplicate_Custom_Post_Types {

    /**
     * Initialize the plugin
     */
    public function __construct() {
        // Add duplicate link to post row actions
        add_filter('post_row_actions', array($this, 'add_duplicate_link'), 10, 2);
        add_filter('page_row_actions', array($this, 'add_duplicate_link'), 10, 2);
        
        // Handle custom post types
        add_action('admin_init', array($this, 'add_duplicate_link_to_cpt'));
        
        // Admin action to duplicate post
        add_action('admin_action_duplicate_post', array($this, 'duplicate_post_action'));
        
        // Add admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    /**
     * Add duplicate links to all registered custom post types
     */
    public function add_duplicate_link_to_cpt() {
        // Get all registered custom post types
        $custom_post_types = get_post_types(array(
            'public'   => true,
            '_builtin' => false
        ), 'names');
        
        // Add the duplicate link to each custom post type
        foreach ($custom_post_types as $cpt) {
            add_filter($cpt . '_row_actions', array($this, 'add_duplicate_link'), 10, 2);
        }
    }
    
    /**
     * Add duplicate link to post row actions
     */
    public function add_duplicate_link($actions, $post) {
        // Check if current user can edit this post
        if (current_user_can('edit_posts')) {
            // Create the duplicate link
            $duplicate_url = admin_url('admin.php?action=duplicate_post&post=' . $post->ID . '&nonce=' . wp_create_nonce('duplicate-post_' . $post->ID));
            
            // Add the duplicate link to actions
            $actions['duplicate'] = '<a href="' . esc_url($duplicate_url) . '" title="' . __('Duplicate this item', 'duplicate-cpt') . '">' . __('Duplicate', 'duplicate-cpt') . '</a>';
        }
        return $actions;
    }
    
    /**
     * Handle the duplicate post action
     */
    public function duplicate_post_action() {
        // Security check
        if (!isset($_GET['post']) || !isset($_GET['nonce'])) {
            wp_die(__('Security check failed', 'duplicate-cpt'));
        }
        
        $post_id = absint($_GET['post']);
        $nonce = sanitize_text_field($_GET['nonce']);
        
        // Verify nonce
        if (!wp_verify_nonce($nonce, 'duplicate-post_' . $post_id)) {
            wp_die(__('Security check failed', 'duplicate-cpt'));
        }
        
        // Check if current user can edit this post
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to duplicate this post', 'duplicate-cpt'));
        }
        
        // Get the original post
        $post = get_post($post_id);
        if (!$post) {
            wp_die(__('Post not found', 'duplicate-cpt'));
        }
        
        // Create duplicate post
        $new_post_id = $this->duplicate_post($post);
        
        if ($new_post_id) {
            // Duplicate post meta
            $this->duplicate_post_meta($post_id, $new_post_id);
            
            // Duplicate taxonomies
            $this->duplicate_taxonomies($post_id, $new_post_id, $post->post_type);
            
            // Set success message
            set_transient('duplicate_post_success', true, 60);
            
            // Redirect to the edit screen of the new post
            wp_redirect(admin_url('post.php?action=edit&post=' . $new_post_id));
            exit;
        } else {
            wp_die(__('Duplication failed', 'duplicate-cpt'));
        }
    }
    
    /**
     * Create a duplicate post
     */
    private function duplicate_post($post) {
        // Create new post data array
        $new_post = array(
            'post_author'    => $post->post_author,
            'post_content'   => $post->post_content,
            'post_title'     => $post->post_title . ' ' . __('(Copy)', 'duplicate-cpt'),
            'post_excerpt'   => $post->post_excerpt,
            'post_status'    => 'draft',
            'post_type'      => $post->post_type,
            'post_parent'    => $post->post_parent,
            'menu_order'     => $post->menu_order,
            'comment_status' => $post->comment_status,
            'ping_status'    => $post->ping_status
        );
        
        // Insert the new post
        $new_post_id = wp_insert_post($new_post);
        
        return $new_post_id;
    }
    
    /**
     * Duplicate post meta
     */
    private function duplicate_post_meta($old_post_id, $new_post_id) {
        // Get all post meta
        $post_meta = get_post_meta($old_post_id);
        
        if (!empty($post_meta)) {
            foreach ($post_meta as $meta_key => $meta_values) {
                foreach ($meta_values as $meta_value) {
                    // Skip certain meta keys that shouldn't be duplicated
                    if (in_array($meta_key, array('_wp_old_slug', '_edit_lock', '_edit_last'))) {
                        continue;
                    }
                    
                    // Add the meta to the new post
                    add_post_meta($new_post_id, $meta_key, maybe_unserialize($meta_value));
                }
            }
        }
    }
    
    /**
     * Duplicate taxonomies (categories, tags, etc.)
     */
    private function duplicate_taxonomies($old_post_id, $new_post_id, $post_type) {
        // Get all taxonomies for the post type
        $taxonomies = get_object_taxonomies($post_type);
        
        if (!empty($taxonomies)) {
            foreach ($taxonomies as $taxonomy) {
                // Get all terms for the taxonomy
                $terms = wp_get_object_terms($old_post_id, $taxonomy, array('fields' => 'slugs'));
                
                if (!empty($terms) && !is_wp_error($terms)) {
                    // Set terms to the new post
                    wp_set_object_terms($new_post_id, $terms, $taxonomy);
                }
            }
        }
    }
    
    /**
     * Display admin notices
     */
    public function admin_notices() {
        if (get_transient('duplicate_post_success')) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Post duplicated successfully.', 'duplicate-cpt') . '</p></div>';
            delete_transient('duplicate_post_success');
        }
    }
}

// Initialize the plugin
new Duplicate_Custom_Post_Types();