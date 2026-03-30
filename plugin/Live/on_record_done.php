<?php
require_once '../../videos/configuration.php';
// Security: accept only private/loopback IPs (Docker/local NGINX) or requests with the correct callbackSecret.
Live::assertRtmpCallbackAllowed();

set_time_limit(300);// 5 minutes
_error_log("on_record_done SendRecordedToEncoder {$global['webSiteRootURL']} line=" . __LINE__);
require_once '../AVideoPlugin.php';
_error_log("on_record_done SendRecordedToEncoder {$global['webSiteRootURL']} line=" . __LINE__);
$global['bypassSameDomainCheck'] = 1;
_error_log("on_record_done start");
$plugin = AVideoPlugin::loadPluginIfEnabled('SendRecordedToEncoder');
_error_log("on_record_done SendRecordedToEncoder {$global['webSiteRootURL']} line=" . __LINE__);
if ($plugin) {
    _error_log("on_record_done SendRecordedToEncoder {$global['webSiteRootURL']} line=" . __LINE__);
    $plugin->on_record_done();
}
_error_log("on_record_done end");
