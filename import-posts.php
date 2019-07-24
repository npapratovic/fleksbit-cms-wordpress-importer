<?php
	error_reporting(E_ALL);
	ini_set('display_errors', 'On');
	ini_set('max_execution_time', 0); // for infinite time of execution 
 
	// require wp-load.php to use built-in WordPress functions
	require_once("../wp-load.php");
	   
	//to import content from FleksbitCMS we will connect to db, and select data from appropriate tables
 
    //we need to manually connect to Laravel DB, and get file url from image ID: 
    $servername = "xxx.xxx.xxx.xxx";
	$username = "root";
	$password = "";
	$dbname = "laravel_db_name";
 
	// Create connection
	$conn = new mysqli($servername, $username, $password, $dbname);
 
	//Because of compatibility we need to set charachters to utf8mb4
	$conn->set_charset('utf8mb4');

	// Check connection
	if ($conn->connect_error) {

	    die("Connection failed: " . $conn->connect_error);

	} 
 
	$sql = 'SELECT * FROM posts';

	$result = $conn->query($sql); 
 
	while($row = $result->fetch_array()) { 
 
 		$article_id = $row['id'];
  
		$post_title = $row['title'];
		$post_content = $row['body'];
  
		//lets migrate post_status to appropriate ones in WordPress: 

		$_post_status = $row['post_status_id'];

		switch ($_post_status) {
		    case "1": //draft
		   		$post_status_ID = 'draft';
		        break;
		    case "3": //publish
		        $post_status_ID = 'publish';
		        break;
		    default:
		        $post_status_ID = 'draft'; // default post_status 
		}

		$post_status = $post_status_ID;

		$post_date = $row['published_at'];

		$post_name = $row['permalink'];

		//lets fetch featured image name:

		$featured_image_id = $row['featured_image_id'];
 
		// each post has only ONE featured_image.  
	 
		if(!is_null($featured_image_id)) {

			$get_image = 'SELECT filename FROM media WHERE id = '.$featured_image_id.'';
			$image_response = $conn->query($get_image);
		    $featured_image_arr = $image_response->fetch_assoc();
 			$featured_image = $featured_image_arr['filename'];
	
		}
 
		$post_type = 'post';

		$category_id = $row['category_id'];

		// each post has only ONE main category.  
	 
		if(!is_null($category_id)) {

			$get_category = 'SELECT name FROM post_categories WHERE id = '.$category_id.'';
			$category_response = $conn->query($get_category);
		    $category_arr = $category_response->fetch_assoc();
 			$category_name = $category_arr['name'];
	
		}

		// each post might belong to only ONE subcategory. Meaning we need to check first whether post is assigned to category
 
		$subcategory_id = $row['subcategory_id'];

		if(!is_null($subcategory_id)) {

			$get_sub_category = 'SELECT name FROM post_subcategories WHERE id = '.$subcategory_id.'';
			$sub_category_response = $conn->query($get_sub_category);
		    $sub_category_arr = $sub_category_response->fetch_assoc();
 			$sub_category_name = $sub_category_arr['name'];
	 
		}

		$post_excerpt = $row['lead_text'];
		if(is_null($post_excerpt)) {
			$post_excerpt = '';			
		}
 
 		// Now we have all data, and we can import posts in WP.
 	   
 	   	// Lets check if category and subcategory exist in WordPress
	    
	    // First categories: 

	    $term = term_exists( $category_name, 'category' );
 
		if ( 0 !== $term && null !== $term ) { 

		    $post_category_id = intval( $term['term_id'] );

		} else {
			
			// create category, fetch category ID

   			$permalink = strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '',preg_replace('/\s+/', '-', $category_name) ));
  
			$category_create = wp_insert_term( $category_name, 'category', array( 'slug' => $permalink ) );

			$post_category_id = $category_create['term_id'];  
		}

		// Then subcategories (only if it is assigned): 
		// TODO: create hierarchy parent - child for categories programatically 

		if(!is_null($sub_category_name)) {

			$term = term_exists( $sub_category_name, 'category' );
	 
			if ( 0 !== $term && null !== $term ) { 

			    $post_sub_category_id = intval( $term['term_id'] );

			} else {
				
				// create sub_category, we need to fetch sub_category ID  

	   			$permalink = strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '',preg_replace('/\s+/', '-', $sub_category_name) ));
 
				$sub_category_create = wp_insert_term( $sub_category_name, 'category', array( 'slug' => $permalink ) );

				$post_sub_category_id = $sub_category_create['term_id'];

				if ( is_wp_error($sub_category_create) ){
				   echo $sub_category_create->get_error_message();
				}
	  
			}

		}

  
		// WordPress Array and Variables for posting
 
		$new_post = array( 
			'post_title' => $post_title,
	    	'post_content' => $post_content,
	    	'post_excerpt' => $post_excerpt,
			'post_name' => $post_name, 
			'post_status' => $post_status,
			'post_type' => $post_type,
		    'post_category' => array( $post_category_id ), 
		    'post_date' => $post_date,
			'comment_status' => 'closed',   
			'ping_status' => 'closed'    
		);

		// WordPress wp_insert_post
 
		$insert_post = wp_insert_post($new_post, true);
 
		if ( is_wp_error($insert_post) ){
		   echo $insert_post->get_error_message();
		}

  
		// we have filename for featured image, and we can add this image to post: 

		// $file_path should be the path to a file in the wp media upload directory.
		// important step is to upload featured images manually to this folder (TODO - fix this)
		// $wp_upload_dir is basically wp-uploads/current_year/current_month

		$file_path = $wp_upload_dir['url'] . '/' . $featured_image;

		// The ID of the post this attachment is for.
		$parent_post_id = $insert_post;

		// Check the type of file. We'll use this as the 'post_mime_type'.
		$filetype = wp_check_filetype( basename( $file_path ), null );

		// Get the path to the upload directory.
		$wp_upload_dir = wp_upload_dir();

		// Prepare an array of post data for the attachment.
		$attachment = array(
			'guid'           => $wp_upload_dir['url'] . '/' . basename( $file_path ), 
			'post_mime_type' => $filetype['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file_path ) ),
			'post_content'   => '',
			'post_status'    => 'inherit'
		);

		// Insert the attachment.
		$attach_id = wp_insert_attachment( $attachment, $file_path, $parent_post_id );

		// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		// Generate the metadata for the attachment, and update the database record.
		$attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		$set_post_thumbnail = set_post_thumbnail( $parent_post_id, $attach_id );

		// Update categories hack - I am using wp_set_post_categories with third parameter set to true to append categories, 
		// instead of replace see more https://codex.wordpress.org/Function_Reference/wp_set_post_categories
		// only if they are assigned

		if(!is_null($post_sub_category_id)) { 

		    $set_post_sub_categories = wp_set_post_categories( $insert_post, $post_sub_category_id, true );
			
			if ( is_wp_error($set_post_sub_categories) ){
			   echo $set_post_sub_categories->get_error_message() . 'err';
			}

		} else {
			$no_subcategories = '----There are no subcategories for this post <br />';
		}

 
	    // Show me simple progress log: 
    
		echo 'FleksbitCMS post ID: '.$article_id.' - post_title: '.$post_title.' - import details: <br>';

		if($insert_post) {
			echo '----post_import_OK <br>';
		} else {
			echo '----post_import_NOTOK <br>';
		} 

		if($set_post_sub_categories) {
			echo '----set_post_sub_categories_OK <br>';
		} else {
			echo '----set_post_sub_categories_NOTOK <br>';
			echo ''.$no_subcategories.''; 
		}

		if($set_post_thumbnail) {
			echo '----set_post_thumbnail_OK <br>';
		} else {
			echo '----set_post_thumbnail_NOTOK <br>';
		}
 

	}
 
   
?>