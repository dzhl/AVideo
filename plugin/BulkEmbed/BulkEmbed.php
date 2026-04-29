<?php

require_once $global['systemRootPath'] . 'plugin/Plugin.abstract.php';

class BulkEmbed extends PluginAbstract {

    const PERMISSION_BULK_EMBED = 0;
    const DEFAULT_DEPRECATED_API_KEY_SHA256 = '304b13b56b6d051b8b0d350d8b5915511e816aa6c6b9d84da22bcce626326c47';
    
    public function getTags() {
        return array(
            PluginTags::$FREE,
        );
    }

    public function getDescription() {
        return self::getYouTubeAPIKeyHelp();
    }

    public function getName() {
        return "BulkEmbed";
    }

    public function getUUID() {
        return "bulkembed-8c31-4f15-a355-48715fac13f3";
    }

    public function getPluginVersion() {
        return "1.2";
    }

    public function getEmptyDataObject() {
        global $global;
        $obj = new stdClass();

        $obj->API_KEY = "";
        $obj->onlyAdminCanBulkEmbed = true;
        $obj->useOriginalYoutubeDate = true;
        return $obj;
    }    

    static function getAPIKey()
    {
        $obj = AVideoPlugin::getObjectData("BulkEmbed");
        return trim(@$obj->API_KEY);
    }

    static function hasValidAPIKey()
    {
        $apiKey = self::getAPIKey();
        return !empty($apiKey) && !self::isDeprecatedDefaultAPIKey($apiKey);
    }

    static function isDeprecatedDefaultAPIKey($apiKey)
    {
        return hash_equals(self::DEFAULT_DEPRECATED_API_KEY_SHA256, hash('sha256', trim($apiKey)));
    }

    static function getYouTubeAPIKeyHelp()
    {
        $credentialsURL = 'https://console.cloud.google.com/apis/credentials';
        $youtubeAPIURL = 'https://console.cloud.google.com/apis/library/youtube.googleapis.com';
        $docsURL = 'https://developers.google.com/youtube/v3/getting-started';

        $str = '<p>Bulk Embed lets authorized users search YouTube and embed multiple videos into this site at once. It can also detect videos that have already been embedded, so you can avoid importing duplicates.</p>';
        $str .= '<p><strong>You must configure your own YouTube Data API v3 key to use Bulk Embed.</strong></p>';
        $str .= '<ol>';
        $str .= '<li>Open the <a href="' . $credentialsURL . '" target="_blank" rel="noopener noreferrer">Google Cloud Credentials page</a> and select or create a Google Cloud project.</li>';
        $str .= '<li>Open the <a href="' . $youtubeAPIURL . '" target="_blank" rel="noopener noreferrer">YouTube Data API v3 page</a> and click <strong>Enable</strong> for the selected project.</li>';
        $str .= '<li>Go back to <strong>APIs & Services &gt; Credentials</strong>, click <strong>Create credentials</strong>, and choose <strong>API key</strong>.</li>';
        $str .= '<li>Copy the generated API key and paste it into this plugin setting: <strong>API_KEY</strong>.</li>';
        $str .= '<li>Recommended: restrict the key in Google Cloud to the <strong>YouTube Data API v3</strong>, and optionally restrict usage to your server/IP or HTTP referrer.</li>';
        $str .= '</ol>';
        $str .= '<p>Google documentation: <a href="' . $docsURL . '" target="_blank" rel="noopener noreferrer">YouTube Data API v3 Getting Started</a>.</p>';

        return $str;
    }

    static function getMissingAPIKeyMessage()
    {
        return '<h3>Bulk Embed requires your own YouTube API key</h3>' . self::getYouTubeAPIKeyHelp();
    }
    
    public function getPluginMenu() {
        global $global;
        $menu = '<button onclick="avideoModalIframe(webSiteRootURL +\'plugin/BulkEmbed/search.php\');" class="btn btn-primary btn-xs btn-block" target="_blank">Search</button>';
        return $menu;
    }
    
    public function getUploadMenuButton(){
        global $global;
        $obj = $this->getDataObject();
        
        if(BulkEmbed::canBulkEmbed()){
            return '<li><a  href="#" onclick="avideoModalIframeFull(webSiteRootURL+\'plugin/BulkEmbed/search.php\');return false;" class="faa-parent animated-hover"><span class="fas fa-link faa-burst"></span> '.__("Bulk Embed").'</a></li>';
        }else{
            return '';
        }
    }


    function getPermissionsOptions()
    {
        $permissions = array();

        $permissions[] = new PluginPermissionOption(self::PERMISSION_BULK_EMBED, __("Can Bulk Embed"), "Members of the designated user group will have access Bulk Embed videos", 'BulkEmbed');
        return $permissions;
    }

    static function canBulkEmbed()
    {

        if (User::isAdmin() || isCommandLineInterface()) {
            return true;
        }
        
        if(!User::isLogged()){
            return false;
        }

        $objo = AVideoPlugin::getObjectData("BulkEmbed");
        if($objo->onlyAdminCanBulkEmbed && !User::isAdmin()){
            return false;
        }

        return Permissions::hasPermission(self::PERMISSION_BULK_EMBED, 'BulkEmbed');
    }
}
