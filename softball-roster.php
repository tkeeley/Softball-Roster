<?php
/*
Plugin Name: Roster Plugin
Description: A plugin to create and manage rosters for 10U, 12U, and 14U.
Version: 1.0
Author: Cup O Code
*/

// Register Custom Post Type
function create_roster_post_type() {
    register_post_type('roster',
        array(
            'labels'      => array(
                'name'          => __('Rosters'),
                'singular_name' => __('Roster'),
            ),
            'public'      => true,
            'has_archive' => true,
            'supports'    => array('title'),
            'menu_icon'   => plugins_url('softball_icon.png', __FILE__),
        )
    );
}
add_action('init', 'create_roster_post_type');


// Add custom CSS to resize the menu icon
function roster_plugin_admin_styles() {
    echo '<style>
        #adminmenu .menu-icon-roster div.wp-menu-image {
            background-size: 20px 20px !important;
        }
        #adminmenu .menu-icon-roster div.wp-menu-image img {
            width: 20px;
            height: 20px;
        }
    </style>';
}
add_action('admin_head', 'roster_plugin_admin_styles');

// Flush rewrite rules on activation and deactivation
function roster_plugin_activate() {
    create_roster_post_type();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'roster_plugin_activate');

function roster_plugin_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'roster_plugin_deactivate');

// Enqueue jQuery
function enqueue_custom_admin_scripts() {
    wp_enqueue_script('jquery');
}
add_action('admin_enqueue_scripts', 'enqueue_custom_admin_scripts');

// Add Custom Meta Boxes
function add_roster_meta_boxes() {
    add_meta_box('roster_meta_box', 'Player Details', 'roster_meta_box_callback', 'roster', 'normal', 'high');
    add_meta_box('roster_type_meta_box', 'Roster Type', 'roster_type_meta_box_callback', 'roster', 'side', 'default');
}
add_action('add_meta_boxes', 'add_roster_meta_boxes');

function roster_meta_box_callback($post) {
    $number = get_post_meta($post->ID, 'roster_number', true);
    $name = get_post_meta($post->ID, 'roster_name', true);
    $position = get_post_meta($post->ID, 'roster_position', true);
    $bats = get_post_meta($post->ID, 'roster_bats', true);
    $throws = get_post_meta($post->ID, 'roster_throws', true);
    $school_district = get_post_meta($post->ID, 'roster_school_district', true);
    wp_nonce_field('roster_meta_box_nonce', 'meta_box_nonce');
    ?>
    <p>
        <label for="roster_number">Number</label>
        <input type="text" name="roster_number" id="roster_number" value="<?php echo esc_attr($number); ?>" />
    </p>
    <p>
        <label for="roster_name">Name</label>
        <input type="text" name="roster_name" id="roster_name" value="<?php echo esc_attr($name); ?>" />
    </p>
    <p>
        <label for="roster_position">Position</label>
        <input type="text" name="roster_position" id="roster_position" value="<?php echo esc_attr($position); ?>" />
    </p>
    <p>
        <label for="roster_bats">Bats</label>
        <input type="text" name="roster_bats" id="roster_bats" value="<?php echo esc_attr($bats); ?>" />
    </p>
    <p>
        <label for="roster_throws">Throws</label>
        <input type="text" name="roster_throws" id="roster_throws" value="<?php echo esc_attr($throws); ?>" />
    </p>
    <p>
        <label for="roster_school_district">School District</label>
        <input type="text" name="roster_school_district" id="roster_school_district" value="<?php echo esc_attr($school_district); ?>" />
    </p>
    <?php
}

function roster_type_meta_box_callback($post) {
    $type = get_post_meta($post->ID, 'roster_type', true);
    wp_nonce_field('roster_type_meta_box_nonce', 'meta_box_nonce');
    ?>
    <p>
        <label for="roster_type">Select Roster Type:</label>
        <select name="roster_type" id="roster_type">
            <option value="10U" <?php selected($type, '10U'); ?>>10U</option>
            <option value="12U" <?php selected($type, '12U'); ?>>12U</option>
            <option value="14U" <?php selected($type, '14U'); ?>>14U</option>
        </select>
    </p>
    <?php
}

function save_roster_meta_box_data($post_id) {
    if (!isset($_POST['meta_box_nonce']) || !wp_verify_nonce($_POST['meta_box_nonce'], 'roster_meta_box_nonce')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $fields = ['roster_number', 'roster_name', 'roster_position', 'roster_bats', 'roster_throws', 'roster_school_district', 'roster_type'];
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $sanitized_value = sanitize_text_field($_POST[$field]);
            update_post_meta($post_id, $field, $sanitized_value);
            error_log("Saving $field: $sanitized_value"); // Log the saved data
        } else {
            error_log("$field is not set"); // Log if a field is not set
        }
    }
}
add_action('save_post', 'save_roster_meta_box_data');

// Add Shortcodes for 10U, 12U, and 14U
function roster_10u_shortcode() {
    return roster_shortcode_handler('10U');
}
add_shortcode('roster_10u', 'roster_10u_shortcode');

function roster_12u_shortcode() {
    return roster_shortcode_handler('12U');
}
add_shortcode('roster_12u', 'roster_12u_shortcode');

function roster_14u_shortcode() {
    return roster_shortcode_handler('14U');
}
add_shortcode('roster_14u', 'roster_14u_shortcode');

function roster_shortcode_handler($type) {
    error_log("Fetching roster for: $type"); // Log the type of roster being fetched
    $query_args = array(
        'post_type' => 'roster',
        'meta_key' => 'roster_number',
        'orderby' => 'meta_value_num',
        'order' => 'ASC',
        'meta_query' => array(
            array(
                'key' => 'roster_type',
                'value' => $type,
                'compare' => '='
            )
        )
    );

    error_log('Query Args: ' . print_r($query_args, true)); // Log the query arguments
    $query = new WP_Query($query_args);

    if ($query->have_posts()) {
        $output = '<style>
            .roster-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 18px; text-align: left; }
            .roster-table th, .roster-table td { padding: 12px; border: 1px solid #ddd; }
            .roster-table th { background-color: #f2f2f2; }
            @media screen and (max-width: 600px) {
                .roster-table thead { display: none; }
                .roster-table, .roster-table tbody, .roster-table tr, .roster-table td { display: block; width: 100%; }
                .roster-table tr { margin-bottom: 15px; }
                .roster-table td { text-align: right; padding-left: 50%; position: relative; }
                .roster-table td:before { content: attr(data-label); position: absolute; left: 0; width: 50%; padding-left: 10px; font-weight: bold; text-align: left; }
            }
        </style>';

        $output .= '<table class="roster-table">';
        $output .= '<thead><tr><th>Name</th><th>Number</th><th>Position</th><th>Bats</th><th>Throws</th><th>School District</th></tr></thead><tbody>';
        while ($query->have_posts()) {
            $query->the_post();
            $number = get_post_meta(get_the_ID(), 'roster_number', true);
            $name = get_post_meta(get_the_ID(), 'roster_name', true);
            $position = get_post_meta(get_the_ID(), 'roster_position', true);
            $bats = get_post_meta(get_the_ID(), 'roster_bats', true);
            $throws = get_post_meta(get_the_ID(), 'roster_throws', true);
            $school_district = get_post_meta(get_the_ID(), 'roster_school_district', true);
            error_log("Player: $number, $name, $position, $bats, $throws, $school_district"); // Log the player details
            $output .= "<tr><td data-label='Name'>$name</td><td data-label='Number'>$number</td><td data-label='Position'>$position</td><td data-label='Bats'>$bats</td><td data-label='Throws'>$throws</td><td data-label='School District'>$school_district</td></tr>";
        }
        $output .= '</tbody></table>';
    } else {
        $output = 'No players found for ' . $type;
        error_log("No players found for: $type"); // Log no players found
    }

    wp_reset_postdata();
    return $output;
}
?>
