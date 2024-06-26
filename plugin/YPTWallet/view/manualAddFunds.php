<?php
require_once '../../../videos/configuration.php';
require_once $global['systemRootPath'] . 'objects/user.php';
require_once $global['systemRootPath'] . 'objects/functions.php';

if (!User::isLogged()) {
    header("Location: {$global['webSiteRootURL']}");
}

$plugin = AVideoPlugin::loadPluginIfEnabled("YPTWallet");
$obj = $plugin->getDataObject();
$options = _json_decode($obj->addFundsOptions);
$_page = new Page(array('Add Funds'));
?>
<div class="container">
    <div class="row">
        <div class="panel panel-default">
            <div class="panel-heading"><?php echo __("Add Funds"); ?></div>
            <div class="panel-body">
                <div class="col-sm-6">
                    <?php echo $obj->add_funds_text ?>
                </div>
                <div class="col-sm-6">
                    <?php
                    if (!empty($_GET['status'])) {
                        $text = "unknow";
                        $class = "danger";
                        switch ($_GET['status']) {
                            case "fail":
                                $text = $obj->add_funds_success_fail;
                                break;
                            case "success":
                                $text = $obj->add_funds_success_success;
                                $class = "success";
                                break;
                            case "cancel":
                                $text = $obj->add_funds_success_cancel;
                                $class = "warning";
                                break;
                        }
                    ?>
                        <div class="alert alert-<?php echo $class; ?>">
                            <?php echo $text; ?>
                        </div>
                    <?php
                    }
                    ?>
                    <div class="form-group">
                        <label for="value"><?php echo __("Specify Amount"); ?> <?php echo $obj->currency_symbol; ?> <?php echo $obj->currency; ?></label>
                        <select class="form-control" id="value">
                            <?php
                            foreach ($options as $value) {
                            ?>
                                <option value="<?php echo $value; ?>"><?php echo $obj->currency_symbol; ?> <?php echo $value; ?> <?php echo $obj->currency; ?></option>
                            <?php
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="information"><?php echo __("Information"); ?></label>
                        <textarea class="form-control" id="information" name="information"></textarea>
                    </div>
                    <button class="btn btn-primary" id="manualAddFundsPageButton">
                        <?php echo $obj->manualAddFundsPageButton; ?>
                    </button>
                </div>
            </div>
        </div>


    </div>
</div>
<script>
    $(document).ready(function() {
        $('#manualAddFundsPageButton').click(function() {
            modal.showPleaseWait();
            $.ajax({
                url: webSiteRootURL + 'plugin/YPTWallet/view/manualAddFunds.json.php',
                type: "POST",
                data: {
                    value: $('#value').val(),
                    information: $('#information').val(),
                    autoWithdraw: <?php echo empty($autoWithdraw) ? 0 : 1; ?>
                },
                success: function(response) {
                    modal.hidePleaseWait();
                    if (response.error) {
                        setTimeout(function() {
                            avideoAlert("<?php echo __("Sorry!"); ?>", response.msg, "error");
                        }, 500);
                    } else {
                        setTimeout(function() {
                            avideoAlert("<?php echo __("Congratulations!"); ?>", "<?php echo __("Your request was sent"); ?>", "success");
                        }, 500);
                    }
                }
            });
        });
    });
</script>
<?php
$_page->print();
?>