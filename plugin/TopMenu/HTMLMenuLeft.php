<?php
$configFile = $global['systemRootPath'] . 'videos/configuration.php';

require_once $configFile;
require_once $global['systemRootPath'] . 'plugin/TopMenu/Objects/Menu.php';
require_once $global['systemRootPath'] . 'plugin/TopMenu/Objects/MenuItem.php';

$menu = Menu::getAllActive(Menu::$typeLeftMenu);
?>
<!-- left menu start -->
<?php
foreach ($menu as $key => $value) {
    ?>
    <li>
        <hr>
        <strong class="text-danger hideIfCompressed">
            <?php
            if (!empty($value['icon'])) {
                ?>
                <i class="<?php echo htmlspecialchars($value['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                <?php
            }
            ?>
            <?php echo htmlspecialchars(__($value['menuName']), ENT_QUOTES, 'UTF-8'); ?>
        </strong>
    </li>
    <?php
    $menuItems = MenuItem::getAllFromMenu($value['id'], true);
    foreach ($menuItems as $key2 => $value2) {
        ?>
        <li>
            <a  href="<?php echo htmlspecialchars($value2['finalURL'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $value2['target']; ?> >
                <?php
                if (!empty($value2['icon'])) {
                    ?>
                    <i class="<?php echo htmlspecialchars($value2['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                    <?php
                }
                ?>
                <span class="menuLabel">
                    <?php echo htmlspecialchars(__($value2['title']), ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </a>
        </li>
        <?php
    }
}
?>
<!-- left menu end -->
