<?php

// 轮播图 REST API
add_action('rest_api_init', function () {
	register_rest_route('apps/v1', 'posts/swipe', array(
		'methods' => 'GET',
		'callback' => 'getPostSwipe'
	));
});
function getPostSwipe($request) {
    $data = get_swipe_post_data(); 
    if (empty($data)) {
        return new WP_Error('error', 'post swipe is error', array('status' => 404));
    }
    $response = new WP_REST_Response($data);
    $response->set_status(200); 
    return $response;
}

function get_swipe_post_data() {
    global $wpdb;
    $postSwipeId = get_option('lite_swipe');
    $posts = array();
    if(!empty($postSwipeId)) {
        $sql = $wpdb->prepare("SELECT * from ".$wpdb->posts." where id in (%d)", $postSwipeId);
        $_posts = $wpdb->get_results($sql);
        foreach ($_posts as $post) {
            $post_id = (int)$post->ID;
            $post_title = stripslashes($post->post_title);
            $post_views = (int)get_post_meta($post_id, 'views', true);
            $post_date = $post->post_date;
            $post_permalink = get_permalink($post->ID);
            $post_thumbnail = get_post_thumbnail($post_id);
            $sql_like = $wpdb->prepare("SELECT COUNT(1) FROM ".$wpdb->postmeta." where meta_value = 'like' and post_id = %d",$post_id);
            $post_like = $wpdb->get_var($sql_like);
            $sql_comment = $wpdb->prepare("SELECT COUNT(1) FROM ".$wpdb->comments." where comment_approved = '1' and comment_post_ID = %d",$post_id);
            $post_comment = $wpdb->get_var($sql_comment);
            $_data["id"] = $post_id;
            $_data["title"]["rendered"] = $post_title;
            $_data["date"] = $post_date;
            $_data["link"] = $post_permalink;
            $_data['comments'] = $post_comment;
            $_data['like'] = $post_like;
            if (empty(get_option('lite_meta'))) {
                $_data["thumbnail"] = $post_thumbnail;
                $_data["views"] = $post_views;
            } else {
                $_data["meta"]["thumbnail"] = $post_thumbnail;
                $_data['meta']["views"] = $post_views;
                $metaArr = explode(',', get_option('lite_meta'));
                foreach ($metaArr as $value) {
                    $_data["meta"][$value] = get_post_meta($post_id, $value, true);
                }
            }
            $posts[] = $_data;
        }
        $result["code"] = "success";
        $result["message"] = "get post swipe success";
        $result["status"] = "200";
        $result["posts"] = $posts;
        return $result;
    } else {
        $result["code"] = "success";
        $result["message"] = "get post swipe error";
        $result["status"] = "500";
        return $result;
    }
}