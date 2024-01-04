
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YouTube Playlist Viewer</title>
</head>
<body>
    <h2>YouTube Playlist Videos</h2>

 <?php 
 // Include WordPress functions
require_once('wp-load.php');
// Your YouTube API Key
$api_key = '## ENTER YOUR API KEY HERE##';
// YouTube Playlist ID
$playlist_id = '## ENTER YOUR PLAYLIST ID HERE##';

//
// Function to fetch videos from YouTube playlist
//
function getYouTubePlaylistVideos($api_key, $playlist_id) {

    //Uses the Youtube API to get the results as a JSON Object
    $max_results = 20; //The default number of results from the youtube API is 5, change this number to alter that amount
    $api_url = "https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&playlistId=$playlist_id&key=$api_key&maxResults=$max_results";
    $response = wp_remote_get($api_url);
    
    //Handles errors for the JSON
    if (is_wp_error($response)) {
        echo 'Error: ' . $response->get_error_message();
        return false;
    }

    //Decodes the JSON into a videos object
    $body = wp_remote_retrieve_body($response);
    $videos = json_decode($body, true);

    //Takes the JSON object and simplifies it into a PHP object that can be processed
    $videosList = array();
    if (empty($videos['items'])) {
        echo "<p>No videos found in the playlist.</p>";
    }
    else {
        foreach ($videos['items'] as $video) {

        $video_id = $video['snippet']['resourceId']['videoId'];
        $video_title = $video['snippet']['title'];
        $video_date = $video['snippet']['publishedAt'];
        $video_url = "https://www.youtube.com/watch?v=$video_id";
        $video_thumbnail = $video['snippet']['thumbnails']['standard']['url'];
        //PHP array that we can return that has a matching format the messages array 
        $videosList[] = array(
            'title' => $video_title,
            'url'   => $video_url,
            'date' => $video_date,
            'thumbnail' => $video_thumbnail
        );

    }
    return $videosList;

    }
}

// 
// Function to get all the current messages and return them
//
function getMessages() 
 {
    $messages = array();
    $single_message = array();

    // Query custom post type "message"
    $args = array(
        'post_type'      => 'message', //This is the post type we are querying for
        'posts_per_page' => -1, // Retrieve all posts
        'no_found_rows' => true,
    );

    $query = new WP_Query($args);

    // Check if there are any posts
    if ($query->have_posts()) {
        $i = 0;
        while ($query->have_posts()) {
            $i = $i+1; 
            
            $query->the_post();

            // Get post title
            $post_title = get_the_title();

            // Get ACF field "messages" (assuming it's a URL field)
            $message_url = get_field('messages'); //Adjust the post type you're querying for

            $post_date = get_the_date();

            // Store in the array
            $messages[] = array(
                'title' => $post_title,
                'url'   => $message_url,
                'date' => $post_date
            );
            
            
        }
        return $messages;

        // Restore original post data
        wp_reset_postdata();
    } else {
        // No posts found
        echo 'No messages found.';
    }
 }
 
 //
 // Filters out messages 
 //
 function filterMessages($messages, $videoslist) {
    $newmessages = array();

    // Iterate through videoslist
    foreach ($videoslist as $video) {
        $url = $video['url'];

        // Check if the URL is not present in any of the messages
        $urlNotInMessages = true;
        foreach ($messages as $message) {
            if ($message['url'] == $url) {
                $urlNotInMessages = false;
                break;
            }
        }

        // If URL is not present in messages, add it to newmessages
        if ($urlNotInMessages) {
            $newmessages[] = $video;
        }
    }

    return $newmessages;
}

/**
* Downloads an image from the specified URL and attaches it to a post as a post thumbnail. This comes from Rob Verneer and Jan Beck on stackoverflow
*
* @param string $file    The URL of the image to download.
* @param int    $post_id The post ID the post thumbnail is to be associated with.
* @return string|WP_Error Attachment ID, WP_Error object otherwise.
*/
function Generate_Featured_Image( $image_url, $post_id  ){
    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents($image_url);
    $filename = basename($image_url);
    if(wp_mkdir_p($upload_dir['path']))
      $file = $upload_dir['path'] . '/' . $filename;
    else
      $file = $upload_dir['basedir'] . '/' . $filename;
    file_put_contents($file, $image_data);

    $wp_filetype = wp_check_filetype($filename, null );
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name($filename),
        'post_content' => '',
        'post_status' => 'inherit'
    );
    $attach_id = wp_insert_attachment( $attachment, $file, $post_id );
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
    $res1= wp_update_attachment_metadata( $attach_id, $attach_data );
    $res2= set_post_thumbnail( $post_id, $attach_id );
}


///
/// MAIN EXECUTION 
///

$youtube_videos = getYouTubePlaylistVideos($api_key, $playlist_id); //Gets youtube videos from the playlist
$messages = getMessages(); //Gets existing messages
$newmessages = filterMessages($messages, $youtube_videos); //Filters out messages from the playlist file that have URLs not listed on youtube

//Creates new posts for any new messages
// Check if there are any messages
if (!empty($newmessages)) {
    // Output messages in a table
    echo '<table>';
    echo '<tr><th>Title</th><th>URL</th></tr>';
    foreach ($newmessages as $newmessages) {

        
        // Gather post data to create a post for the new message
        $my_post = array(
        'post_title'    => $newmessages['title'],
        'post_content'  => 'Auto-Generated by Jared Thorntons Plugin',
        'post_status'   => 'publish',
        'post_author'   => 1,
        'post_category' => array( 8,39 ),
        'post_type' => 'message', //Adjust to the post you want to make
        'post_date' =>  $newmessages['date']
        );

        // Insert the post into the database.
        $post_id = wp_insert_post( $my_post, $wp_error );
        echo $newmessages['thumbnail'];
        $post_thumbnail = Generate_Featured_Image($newmessages['thumbnail'], $post_id);


        //Update the custom field with the URL
        update_field('messages', $newmessages['url'], $post_id); //Change tthe first parameter to the field you want to add the URL to

        //Adds HTML output if you visit the page manually. 
        echo '<tr>';
        echo '<td>' . esc_html($newmessages['title']) . '</td>';
        echo '<td><a href="' . esc_url($newmessages['url']) . '">' . esc_html($newmessages['url']) . '</a></td>';
        echo '</tr>';
    }

} else {
    // No messages found
    echo 'No messages found.';
}





?>
</body>
</html>
