<?php
error_reporting();
session_start();
include_once 'functions.php';
spl_autoload_register('loadclass');
$db1=new database();
$db=$db1::connect('localhost','php_files','root','');
if(isset($_POST['csv_export']))
{
    try
    {
        $column_data = $db1::execute_fetchall('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA="php_files" AND TABLE_NAME="csv_product";');
    }
    catch (Exception $e)
    {
        $_SESSION['errorr'] = "Doslo k chybe -> {$e->getMessage()}";
        header('Refresh:0');
    }
    try
    {
        $columns = '';
        $filename = 'export.csv';
        $csv_export = '';
        $i = 0;
        foreach ($column_data as $name)
        {
            if ($i == 0)
            {
                $csv_export .= '"' . $name['COLUMN_NAME'] . '"';
            }
            else
            {
                $csv_export .= ',"' . $name['COLUMN_NAME'] . '"';
            }
            $i++;
        }
        $csv_export .= PHP_EOL;
        $sql = 'SELECT * FROM csv_product;';
        $ugu = $db->prepare($sql);
        $ugu->execute();
        $data = $ugu->fetchAll();
        foreach ($data as $row)
        {
            for ($j = 0; $j < $i; $j++)
            {
                if ($j == 0)
                {
                    $csv_export .= '"' . $row[(string)$ugu->getColumnMeta($j)['name']] . '"';
                }
                else
                {
                    $csv_export .= ',"' . str_replace('"','""',$row[(string)$ugu->getColumnMeta($j)['name']]) . '"';
                }
            }
            $csv_export .= PHP_EOL;
        }
        header('Content-Description: File Transfer');
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        echo $csv_export;
        exit;
    }
    catch (Exception $e)
    {
        $_SESSION['errorr'] = "Doslo k chybe -> {$e->getMessage()}";
        header('Refresh:0');
    }
}
else if(isset($_POST['json_export']))
{
    try
    {
        $column_data = $db1::execute_fetchall('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA="php_files" AND TABLE_NAME="json_product";');
    }
    catch (Exception $e)
    {
        $_SESSION['errorr'] = "Doslo k chybe -> {$e->getMessage()}";
        header('Refresh:0');
    }
    try
    {
        $i = count($column_data);
        $filename = 'export.json';
        $json_export = [];
        $json_export[0]['type'] = 'header';
        $json_export[0]['version'] = '4.7.4';
        $json_export[0]['comment'] = 'Export to JSON plugin for PHPMyAdmin';
        $json_export[1]['type'] = 'database';
        $json_export[1]['name'] = 'php_files';
        $json_export[2]['type'] = 'table';
        $json_export[2]['name'] = 'json_product';
        $json_export[2]['database'] = 'php_files';
        $data = $db1::execute_fetchall('SELECT * FROM json_product;');
        $k = 0;
        foreach ($data as $row)
        {
            for ($j = 0; $j < $i; $j++)
            {
                $json_export[2]['data'][$k][$column_data[$j]['COLUMN_NAME']] = $row[$column_data[$j]['COLUMN_NAME']];
            }
            $k++;
        }
        $encoded = json_encode($json_export);
        header('Content-Description: File Transfer');
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        echo $encoded;
        exit;
    }
    catch (Exception $e)
    {
        $_SESSION['errorr'] = "Doslo k chybe -> {$e->getMessage()}";
        header('Refresh:0');
    }
}
if(isset($_POST['submit'],$_FILES))
{
    $name = $_FILES['myfile']['name'];
    $tmp = $_FILES['myfile']['tmp_name'];
    $type = $_FILES['myfile']['type'];
    $size = $_FILES['myfile']['size'];
    $error = $_FILES['myfile']['error'];
    $mime1 = mime_content_type($tmp);
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime2 = finfo_file($finfo, $tmp);
    finfo_close($finfo);
    $explode = explode('.',$name);
    $ext = end($explode);
    if($mime1 == 'text/plain' && $mime2 == 'text/plain' && $type == 'application/vnd.ms-excel' && $ext == 'csv' && empty($error))
    {
        $csv_file = file($tmp);
        $csv = array_map('str_getcsv', $csv_file);
        $fields = array_shift($csv);
        $str_fields = implode(', ', $fields);
        $placeholders = '';
        for ($i = 1; $i <= count($fields); $i++)
        {
            if ($i == 1)
            {
                $placeholders .= '?';
            }
            else
            {
                $placeholders .= ', ?';
            }

        }
        try
        {
            $db->exec('TRUNCATE TABLE csv_product');
            foreach ($csv as $result)
            {
                //$result2 = array_combine(range(1, count($result)), array_values($result));
                $result[4] = str_replace('"','""',$result[4]);
                $db1::execute_query("INSERT INTO csv_product({$str_fields}) VALUES({$placeholders});",$result);
            }
            $_SESSION['success'] = 'Soubor uspesne nahran.';
            header('Refresh:0');
        }
        catch (Exception $e)
        {
            $_SESSION['errorr'] = "Doslo k chybe {$e->getMessage()}";
            header('Refresh:0');
        }
    }
    else if($mime1 == 'text/plain' && $mime2 == 'text/plain' && $type == 'application/octet-stream' && $ext == 'json' && empty($error))
    {
        $json_file = file_get_contents($tmp);
        $decoded = json_decode($json_file,true);
        $placeholders = '';
        $str_fields = '';
        try
        {
            $column_data = $db1::execute_fetchall("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='php_files' AND TABLE_NAME='{$decoded[2]['name']}';");
        }
        catch (Exception $e)
        {
            $_SESSION['errorr'] = "Doslo k chybe -> {$e->getMessage()}";
            header('Refresh:0');
        }
        $i = 0;
        foreach($column_data as $name)
        {
            if($i == 0)
            {
                $str_fields .= $name['COLUMN_NAME'];
            }
            else
            {
                $str_fields .= ', '.$name['COLUMN_NAME'];
            }
            $i++;
        }
        for ($j = 0; $j < $i; $j++)
        {
            if ($j == 0)
            {
                $placeholders .= '?';
            }
            else
            {
                $placeholders .= ', ?';
            }

        }
        try
        {
            $db->exec('TRUNCATE TABLE json_product');
            foreach ($decoded[2]['data'] as $result)
            {
                $result2 = array_values($result);
                $db1::execute_query("INSERT INTO json_product({$str_fields}) VALUES({$placeholders});",$result2);
            }
            $_SESSION['success'] = 'Soubor uspesne nahran.';
            header('Refresh:0');
        }
        catch (Exception $e)
        {
            $_SESSION['errorr'] = "Doslo k chybe {$e->getMessage()}";
            header('Refresh:0');
        }

    }
    else
    {
        if(empty($error))
        {
            $_SESSION['errorr'] = "Nahrany soubor neni ve formatu csv, xml nebo json";
            header('Refresh:0');
        }
        else
        {
            $_SESSION['errorr'] = "Doslo k chybe -> {$error}";
            header('Refresh:0');
        }
    }
}
?>
<!DOCTYPE html>
<!--
To change this license header, choose License Headers in Project Properties.
To change this template file, choose Tools | Templates
and open the template in the editor.
-->
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" >
        <title>Some title</title>
        <link rel="canonical" href="http://www.bstst.8u.cz">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <meta name="keywords" content="maturita,eshop" >
        <meta name="description" content="Projekt dlouhodobé maturitní práce.">
        <!--GOOGLE+-->
        <link rel="author" href="https://plus.google.com/u/0/115111710746527975391">
        <link rel="publisher" href="">
        <link type="text/css" href="https://fonts.googleapis.com/css?family=Jura" rel="stylesheet">
        <link type="text/css" href="custom/css/main.min.css" rel="stylesheet">
        <link rel="icon" type="image/gif" href="favicon.ico" />
    </head>
    <body>
        <noscript>
            K správnému chodu stránky je potřeba mít aktivovaný JavaScript.
        </noscript>
        <div id="content">
            <form action="index.php" method="post" enctype="multipart/form-data">
                <input type="file" name="myfile">
                <input name="submit" type="submit" value="submit" />
                <input  type="submit" name="csv_export" value="csv export">
                <input  type="submit" name="json_export" value="json export">
            </form>
        <?php
            if(isset($_SESSION['success']))
            {
                echo $_SESSION['success'];
                if(isset($_SESSION['success_unset'],$_SESSION['success']))
                {
                    unset($_SESSION['success']);
                    unset($_SESSION['success_unset']);
                }
                if(isset($_SESSION['success']))
                {
                    $_SESSION['success_unset'] = true;
                }
            }
            if(isset($_SESSION['errorr']))
            {
                echo $_SESSION['errorr'];
                if(isset($_SESSION['err_unset'],$_SESSION['errorr']))
                {
                    unset($_SESSION['errorr']);
                    unset($_SESSION['err_unset']);
                }
                if(isset($_SESSION['errorr']))
                {
                    $_SESSION['err_unset'] = true;
                }
            }
        ?>
        </div>
    </body>
</html>
