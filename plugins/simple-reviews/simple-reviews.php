<?php
/**
 * Plugin Name: Simple Reviews
 * Description: A simple WordPress plugin that registers a custom post type for product reviews and provides REST API support.
 * Version: 1.0.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_Reviews {
    public function __construct() {
        add_action('init', [$this, 'register_product_review_cpt']);
        // REgister Product Reviews Shortcode
        add_shortcode('product_reviews', [$this, 'display_product_reviews']);
        // Register REST API routes
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        // Register sentiment Metabox
        add_action('add_meta_boxes', [$this, 'add_sentiment_metabox']);
        // Register sentiment score Metabox
        add_action('add_meta_boxes', [$this, 'add_sentiment_score_metabox']);
        // Save Metabox data
        add_action('save_post', [$this, 'save_review_metabox']);        
    }

    /**
     * Register the custom post type for product reviews.
     */
    public function register_product_review_cpt() {
        register_post_type('product_review', [
            'labels'      => [
                'name'          => 'Product Reviews',
                'singular_name' => 'Product Review'
            ],
            'public'      => true,
            'supports'    => ['title', 'editor', 'custom-fields'],
            'show_in_rest' => true,
        ]);
    }

    /**
     * Register REST API routes for sentiment analysis and review history.
     */
    public function register_rest_routes() {
        // Register the sentiment analysis endpoint
        register_rest_route('mock-api/v1', '/sentiment/', [
            'methods'  => 'POST',
            'callback' => [$this, 'analyze_sentiment'],
            'permission_callback' => '__return_true',
        ]);
        // Register the review history endpoint
        register_rest_route('mock-api/v1', '/review-history/', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_review_history'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Add a metabox for sentiment analysis.
     */
    public function add_sentiment_metabox() {
        add_meta_box(
            'review_sentiment',
            'Review Sentiment',
            [$this, 'render_sentiment_metabox'],
            'product_review',
            'side',
            'high'
        );
    }

    /**
     * Add a metabox for sentiment score.
     */
    public function add_sentiment_score_metabox() {
        add_meta_box(
            'sentiment_analysis',
            'Sentiment Analysis',
            [$this, 'render_sentiment_score_metabox'],
            'product_review',
            'side',
            'high'
        );
    }

    /**
     * Render the sentiment metabox.
     */
    public function render_sentiment_score_metabox() {
        wp_nonce_field('sentiment_nonce_action', 'sentiment_nonce');
        $current_score = get_post_meta(get_the_ID(), 'sentiment_score', true);
        ?>
        <label>Sentiment Score</label>
        <select name="sentiment_score" id="sentiment_score">
            <option value="0.1" <?php selected($current_score, 0.1); ?>>1</option>
            <option value="0.2" <?php selected($current_score, 0.2); ?>>2</option>
            <option value="0.3" <?php selected($current_score, 0.3); ?>>3</option>
            <option value="0.4" <?php selected($current_score, 0.4); ?>>4</option>
            <option value="0.5" <?php selected($current_score, 0.5); ?>>5</option>
            <option value="0.6" <?php selected($current_score, 0.6); ?>>6</option>
            <option value="0.7" <?php selected($current_score, 0.7); ?>>7</option>
            <option value="0.8" <?php selected($current_score, 0.8); ?>>8</option>
            <option value="0.9" <?php selected($current_score, 0.9); ?>>9</option>
        <?php    
    }

    /**
     * Render the sentiment metabox.
     */
    public function render_sentiment_metabox() {
        wp_nonce_field('sentiment_nonce_action', 'sentiment_nonce');
        ?>
        <label>Sentiment</label>
        <input type="text" name="sentiment" id="sentiment" value="<?php echo esc_attr(get_post_meta(get_the_ID(), 'sentiment', true)); ?>" />
        <?php    
    }

    /**
     * Save the sentiment and sentiment score when the post is saved.
     */
    public function save_review_metabox($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (isset($_POST['sentiment'])) {
            update_post_meta($post_id, 'sentiment', sanitize_text_field($_POST['sentiment']));
        }
        if (isset($_POST['sentiment_score'])) {
            update_post_meta($post_id, 'sentiment_score', floatval($_POST['sentiment_score']));
        }
    }

    /**
     * Analyze sentiment of the provided text.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error The response or error object.
     */

     public function analyze_sentiment($request) {
        if (!($request instanceof WP_REST_Request)) {
            return new WP_Error('invalid_request', 'This function must be called via a REST API request.', ['status' => 400]);
        }
    
        $params = $request->get_json_params();
        $text = isset($params['text']) ? sanitize_text_field($params['text']) : '';
        $review_score = isset($params['review_score']) ? floatval($params['review_score']) : 0.5;
    
        if (empty($text)) {
            return new WP_Error('empty_text', 'No text provided for analysis.', ['status' => 400]);
        }
    
        $sentiment_scores = ['positive' => 0.9, 'negative' => 0.2, 'neutral' => 0.5];
        $sentiment = $review_score > 0.5 ? 'positive' : ($review_score < 0.5 ? 'negative' : 'neutral');
        return rest_ensure_response(['sentiment' => $sentiment, 'score' => $review_score]);
    }

    /**
     * Get the review history.
     *
     * @return WP_REST_Response The response object.
     */

    public function get_review_history() {
        $reviews = get_posts([
            'post_type'      => 'product_review',
            'posts_per_page' => 5,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
        
        $response = [];
        foreach ($reviews as $review) {
            $response[] = [
                'id'       => $review->ID,
                'title'    => $review->post_title,
                'sentiment'=> get_post_meta($review->ID, 'sentiment', true) ?? 'neutral',
                'score'    => get_post_meta($review->ID, 'sentiment_score', true) ?? 0.5,
            ];
        }

        return rest_ensure_response($response);
    }

    /**
     * Get the review history.
     *
     * @return WP_REST_Response The response object.
     */

    public function display_product_reviews() {
        $reviews = get_posts([
            'post_type'      => 'product_review',
            'posts_per_page' => 5,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $output = '<style>
            .review-positive { color: green; font-weight: bold; }
            .review-negative { color: red; font-weight: bold; }
        </style>';

        $output .= '<ul>';
        // Loop through the reviews and display
        foreach ($reviews as $review) {
            $sentiment = get_post_meta($review->ID, 'sentiment', true) ?? 'neutral';
            $class = ($sentiment === 'positive') ? 'review-positive' : (($sentiment === 'negative') ? 'review-negative' : '');
            $output .= "<li class='$class'>{$review->post_title} (Sentiment: $sentiment)</li>";
        }
        $output .= '</ul>';

        return $output;
    }
}

// Initialize the plugin
new Simple_Reviews();