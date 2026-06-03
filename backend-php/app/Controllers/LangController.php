<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Shared\Lang;

class LangController extends BaseController
{
    public function switch(): void
    {
        $lang = (string) ($_GET['lang'] ?? Lang::getDefaultLang());
        Lang::setActiveLang($lang);

        // Redirect back to referer or home
        $redirect = $_SERVER['HTTP_REFERER'] ?? 'index.php?route=home/index';
        header('Location: ' . $redirect);
        exit;
    }
}
