<?php

require_once '../../videos/configuration.php';
_error_log("NGINX ON Update POST: ".json_encode($_POST), AVideoLog::$DEBUG, true);
_error_log("NGINX ON Update GET: ".json_encode($_GET), AVideoLog::$DEBUG, true);
