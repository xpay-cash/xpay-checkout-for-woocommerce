<?php
session_start();
if (!isset($_GET['token']) || $_GET['token'] != md5($_SERVER['HTTP_HOST'].gmdate('Ymd').session_id())) {
    echo ('Sesion expirada o no estas autorizado...');
    exit;
}
$fdir = dirname(__FILE__);
if (!isset($_GET['month'])) {
   $cdir = scandir($fdir);
   echo "<b>Seleccione el Año-Mes que deseas examinar:</b><br />";
   foreach ($cdir as $key => $value)
   {
      if (!in_array($value, array(".","..")))
      {
         if (is_dir($fdir . DIRECTORY_SEPARATOR . $value))
         {
             echo '- <a href="index.php?token='.$_GET['token'].'&month='.$value.'">'.$value.'</a><br />';
         }
      }
   }
} else {
    $month = preg_replace('/[^0-9a-zA-z-]+/', '', $_GET['month']);
    if (isset($_GET['day'])) {
        $day = preg_replace('/[^0-9a-zA-Z\.-]+/', '', $_GET['day']);
        if (substr($day, -4) == '.log') {
            $f = $fdir . DIRECTORY_SEPARATOR . $month . DIRECTORY_SEPARATOR . $day;
            if ( is_file($f) ) {
                // disable ZLIB ouput compression
                ini_set('zlib.output_compression','Off');
                // compress data
                if (class_exists('ZipArchive')) {
                    $zip = new ZipArchive;
                    $res = $zip->open($f.'.zip', ZipArchive::CREATE);
                    if ($res === true) {
                        $zip->addFile($f, $day);
                        $zip->close();
                        $output = file_get_contents($f.'.zip');
                        unlink($f.'.zip');
                        header('Content-Type: application/x-download');
                        header('Content-Length: '.strlen($output));
                        header('Content-Disposition: attachment; filename="'.$day.'.zip"');
                        header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
                        header('Pragma: no-cache');
                        // output data
                        echo $output;
                        exit;
                    }
                }
                if (function_exists('gzencode')) {
                    $gzipoutput = gzencode(file_get_contents($f), 9);
                    header('Content-Type: application/x-download');
                    header('Content-Length: '.strlen($gzipoutput));
                    header('Content-Disposition: attachment; filename="'.$day.'.gz"');
                    header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
                    header('Pragma: no-cache');
                    // output data
                    echo $gzipoutput;
                    exit;
                }
                $output = file_get_contents($f);
                header('Content-Type: application/x-download');
                header('Content-Length: '.strlen($output));
                header('Content-Disposition: attachment; filename="'.$day.'"');
                header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
                header('Pragma: no-cache');
                // output data
                echo $output;
                exit;
            }
        }
    }
    echo '<a href="index.php?token='.$_GET['token'].'">Volver a la lista de Meses/Años.</a><br /><br />';
    echo "<b>Seleccione el día que deseas examinar:</b><br />";
    $dir = $fdir . DIRECTORY_SEPARATOR . $month;
    if (!is_dir($dir)) {
        die('Mes invalido: '.$dir);
    }
    $cdir = scandir($dir);
    foreach ($cdir as $key => $value)
    {
        if (!in_array($value, array(".","..")))
        {
            if (!is_dir($dir . DIRECTORY_SEPARATOR . $value))
            {
                echo '- <a href="index.php?token='.$_GET['token'].'&month='.$month.'&day='.$value.'">'.$value.'</a><br />';
            }
        }
    }
}
