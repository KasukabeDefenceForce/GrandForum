<?php
    if(isset($_POST['svg'])){
        $svg = $_POST['svg'];
        
        $svg = str_replace('xmlns="http://www.w3.org/2000/svg"', '', $svg);
        
        $hash = md5($svg);
        file_put_contents("/tmp/$hash.svg", $svg);
        system("convert +antialias -background transparent /tmp/$hash.svg /tmp/$hash.png");
        $png = file_get_contents("/tmp/$hash.png");
        unlink("/tmp/$hash.svg");
        unlink("/tmp/$hash.png");
        
        echo base64_encode($png);
    }

?>
