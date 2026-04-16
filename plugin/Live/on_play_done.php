<?php

require_once '../../videos/configuration.php';
_error_log("NGINX ON Play Done POST: ".json_encode($_POST), AVideoLog::$DEBUG, true);
_error_log("NGINX ON Play Done GET: ".json_encode($_GET), AVideoLog::$DEBUG, true);
