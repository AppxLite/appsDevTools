<?php

//评论开启状态
add_action('rest_api_init', function () {
    register_rest_route('apps/v1', 'comment/config', array(
        'methods' => 'GET', 
        'callback' => 'getEnableCommentConfig'
   ));
});
function getEnableCommentConfig($data) {
    $data = getCommentConfig();
    if (empty($data)) {
        return new WP_Error('no options', 'no options', array('status' => 404));
    }
    $response = new WP_REST_Response($data);
    $response->set_status(200);
    return $response;
}
function getCommentConfig() {
    $enable = get_option('lite_comments');
    if ($enable) {
        $result["code"] = "success";
        $result["message"] = "get enableComment success";
        $result["status"] = "200";
        $result["enable"] = $enable;
        return $result;
    } else {
        $result["code"] = "success";
        $result["message"] = "get enableComment success";
        $result["status"] = "200";
        $result["enable"] = $enable;
        return $result;
    }
}

// 增加评论自定义字段
add_filter('rest_prepare_comment', 'rest_comments_custom_fields', 10, 3);
function rest_comments_custom_fields($data, $comment, $request) { 
    global $wpdb;
    $_data = $data->data;
    $comment_id = $comment->comment_ID;
    $sql = $wpdb->prepare("SELECT t2.comment_author as parent_name, t2.comment_date as parent_date, t1.user_id as user_id, (SELECT t3.meta_value from ".$wpdb->commentmeta." t3 where t1.comment_ID = t3.comment_id AND t3.meta_key = 'formId') AS formId from ".$wpdb->comments." t1 LEFT JOIN ".$wpdb->comments." t2 on t1.comment_parent = t2.comment_ID WHERE t1.comment_ID = %d", $comment_id);
	$comment = $wpdb->get_row($sql);
    $userid = $comment->user_id;
    $parent_name = $comment->parent_name;
    $parent_date = $comment->parent_date;
    $formId = $comment->formId;
    if(empty($formId)) {
        $formId = "";
    }
    if(empty($parent_name)) {
        $parent_name = "";
    }
    if(empty($parent_date)) {
        $parent_date = "";
    }
    $_data['parent_name'] = $parent_name;
    $_data['parent_date'] = $parent_date;
    $_data['userid'] = $userid;
    $_data['formId'] = $formId;
    $data->data = $_data;
    return $data;
}

// 定义热门评论 REST API
add_action('rest_api_init', function() {
	register_rest_route('apps/v1', 'comment/hot', array(
		'methods' => 'GET', 
		'callback' => 'getHotCommentsPosts'
	));
});
function getHotCommentsPosts($data) {
	$data = get_hot_comments_post_data(10);
	if (empty($data)) {
		return new WP_Error('no posts', 'no posts', array('status' => 404));
	}
	$response = new WP_REST_Response($data);
	$response->set_status(200);
	return $response;
}
// 获取年度评论最多的文章
function get_hot_comments_post_data($limit) {
	global $wpdb, $post;
    $today = date("Y-m-d H:i:s");// 获取当天日期时间
    $limit_date = date("Y-m-d H:i:s", strtotime("-1 year"));// 获取指定日期时间
	$sql = $wpdb->prepare("SELECT ".$wpdb->posts.".ID as ID, post_title, post_name, post_content, post_date, COUNT(".$wpdb->comments.".comment_post_ID) AS 'comment_total' FROM ".$wpdb->posts." LEFT JOIN ".$wpdb->comments." ON ".$wpdb->posts.".ID = ".$wpdb->comments.".comment_post_ID WHERE comment_approved = '1' AND post_date BETWEEN '".$limit_date."' AND '".$today."' AND post_status = 'publish' AND post_password = '' GROUP BY ".$wpdb->comments.".comment_post_ID ORDER BY comment_total DESC LIMIT %d", $limit);
	$mostcommenteds = $wpdb->get_results($sql);
    $posts = array();
    foreach ($mostcommenteds as $post) {
		$post_id = (int)$post->ID;
		$post_title = stripslashes($post->post_title);
		$post_views = (int)get_post_meta($post_id, 'views', true);
        $post_date = $post->post_date;
		$post_comment = (int)$post->comment_total;
		$post_permalink = get_permalink($post->ID);
		$post_thumbnail = get_post_thumbnail($post_id);
		$sql_like = $wpdb->prepare("SELECT COUNT(1) FROM ".$wpdb->postmeta." where meta_value = 'like' and post_id = %d", $post_id);
		$post_like = $wpdb->get_var($sql_like);
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
	return $posts;
}

// 最新评论文章 REST API
add_action('rest_api_init', function () {
	register_rest_route('apps/v1', 'comment/new', array(
		'methods' => 'GET', 
		'callback' => 'getNewCommentsPosts'
	));
});
function getNewCommentsPosts($data) {
	$data = get_new_comments_post_data(10);
	if (empty($data)) {
		return new WP_Error('noposts', 'noposts', array('status' => 404));
	}
	$response = new WP_REST_Response($data);
	$response->set_status(200);
	return $response;
}
// 获取近期评论文章
function get_new_comments_post_data($limit) {
    global $wpdb, $post;
    $time_difference = get_option('gmt_offset');
    $sql = $wpdb->prepare("SELECT ".$wpdb->posts.".ID as ID, post_title, post_name, post_content, post_date, COUNT(".$wpdb->comments.".comment_post_ID) AS 'comment_total' FROM ".$wpdb->posts." LEFT JOIN ".$wpdb->comments." ON ".$wpdb->posts.".ID = ".$wpdb->comments.".comment_post_ID WHERE comment_approved = '1' AND post_date < '".date("Y-m-d H:i:s", (time() + ($time_difference * 3600)))."'AND post_status = 'publish' AND post_password = '' GROUP BY ".$wpdb->comments.".comment_post_ID ORDER BY comment_date DESC LIMIT %d", $limit);
	$newcomments = $wpdb->get_results($sql);
    $posts = array();
    foreach ($newcomments as $post) {
		$post_id = (int) $post->ID;
		$post_title = stripslashes($post->post_title);
		$post_views = (int)get_post_meta($post_id, 'views', true);
        $post_date = $post->post_date;
        $post_comment = (int)$post->comment_total;
        $post_permalink = get_permalink($post->ID);
		$post_thumbnail = get_post_thumbnail($post_id);
		$sql_like = $wpdb->prepare("SELECT COUNT(1) FROM ".$wpdb->postmeta." where meta_value = 'like' and post_id = %d", $post_id);
		$post_like = $wpdb->get_var($sql_like);	
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
	return $posts;
}

// 获取评论及回复 REST API
add_action('rest_api_init', function() {
	register_rest_route('apps/v1', 'comment/list', array(
		'methods' => 'get', 
		'callback' => 'getLiteCommentsData'
	));
});
function getLiteCommentsData($request) {
	$postid = isset($request['postid']) ? (int)$request['postid'] : 0;
 	$limit = isset($request['limit']) ? (int)$request['limit'] : 0;
	$page = isset($request['page']) ? (int)$request['page'] : 0;
	$order = isset($request['order']) ? $request['order'] : '';
	if (empty($order)) {
		$order = "asc";
	}
	if (empty($postid) || empty($limit) || empty($page) || get_post($postid) == null) {
		return new WP_Error('error', 'postid or limit or page or post is empty', array('status' => 500));
	} else {
		$data = get_comments_data($postid, $limit, $page, $order);
		if (empty($data)) {
			return new WP_Error('error', 'add comment error', array('status' => 404));
		}
		$response = new WP_REST_Response($data);
		$response->set_status(200);
		return $response;
	}
}
function get_comments_data($postid, $limit, $page, $order) {
	global $wpdb;
	$page = ($page-1)*$limit;
	$sql = $wpdb->prepare("SELECT t.*, (SELECT t2.meta_value from ".$wpdb->commentmeta." t2 where t.comment_ID = t2.comment_id AND t2.meta_key = 'formId') AS formId FROM ".$wpdb->comments." t WHERE t.comment_post_ID = %d and t.comment_parent = 0 and t.comment_approved = '1' order by t.comment_date ".$order." limit %d, %d", $postid, $page, $limit);
	$comments = $wpdb->get_results($sql);
	$commentslist = array();
	foreach($comments as $comment) {
		if($comment->comment_parent == 0) {
			$data["id"] = $comment->comment_ID;
			$data["author_name"] = $comment->comment_author;
			$author_url = $comment->comment_author_url;
			$data["author_url"] = strpos($author_url, "wx.qlogo.cn") ? $author_url:"../../images/gravatar.png";
			$data["date"] = time_tran($comment->comment_date);
			$data["content"] = $comment->comment_content;
			$data["formId"] = $comment->formId;
			$data["userid"] = $comment->user_id;
			$order = "asc";
			$data["child"] = getchaildcomment($postid, $comment->comment_ID, 5, $order);
			$commentslist[] = $data;
		}
	}
	$result["code"] = "success";
    $result["message"] = "get comments success";
    $result["status"] = "200";
    $result["counts"] = count_comments($postid, 1);
    $result["data"] = $commentslist;
    return $result;
}
function getchaildcomment($postid, $comment_id, $limit, $order) {
	global $wpdb;
	if ($limit>0) {
		$commentslist = array();
		$sql = $wpdb->prepare("SELECT t.*, (SELECT t2.meta_value from ".$wpdb->commentmeta." t2 where t.comment_ID = t2.comment_id AND t2.meta_key = 'formId') AS formId FROM ".$wpdb->comments." t WHERE t.comment_post_ID = %d and t.comment_parent = %d and t.comment_approved = '1' order by comment_date ".$order, $postid, $comment_id);
		$comments = $wpdb->get_results($sql);
		foreach($comments as $comment) {						
			$data["id"] = $comment->comment_ID;
			$data["author_name"] = $comment->comment_author;
			$author_url = $comment->comment_author_url;
			$data["author_url"] = strpos($author_url, "wx.qlogo.cn")?$author_url:"../../images/gravatar.png";
			$data["date"] = time_tran($comment->comment_date);
			$data["content"] = $comment->comment_content;
			$data["formId"] = $comment->formId;
			$data["userid"] = $comment->user_id;
			$data["child"] = getchaildcomment($postid, $comment->comment_ID, $limit-1, $order);
			$commentslist[] = $data;			
		}
	}
	return $commentslist;
}

/*
* 获取文章的评论人数/评论数量
* $postid:文章id
* $which:返回类型（0或1）为0时返回评论人数，为1时返回评论数量
*/
function count_comments($postid, $which) {
	$comments = get_comments('status=approve&type=comment&post_id='.$postid); //获取文章的所有评论
	if ($comments) {
		$i = 0; $j = 0; $commentusers = array();
		foreach ($comments as $comment) {
			++$i;
			if ($i==1) {$commentusers[] = $comment->comment_author_email; ++$j;}
			if (!in_array($comment->comment_author_email, $commentusers)) {
				$commentusers[] = $comment->comment_author_email;
				++$j;
			}
		}
		$output = array($j,$i);
		$which = ($which == 0) ? 0 : 1;
		return $output[$which]; //返回评论人数
	}
	return 0; //没有评论返回0
}

// 提交评论 REST API
add_action('rest_api_init', function () {
	register_rest_route('apps/v1', 'comment/add', array(
		'methods' => 'POST', 
		'callback' => 'addComments'
	));
});
function addComments($request) {
	$post = (int)$request['post'];
    $author_name = $request['author_name'];
    $author_email = $request['author_email'];
    $content = $request['content'];
    $author_url = $request['author_url'];
    $openid = $request['openid'];
    $reqparent = '0';
    $userid = 0;
    $formId = '';
    $comment_author_IP = '';
    if(isset($request['userid'])) {
        $userid = (int)$request['userid'];
    }
    if(isset($request['formId'])) {
        $formId = $request['formId'];
    }
    if(isset($request['parent'])) {
        $reqparent = $request['parent'];
    }
    $parent = 0;
    if(is_numeric($reqparent)) {
        $parent = (int)$reqparent;
        if($parent < 0) {
            $parent = 0;
        }
    }
    if($parent != 0) {
        $comment = get_comment($parent);
        if (empty($comment)) {
			{
                return new WP_Error('error', 'parent id is error', array('status' => 500));
            }
        }
    }
    if(empty($openid) || empty($post) || empty($author_url) || empty($author_email) || empty($content) || empty($author_name)) {
        return new WP_Error('error', 'openid or post or author_name or author_url or author_email or content is empty', array('status' => 500));
    } else if(get_post($post) == null) {
        return new WP_Error('error', 'post id is error', array('status' => 500));
    } else {
        if(!username_exists($openid)) {
            return new WP_Error('error', 'not allowed to submit', array('status' => 500));
        } else if(is_wp_error(get_post($post))) {
            return new WP_Error('error', 'post id is error', array('status' => 500));
        } else {
            $data = add_comment_data($post, $author_name, $author_email, $author_url, $content, $parent, $openid, $userid, $formId, $comment_author_IP);
            if (empty($data)) {
                return new WP_Error('error', 'add comment error', array('status' => 404));
            }
            $response = new WP_REST_Response($data);
            $response->set_status(200);
            return $response;
        }
    }
}
function add_comment_data($post, $author_name, $author_email, $author_url, $content, $parent, $openid, $userid, $formId, $comment_author_IP) {
	global $wpdb;
    $user_id = 0;
    $useropenid = "";
	$approved = get_option('lite_check_comments');
	$sql = $wpdb->prepare("SELECT ID FROM ".$wpdb->users." WHERE user_login = '%s'", $openid);
    $users = $wpdb->get_results($sql);
    foreach ($users as $user) {
        $user_id = (int)$user->ID;
    }
    $commentdata = array(
        'comment_post_ID' => $post, // to which post the comment will show up
        'comment_author' => $author_name, //fixed value - can be dynamic 
        'comment_author_email' => $author_email, //fixed value - can be dynamic 
        'comment_author_url' => $author_url, //fixed value - can be dynamic 
        'comment_content' => $content, //fixed value - can be dynamic 
        'comment_type' => '', //empty for regular comments, 'pingback' for pingbacks, 'trackback' for trackbacks
        'comment_parent' => $parent, //0 if it's not a reply to another comment;if it's a reply, mention the parent comment ID here
		'comment_approved' => $approved ? 0 : 1, // Whether the comment has been approved
        'user_id' => $user_id, //passing current user ID or any predefined as per the demand
        'comment_author_IP' => $comment_author_IP
   );
    $comment_id = wp_insert_comment(wp_filter_comment($commentdata));
    if($comment_id) {
        $useropenid = "";
        if($userid != 0) {
            $sql = "SELECT user_login FROM ".$wpdb->users ." WHERE ID = ".$userid;
            $users = $wpdb->get_results($sql);
            foreach ($users as $user) {
                $useropenid = $user->user_login;
            }
        }
        $addcommentmetaflag = false;
        if($formId != '') {
            $addcommentmetaflag = add_comment_meta($comment_id, 'formId', $formId, false);
        }
        $result["code"] = "success";
        if($addcommentmetaflag) {
            $result["message"] = "add comment and formId success";
        } else {
            $result["message"] = "add comment success, add formId fail";
        } 
        $result["status"] = "200";
        $result["useropenid"] = $useropenid;
        return $result;
    } else {
        $result["code"] = "success";
        $result["message"] = "add comment error";
        $result["status"] = "500";
        return $result;
    }
}

// 我的评论 REST API
add_action('rest_api_init', function () {
	register_rest_route('apps/v1', 'comment/get', array(
		'methods' => 'GET', 
		'callback' => 'getLiteComments'
	));
});
function getLiteComments($request) {
	$openid = isset($request['openid']) ? $request['openid'] : '';
    if(empty($openid)) {
        return new WP_Error('error', 'openid is empty', array('status' => 500));
    } else{
        if(!username_exists($openid)) {
            return new WP_Error('error', 'not allowed to submit', array('status' => 500));
        } else {
            $data = get_comment_data($openid);
            if (empty($data)) {
                return new WP_Error('error', 'add comment error', array('status' => 404));
            }
            $response = new WP_REST_Response($data);
            $response->set_status(200);
            return $response;
        }
    }
}
function get_comment_data($openid) {
	global $wpdb;
    $user_id = 0;
	$sql = $wpdb->prepare("SELECT ID FROM ".$wpdb->users ." WHERE user_login = '%s'", $openid);
    $users = $wpdb->get_results($sql);
    foreach ($users as $user) {
        $user_id = (int)$user->ID;
    }
    if($user_id == 0) {
        $result["code"] = "success";
        $result["message"] = "user_id is empty";
        $result["status"] = "500";
        return $result;
    } else {
        $sql = $wpdb->prepare("SELECT * from ".$wpdb->posts." where ID in (SELECT comment_post_ID from ".$wpdb->comments." where user_id = %s GROUP BY comment_post_ID order by comment_date) LIMIT 10", $user_id);
		$_posts = $wpdb->get_results($sql);
        $posts = array();
        foreach ($_posts as $post) {
            $_data["id"] = $post->ID;
            $_data["title"]["rendered"] = $post->post_title;
            if (empty(get_option('lite_meta'))) {
                $_data["thumbnail"] = get_post_meta($post->ID, 'thumbnail' , true);
                $_data["views"] = get_post_meta($post->ID, 'views', true);
            } else {
                $_data["meta"]["thumbnail"] = get_post_meta($post->ID, 'thumbnail' , true);
                $_data['meta']["views"] = get_post_meta($post->ID, 'views', true);
                $metaArr = explode(',', get_option('lite_meta'));
                foreach ($metaArr as $value) {
                    $_data["meta"][$value] = get_post_meta($post_id, $value , true);
                }
            }
            $posts[] = $_data;
        }
        $result["code"] = "success";
        $result["message"] = "get comments success";
        $result["status"] = "200";
        $result["data"] = $posts;
        return $result;
    }
}