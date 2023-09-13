<?php

$api_key = 'AIzaSyA75eDTOZpSEGzBE8p5JZ2_Ln8cx42UVXk';
$base_url = 'https://www.googleapis.com/youtube/v3/';
$channel_ids = ['UC3IZKseVpdzPSBaWxBxundA', 'UCLkAepWjdylmXSltofFvsYQ', 'UCfkXDY7vwkcJ8ddFGz8KusA'];

$db = new Database();
$connect = $db->db_connect();
$insert_queries = [];

foreach ($channel_ids as $channel_id) {
    $count_sql = "SELECT COUNT(*) as video_count FROM `youtube_channel_videos` WHERE `channel_id` = '$channel_id'";
    $count_result = $connect->query($count_sql);

    if ($count_result && $count_result->num_rows > 0) {
        $row = $count_result->fetch_assoc();
        $current_video_count = $row['video_count'];
    } else {
        $current_video_count = 0;
    }

    $videos_needed = max(0, 100 - $current_video_count);

    if ($videos_needed > 0) {
        $api_channel_url = $base_url . 'channels?part=snippet&id=' . $channel_id . '&key=' . $api_key;
        $channel_info = json_decode(file_get_contents($api_channel_url));

        if ($channel_info && isset($channel_info->items[0])) {
            $info = $channel_info->items[0]->snippet;
            $channel_title = $connect->real_escape_string($info->title);
            $channel_description = $connect->real_escape_string($info->description);
            $channel_thumbnail = $info->thumbnails->high->url;

            $insert_sql = "INSERT IGNORE INTO `youtube_channels` (`channel_id`, `channel_pfp`, `channel_name`, `channel_description`) 
                           VALUES ('$channel_id', '$channel_thumbnail', '$channel_title', '$channel_description')";
            $insert_queries[] = $insert_sql;
        }

        $nextPageToken = '';
        $videoCount = 0;

        do {
            $maxResults = min(50, $videos_needed - $videoCount);

            $api_video_url = $base_url . 'search?order=date&type=video&part=snippet&channelId=' . $channel_id . '&maxResults=' . $maxResults . '&pageToken=' . $nextPageToken . '&key=' . $api_key;
            $video_data = json_decode(file_get_contents($api_video_url));

            if ($video_data) {
                foreach ($video_data->items as $video) {
                    $video_id = $connect->real_escape_string($video->id->videoId);
                    $video_link = 'https://www.youtube.com/watch?v=' . $video_id;
                    $video_title = $connect->real_escape_string($video->snippet->title);
                    $video_description = $connect->real_escape_string($video->snippet->description);
                    $video_thumbnail = $video->snippet->thumbnails->high->url;
                    $video_publishedAt = $video->snippet->publishedAt;

                    $check_sql = "SELECT video_id FROM `youtube_channel_videos` WHERE `video_id` = '$video_id'";
                    $check_result = $connect->query($check_sql);

                    if ($check_result->num_rows == 0) {
                        $insert_sql = "INSERT IGNORE INTO `youtube_channel_videos` (`video_id`, `video_link`, `video_title`, `video_description`, `video_thumbnail`, `channel_id`, `video_publishedAt`) 
                                VALUES ('$video_id', '$video_link', '$video_title', '$video_description', '$video_thumbnail', '$channel_id', '$video_publishedAt')";
                        $insert_queries[] = $insert_sql;
                        $videoCount++;
                    }
                }

                $nextPageToken = isset($video_data->nextPageToken) ? $video_data->nextPageToken : '';

                sleep(1);
            }
        } while (!empty($nextPageToken) && $videoCount < $videos_needed);
    }
}

foreach ($insert_queries as $insert_query) {
    if ($connect->query($insert_query) === false) {
        die('Error inserting queries: ' . $connect->error);
    }
}

class Database
{
    public function db_connect()
    {
        $connect = new mysqli('localhost', 'root', '', 'youtube_db');

        if ($connect->connect_error) {
            die('Error fetching channel videos.');
        } else {
            return $connect;
        }
    }
}