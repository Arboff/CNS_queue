<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['username'])){
    echo json_encode(['status'=>'error','message'=>'Not authenticated']);
    exit;
}

$surveyFile='surveys.json';
if(!file_exists($surveyFile)) file_put_contents($surveyFile,'[]');

$data=json_decode(json_encode($_POST),true);

// Add username and timestamp
$data['username']=$_SESSION['username'];
$data['timestamp']=date('Y-m-d H:i:s');

$surveys=json_decode(file_get_contents($surveyFile),true);
$surveys[]=$data;
file_put_contents($surveyFile,json_encode($surveys,JSON_PRETTY_PRINT));

echo json_encode(['status'=>'ok']);
