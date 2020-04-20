<?php

$PYTHON_SERVER = "http://localhost:27036/";

$code = 200;
$message = 'Success.';
header('Content-Type: application/json');

// Method: POST, PUT, GET etc
// Data: array("param" => "value") ==> index.php?param=value
// Based on: https://stackoverflow.com/questions/9802788/
function call_get_api($url, $data = false) {
    if ($data)
        $url = sprintf("%s?%s", $url, http_build_query($data));

    // Make the api call.
    $result = file_get_contents($url);

    // Decode the JSON response.
    $result = json_decode($result, true);

    return $result;
}

function extract_vid_id($data) {
    if (preg_match_all("/#[\w-]{11}#/", $data) === 1) {
        // Data is a video id.
        return $data;
    }

    // Parse the given URL to get the query string.
    $parsed = parse_url($data);

    if ($parsed['host'] === 'youtu.be') {
    	return (substr($parsed['path'], 1));
    }
    
    // Parse the query string into an array.
    parse_str($parsed['query'], $query);

    // Return the 'v' key of the array.
    return $query['v'];
}

function get_vid_data($v) {
    $url = 'http://www.youtube.com/watch?v=' . $v;
    return json_decode(file_get_contents("http://www.youtube.com/oembed?url=$url&format=json"));
}

function add_vid_to_queue($data) {
    global $PYTHON_SERVER;

    $vid_id = extract_vid_id($data);
    $response = call_get_api(
	    $PYTHON_SERVER . "add",
	    array(
		    "vid" => $vid_id,
		    "user" => $_SERVER['PHP_AUTH_USER'],
		    "note" => $_POST['note']
	    )
    );

    return array_key_exists('message', $response) &&
           $response['message'] === 'Success!';
}

function queue_control($control) {
    switch ($control) {
	case 'skip':
        case 'pause':
        case 'resume':
            global $PYTHON_SERVER;
            file_put_contents('logs.txt', $control.$_SERVER['PHP_AUTH_USER'].PHP_EOL , FILE_APPEND | LOCK_EX);
            call_get_api($PYTHON_SERVER . $control, array("user" => $_SERVER['PHP_AUTH_USER']));
            return true;
        default:
            $code = 400;
            $message = 'Unknown action $control.';
            return false;
    }
}

function vid_data($vid_id) {
    $url = 'http://www.youtube.com/watch?v=' . $vid_id;
    $curr_data = get_vid_data($vid_id);
    $title = htmlentities($curr_data->title, null, 'UTF-8');
    $author_name = htmlentities($curr_data->author_name, null, 'UTF-8');
    $author_url = htmlentities($curr_data->author_url, null, 'UTF-8');
    $thumbnail = htmlentities($curr_data->thumbnail_url, null, 'UTF-8');

    $vid_data = array(
        'url' => $url,
        'title' => $title,
        'author_name' => $author_name,
        'author_url' => $author_url,
        'thumbnail' => $thumbnail
    );
    return $vid_data;
}

if (array_key_exists('action', $_GET)) { // Queue control action.
    queue_control($_GET['action']);
}

if (array_key_exists('url', $_POST)) { // Add song to queue.
    // Get the video id and video data.
    $vid_id = extract_vid_id($_POST['url']);
    $data = get_vid_data($vid_id);

    // Simple Ride filter.
    if (strpos(strtolower($data->title), 'valkyries') === false) {
        $meme = false;
        $painful = false;

	    $meme_titles = [
	        'but every',
             'but it',
             'boosted',
             'recorder',
             'flute',
             'shitty',
             '(slowly',
             'every note',
             'every time',
             'but the',
             'gets faster',
             ' are number',
             'ever ever ever',
	        'but instead'
	    ];

	    $meme_authors = [
	        'pluffnub'
    	];

	    $painful_titles = [
	        'very loud',
	        'wilhelm scream'
        ];

	    $painful_authors = [
            'webdriver'
	    ];

	    foreach ($meme_titles as $filter) {
	        if (strpos(strtolower($data->title), $filter) !== false) { $meme = true; }
	    }
	    
	    foreach ($meme_authors as $filter) {
	        if (strpos(strtolower($data->author_name), $filter) !== false) { $meme = true; }
	    }

	    foreach ($painful_titles as $filter) {
	        if (strpos(strtolower($data->title), $filter) !== false) { $painful = true; }
	    }
	    
	    foreach ($painful_authors as $filter) {
	        if (strpos(strtolower($data->author_name), $filter) !== false) { $painful = true; }
	    }

        if (false && ($meme === true)){
            $code = 402;
            $message = "[[[ PFW MEME-A-TORIUM ]]]";
        } else if (false && ($painful === true)){
            $code = 403;
            $message = "Have mercy on our fragile ears.";
        } else {
            // Get the POST data.
            if (add_vid_to_queue($_POST['url'])) {
                $message = 'Successfully added video to queue.';
            } else {
                $code = 500; // Server error.
                $message = 'Failed to add video to queue.';
            }
        }
    } else {
        $code = 401; // You can't just play the ride!
        $message = 'Ride detected. Nice try, punk.';
    }
}

if (array_key_exists('status', $_GET)) {
    $history = array();
    $queue = array();
    $songs = array();

    $data = call_get_api($PYTHON_SERVER . 'status');
    /*
    if (is_array($data['queue'])) {
        $i = 0;
        foreach (array_reverse($data['queue']) as $vid) {
            $song = vid_data($vid['vid']);
	    $song['note'] = $vid['note'];
	    $song['added_by'] = $vid['user'];
	    $song['added_on'] = $vid['time'];
	    array_push($queue, $song);
	    array_push($songs, $song);
        }
    }
    */
 
    if ($data['current'] != null) { 
        $current = vid_data($data['current']['vid']);
        $current['note'] = $data['current']['note'];
        $current['added_by'] = $data['current']['user'];
        $current['added_on'] = $data['current']['time'];
    
	$data['current'] = $current;
	array_push($songs, $current);
    }	
    
    /*
    if (is_array($data['history'])) {
        $i = 0;
        foreach (array_reverse($data['history']) as $vid) {
            $song = vid_data($vid['vid']);
	    $song['note'] = $vid['note'];
	    $song['added_by'] = $vid['user'];
	    $song['added_on'] = $vid['time'];
	    array_push($history, $song);
	    array_push($songs, $song);
        }
    }
    
    $data['songs'] = $songs;
    */

    echo json_encode($data);
} else {
    $result = $code == 200 ? 'success' : 'failure';
    echo "{\"result\": \"$result\", \"message\": \"$message\"}";
    http_response_code($code);
}

?>

