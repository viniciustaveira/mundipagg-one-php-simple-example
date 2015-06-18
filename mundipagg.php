<?php

$response = array();

http_response_code(404);
header('Content-Type: application/json');
print json_encode($response);