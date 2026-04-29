<?php
global $global, $config;
if (!isset($global['systemRootPath'])) {
    require_once '../videos/configuration.php';
}
require_once $global['systemRootPath'] . 'objects/user.php';
if (!User::isLogged()) {
    forbiddenPage('Permission denied');
}
require_once $global['systemRootPath'] . 'objects/comment.php';
$_page = new Page(array('My Comments'));
$commentTemplate = json_encode(file_get_contents($global['systemRootPath'] . 'view/videoComments_template.php'));
?>
<style>
    .myCommentsTabArea {
        margin-top: 15px;
    }

    .myCommentsTabArea .media {
        background-color: #88888808;
        padding: 0;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 10px;
    }

    .myCommentsTabArea .media:hover {
        background-color: #88888810;
    }

    .myCommentsTabArea .media .media-left {
        margin-left: 5px;
    }

    .myCommentsTabArea .userCanNotAdminComment .hideIfUserCanNotAdminComment,
    .myCommentsTabArea .userCanNotEditComment .hideIfUserCanNotEditComment,
    .myCommentsTabArea .isNotPinned .hideIfIsUnpinned,
    .myCommentsTabArea .isPinned .hideIfIsPinned,
    .myCommentsTabArea .isResponse .hideIfIsResponse,
    .myCommentsTabArea .totalLikes0,
    .myCommentsTabArea .totalDislikes0,
    .myCommentsTabArea .isOpen > .hideIfIsOpen,
    .myCommentsTabArea .isNotOpen > .hideIfIsNotOpen {
        display: none;
    }

    .myCommentsTabArea > .media > div.media-body .repliesArea {
        margin-left: -60px;
        padding-left: 5px;
    }

    .myCommentsTabArea > .media > div.media-body > div.repliesArea .repliesArea .repliesArea {
        margin-left: -70px;
        padding-left: 0;
    }

    .myCommentsTabArea > .media div.media-body {
        overflow: visible;
    }

    .myCommentsTabArea > .media div.media-left > img {
        width: 60px;
    }

    .myCommentsTabArea > .media .commentsButtonsGroup {
        opacity: 0.5;
    }

    .myCommentsTabArea > .media .media-body:hover > .commentsButtonsGroup {
        opacity: 1;
    }

    .myCommentsTabArea .isAResponse {
        margin-left: 20px;
    }

    .myCommentsTabArea > .media .media .isAResponse {
        margin-left: 10px;
    }

    .myCommentsTabArea > .media .media .media .isAResponse {
        margin-left: 5px;
    }

    .myCommentsTabArea .repliesArea div.media-body h3.media-heading {
        display: none;
    }

    #myCommentsPanel .nav-tabs {
        margin-bottom: 0;
        border-bottom: 1px solid #ddd;
    }

    #myCommentsPanel .tab-content {
        padding-top: 15px;
    }

    .my-comments-empty {
        padding: 40px 0;
        text-align: center;
        color: #aaa;
    }

    .my-comments-empty i {
        display: block;
        margin-bottom: 10px;
    }
</style>
<div class="container-fluid">
    <div class="panel panel-default" id="myCommentsPanel">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="fas fa-comments"></i> <?php echo __('My Comments'); ?>
            </h3>
        </div>
        <div class="panel-body">
            <ul class="nav nav-tabs" role="tablist">
                <li role="presentation" class="active">
                    <a href="#postedTab" aria-controls="postedTab" role="tab" data-toggle="tab">
                        <i class="fas fa-comment"></i>
                        <?php echo __('Comments I Wrote'); ?>
                        <span class="badge" id="postedBadge" style="display:none;"></span>
                    </a>
                </li>
                <li role="presentation">
                    <a href="#receivedTab" aria-controls="receivedTab" role="tab" data-toggle="tab">
                        <i class="fas fa-inbox"></i>
                        <?php echo __('Comments on My Videos'); ?>
                        <span class="badge" id="receivedBadge" style="display:none;"></span>
                    </a>
                </li>
            </ul>
            <div class="tab-content">
                <div role="tabpanel" class="tab-pane active" id="postedTab">
                    <div id="postedCommentsArea" class="myCommentsTabArea canComment userLogged"></div>
                    <div class="text-center" style="margin-top: 10px;">
                        <button class="btn btn-link" onclick="loadPostedComments(0, lastPostedPage + 1);" id="postedLoadMoreBtn" style="display:none;">
                            <i class="fas fa-chevron-down"></i> <?php echo __('Load More'); ?>
                        </button>
                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="receivedTab">
                    <div id="receivedCommentsArea" class="myCommentsTabArea canComment userLogged"></div>
                    <div class="text-center" style="margin-top: 10px;">
                        <button class="btn btn-link" onclick="loadReceivedComments(0, lastReceivedPage + 1);" id="receivedLoadMoreBtn" style="display:none;">
                            <i class="fas fa-chevron-down"></i> <?php echo __('Load More'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    var commentTemplate = <?php echo $commentTemplate; ?>;
    var lastPostedPage = 0;
    var lastReceivedPage = 0;
    var receivedLoaded = false;

    function mcGetCommentTemplate(itemsArray) {
        var template = commentTemplate;
        for (var search in itemsArray) {
            var replace = itemsArray[search];
            if (typeof replace == 'boolean') {
                if (search == 'userCanAdminComment') {
                    replace = replace ? 'userCanAdminComment' : 'userCanNotAdminComment';
                } else if (search == 'userCanEditComment') {
                    replace = replace ? 'userCanEditComment' : 'userCanNotEditComment';
                }
            } else if (search == 'myVote') {
                if (replace == '1') {
                    replace = 'myVote1';
                } else if (replace == '-1') {
                    replace = 'myVote-1';
                } else {
                    replace = 'myVote0';
                }
            }
            if (typeof replace !== 'string' && typeof replace !== 'number') {
                continue;
            }
            if (search == 'pin') {
                replace = !empty(replace) ? 'isPinned' : 'isNotPinned';
            }
            template = template.replace(new RegExp('{' + search + '}', 'g'), replace);
        }
        template = template.replace(new RegExp('{replyText}', 'g'), <?php printJSString('Reply') ?>);
        template = template.replace(new RegExp('{viewAllRepliesText}', 'g'), <?php printJSString('View all replies') ?>);
        template = template.replace(new RegExp('{hideRepliesText}', 'g'), <?php printJSString('Hide Replies') ?>);
        template = template.replace(new RegExp('{likes}', 'g'), 0);
        template = template.replace(new RegExp('{dislikes}', 'g'), 0);
        template = template.replace(new RegExp('{myVote}', 'g'), 'myVote0');
        if (!empty(itemsArray.comments_id_pai)) {
            template = template.replace(new RegExp('{isResponse}', 'g'), 'isResponse');
        } else {
            template = template.replace(new RegExp('{isResponse}', 'g'), 'isNotResponse');
        }
        return template;
    }

    function mcProcessCommentRow(itemsArray) {
        if (typeof itemsArray === 'function') {
            return false;
        }
        itemsArray.isAResponse = !empty(itemsArray.comments_id_pai) ? 'isAResponse' : 'isNotAResponse';
        itemsArray.videoLink = '#';
        itemsArray.videoTitle = '';
        if (typeof itemsArray.video != 'undefined' && !empty(itemsArray.video)) {
            itemsArray.videoLink = itemsArray.video.link;
            itemsArray.videoTitle = itemsArray.video.title;
        }
        var template = mcGetCommentTemplate(itemsArray);
        template = $(template);
        var repliesAreaSelector = '> div.media-body > div.repliesArea';
        if (typeof itemsArray.responses != 'undefined' && itemsArray.responses.length > 0) {
            for (var i in itemsArray.responses) {
                var row = itemsArray.responses[i];
                if (typeof row === 'function') {
                    continue;
                }
                var templateRow = mcProcessCommentRow(row);
                template.find(repliesAreaSelector).removeClass('isNotOpen').addClass('isOpen').append(templateRow);
            }
        }
        return template;
    }

    function mcAddComment(itemsArray, areaSelector, comments_id, append) {
        var template = mcProcessCommentRow(itemsArray);
        var selector = areaSelector + ' ';
        if (!empty(comments_id)) {
            selector = '#comment_' + comments_id + ' > div.media-body > div.repliesArea ';
        }
        var element = '#comment_' + itemsArray.id;
        if ($(selector + element).length) {
            var object = $('<div/>').append(template);
            var html = $(object).find(element).html();
            $(selector + element).html(html);
        } else {
            if (append) {
                $(selector).append(template);
            } else {
                $(selector).prepend(template);
            }
        }
        return true;
    }

    function loadPostedComments(comments_id, page) {
        var url = webSiteRootURL + 'objects/myComments.json.php?type=posted';
        url = addQueryStringParameter(url, 'comments_id', comments_id);
        url = addQueryStringParameter(url, 'current', page);
        lastPostedPage = page;
        modal.showPleaseWait();
        $.ajax({
            url: url,
            success: function(response) {
                modal.hidePleaseWait();
                if (response.error) {
                    avideoAlertError(response.msg);
                } else {
                    var areaSelector = '#postedCommentsArea';
                    if (!empty(comments_id)) {
                        areaSelector = '#comment_' + comments_id + ' > div.media-body > div.repliesArea';
                    } else {
                        var hasMore = !empty(response.rows) && response.total > response.rowCount * page;
                        if (hasMore) {
                            $('#postedLoadMoreBtn').fadeIn();
                        } else {
                            if (page > 1) {
                                avideoToastInfo(<?php printJSString('No more comments') ?>);
                            }
                            $('#postedLoadMoreBtn').fadeOut();
                        }
                        if (page == 1) {
                            var badge = $('#postedBadge');
                            badge.text(response.total);
                            badge.show();
                        }
                    }
                    if (page <= 1) {
                        $(areaSelector).empty();
                    }
                    if (empty(response.rows) && page == 1) {
                        $(areaSelector).html('<div class="my-comments-empty"><i class="fas fa-comment-slash fa-3x"></i><?php echo __("You have not posted any comments yet."); ?></div>');
                        return;
                    }
                    for (var i in response.rows) {
                        var row = response.rows[i];
                        if (typeof row === 'function') {
                            continue;
                        }
                        mcAddComment(row, areaSelector, comments_id, true);
                    }
                }
            },
            error: function() {
                modal.hidePleaseWait();
                avideoAlertError(<?php printJSString('Error loading comments') ?>);
            }
        });
    }

    function loadReceivedComments(comments_id, page) {
        var url = webSiteRootURL + 'objects/myComments.json.php?type=received';
        url = addQueryStringParameter(url, 'comments_id', comments_id);
        url = addQueryStringParameter(url, 'current', page);
        lastReceivedPage = page;
        modal.showPleaseWait();
        $.ajax({
            url: url,
            success: function(response) {
                modal.hidePleaseWait();
                if (response.error) {
                    avideoAlertError(response.msg);
                } else {
                    var areaSelector = '#receivedCommentsArea';
                    if (!empty(comments_id)) {
                        areaSelector = '#comment_' + comments_id + ' > div.media-body > div.repliesArea';
                    } else {
                        var hasMore = !empty(response.rows) && response.total > response.rowCount * page;
                        if (hasMore) {
                            $('#receivedLoadMoreBtn').fadeIn();
                        } else {
                            if (page > 1) {
                                avideoToastInfo(<?php printJSString('No more comments') ?>);
                            }
                            $('#receivedLoadMoreBtn').fadeOut();
                        }
                        if (page == 1) {
                            var badge = $('#receivedBadge');
                            badge.text(response.total);
                            badge.show();
                        }
                    }
                    if (page <= 1) {
                        $(areaSelector).empty();
                    }
                    if (empty(response.rows) && page == 1) {
                        $(areaSelector).html('<div class="my-comments-empty"><i class="fas fa-inbox fa-3x"></i><?php echo __("No comments on your videos yet."); ?></div>');
                        return;
                    }
                    for (var i in response.rows) {
                        var row = response.rows[i];
                        if (typeof row === 'function') {
                            continue;
                        }
                        mcAddComment(row, areaSelector, comments_id, true);
                    }
                }
            },
            error: function() {
                modal.hidePleaseWait();
                avideoAlertError(<?php printJSString('Error loading comments') ?>);
            }
        });
    }

    $(document).ready(function() {
        loadPostedComments(0, 1);
        $('a[href="#receivedTab"]').on('shown.bs.tab', function() {
            if (!receivedLoaded) {
                receivedLoaded = true;
                loadReceivedComments(0, 1);
            }
        });
    });
</script>
<?php
$_page->print();
?>
