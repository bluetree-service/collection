<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../vendor/autoload.php';
?>
    <div class="example">
        <h3>Set data into Object</h3>
        <div>
            <?php require_once __DIR__ . '/base_object/set_data.php'; ?>
        </div>
    </div>
    <div class="example">
        <h3>Check is data exists</h3>
        <div>
            <?php require_once __DIR__ . '/base_object/check_data.php'; ?>
        </div>
    </div>
    <div class="example">
        <h3>Get data from Object</h3>
        <div>
            <?php require_once __DIR__ . '/base_object/get_data.php'; ?>
        </div>
    </div>
    <div class="example">
        <h3>Replace data (show origin data + restore data)</h3>
        <div>
            <?php require_once __DIR__ . '/base_object/replace_data.php'; ?>
        </div>
    </div>
    <div class="example">
        <h3>Data preparation (gat and set)</h3>
        <div>
            <?php require_once __DIR__ . '/base_object/data_preparation.php'; ?>
        </div>
    </div>
    <div class="example">
        <h3>Export data</h3>
        <div>
            <?php require_once __DIR__ . '/base_object/export_data.php'; ?>
        </div>
    </div>
    <div class="example">
        <h3>Destroy data</h3>
        <div>
            <?php require_once __DIR__ . '/base_object/destroy_data.php'; ?>
        </div>
    </div>
    <div class="example">
        <h3>Compare two Objects</h3>
        <div>
            <?php require_once __DIR__ . '/base_object/compare_data.php'; ?>
        </div>
    </div>
    <div class="example">
        <h3>Merge two Objects</h3>
        <div>
            <?php require_once __DIR__ . '/base_object/merge_data.php'; ?>
        </div>
    </div>
    <div class="example">
        <h3>Recursive function</h3>
        <div>
            <?php require_once __DIR__ . '/base_object/recursive.php'; ?>
        </div>
    </div>
    <div class="example">
        <h3>Data validation on insert</h3>
        <div>
            <?php require_once __DIR__ . '/base_object/data_validation.php'; ?>
        </div>
    </div>
    <div class="example">
        <h3>Object as array</h3>
        <div>
            <?php require_once __DIR__ . '/base_object/array_access.php'; ?>
        </div>
    </div>
<?php
require_once __DIR__ . '/footer.php';
