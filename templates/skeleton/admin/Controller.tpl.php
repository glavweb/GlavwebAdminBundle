<?php echo "<?php\n\n"; ?>
declare(strict_types=1);

namespace <?php echo $namespace; ?>;

use App\Controller\Admin\CRUDController;

/**
 * Class <?php echo $class_name."\n"; ?>
<?php
if ($author) {
    $authorFull = $author;
    if ($authorEmail) {
        $authorFull .= ' <'.$authorEmail.'>';
    }

    echo " *\n";
    echo " * @author $authorFull\n";
}
?>
 */
class <?php echo $class_name; ?> extends CRUDController
{}
