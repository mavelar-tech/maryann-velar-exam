<?php

header('Content-Type: application/json');

require_once './sync_youtube_channel.php';

$db = new database();
$connect = $db->db_connect();

if ($connect->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$channel_info_query = "SELECT * FROM `youtube_channels`";
$channel_info_result = $connect->query($channel_info_query);

if ($channel_info_result === false) {
    echo json_encode(['error' => 'Error fetching channel information.']);
    exit;
}

$channel_info = [];

while ($row = $channel_info_result->fetch_assoc()) {
    $channel_info[] = $row;
}

$channel_videos_query = "SELECT * FROM `youtube_channel_videos`";
$channel_videos_result = $connect->query($channel_videos_query);

if ($channel_videos_result === false) {
    echo json_encode(['error' => 'Error fetching channel videos.']);
    exit;
}

$video_info = [];

while ($row = $channel_videos_result->fetch_assoc()) {
    $video_info[] = $row;
}

$data = [
    'channels' => $channel_info,
    'videos' => $video_info,
];

echo json_encode($data, JSON_PRETTY_PRINT);