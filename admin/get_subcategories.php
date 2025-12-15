<?php
include "db-conn.php";

if (isset($_GET['category_ids'])) {
    $category_ids = mysqli_real_escape_string($conn, $_GET['category_ids']);
    
    // Get subcategories for the selected categories
    $sql = "SELECT sc.* FROM sub_categories sc 
            WHERE sc.parent_id IN ($category_ids) AND sc.status = 1 
            ORDER BY sc.categories ASC";
    
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        while ($subcategory = mysqli_fetch_assoc($result)) {
            echo '<div class="multiselect-option" 
                  data-value="' . $subcategory['cate_id'] . '" 
                  data-name="' . htmlspecialchars($subcategory['categories']) . '"
                  onclick="selectSubcategory(this)">
                  ' . htmlspecialchars($subcategory['categories']) . '
                  </div>';
        }
    } else {
        echo '<div class="text-muted p-3">No subcategories found for selected categories</div>';
    }
}
?>