<?php
$uniqid = uniqid();
$obj = AVideoPlugin::getObjectData("PayPalYPT");
?>
<button type="submit" class="btn btn-primary" id="YPTWalletPayPalRecurrentButton<?php echo $uniqid; ?>"><i class="fab fa-paypal"></i> <?php echo __($obj->subscriptionButtonLabel); ?></button>
<script>
    $(document).ready(function () {
        $('#YPTWalletPayPalRecurrentButton<?php echo $uniqid; ?>').click(function (evt) {
            evt.preventDefault();
            modal.showPleaseWait();

            $.ajax({
                url: webSiteRootURL+'plugin/YPTWallet/plugins/YPTWalletPayPal/requestSubscription.json.php',
                data: {
                    "plans_id": "<?php echo @$_GET['plans_id']; ?>"
                },
                type: 'post',
                success: function (response) {
                    if (!response.error) {
                        document.location = response.approvalLink;
                    } else {
                        avideoAlert("<?php echo __("Sorry!"); ?>", "<?php echo __("Error!"); ?>", "error");
                        modal.hidePleaseWait();
                    }
                }
            });
            return false;
        });
    });

</script>