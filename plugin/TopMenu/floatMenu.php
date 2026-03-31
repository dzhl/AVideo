<?php

require_once $global['systemRootPath'] . 'plugin/TopMenu/Objects/Menu.php';
require_once $global['systemRootPath'] . 'plugin/TopMenu/Objects/MenuItem.php';

$menu = Menu::getAllActive(Menu::$typeFloatMenu);
?>
<!-- floatmenu start -->
<?php
$menuItems = array();
$icon = '';
foreach ($menu as $key => $value) {
    $menuItems = MenuItem::getAllFromMenu($value['id'], true);
    if (!empty($menuItems)) {
        $icon = $value['icon'];
        break;
    }
}
if (empty($menuItems)) {
    return;
}

?>
<div id="topMenuFloatMenu" class="floatingRightBottom">

    <button class="btn btn-primary btn-lg btn-circle circle-menu" data-toggle="tooltip" title="<?php echo __('Open'); ?>" data-placement="left">
        <i class="<?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?> fa-2x"></i>
    </button>

    <div class="submenu hidden">
        <?php
        foreach ($menuItems as $key2 => $value2) {
        ?>
            <a href="<?php echo htmlspecialchars($value2['finalURL'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $value2['target']; ?> class="btn btn-default btn-lg btn-circle submenu-item <?php echo getCSSAnimationClassAndStyle('animate__bounceIn', 'topFloatMenu'); ?> " data-toggle="tooltip" title="<?php echo htmlspecialchars(__($value2['title']), ENT_QUOTES, 'UTF-8'); ?>" data-placement="left">
                <?php
                if (!empty($value2['icon'])) {
                ?>
                    <i class="<?php echo htmlspecialchars($value2['icon'], ENT_QUOTES, 'UTF-8') ?> fa-2x"></i>
                <?php
                } else {
                    echo '<i class="fas fa-folder fa-2x"></i>';
                }
                ?>
            </a>
        <?php
        }
        ?>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#topMenuFloatMenu .circle-menu').click(function() {
            $('.submenu').toggleClass('hidden');
        });


        $(document).click(function(event) {
            var target = $(event.target);
            if (!target.closest('#topMenuFloatMenu').length) {
                $('.submenu').addClass('hidden');
            }
        });
    });
</script>
